<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PegawaiReferenceProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_client_proxies_scoped_pegawai_request_to_sikkepo(): void
    {
        config([
            'sikkepo.base_url' => 'https://sikkepo.test',
            'sikkepo.platform_client_id' => 'perjadin',
            'sikkepo.platform_client_secret' => 'secret',
        ]);

        Http::fake([
            'https://sikkepo.test/api/v1/platform/token' => Http::response([
                'access_token' => 'platform-token',
            ], 200),
            'https://sikkepo.test/api/v1/platform/pegawai*' => Http::response([
                'data' => [[
                    'pegawai_id' => '11111111-1111-4111-8111-111111111111',
                    'nip' => '198001012010011001',
                    'nama' => 'Nama Pegawai',
                    'aktif' => true,
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1],
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/references/pegawai?q=Nama&aktif=1');

        $response->assertOk()
            ->assertJsonPath('data.0.pegawai_id', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('data.0.aktif', true);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://sikkepo.test/api/v1/platform/pegawai?q=Nama&aktif=1'
                && $request->header('Authorization') === ['Bearer platform-token'];
        });
    }

    public function test_pegawai_proxy_requires_local_api_authentication(): void
    {
        $this->getJson('/api/v1/references/pegawai')->assertUnauthorized();
    }

    public function test_upstream_failure_is_returned_as_safe_gateway_error(): void
    {
        config([
            'sikkepo.base_url' => 'https://sikkepo.test',
            'sikkepo.platform_client_id' => 'perjadin',
            'sikkepo.platform_client_secret' => 'secret',
        ]);

        Http::fake([
            'https://sikkepo.test/api/v1/platform/token' => Http::response([
                'access_token' => 'platform-token',
            ], 200),
            'https://sikkepo.test/api/v1/platform/pegawai*' => Http::response([], 503),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/references/pegawai')
            ->assertStatus(502)
            ->assertJsonPath('code', 'sikkepo_unavailable');
    }

    public function test_pegawai_proxy_refreshes_cached_token_once_after_upstream_unauthorized_response(): void
    {
        config([
            'sikkepo.base_url' => 'https://sikkepo.test',
            'sikkepo.platform_client_id' => 'perjadin',
            'sikkepo.platform_client_secret' => 'secret',
        ]);
        Cache::forget(config('sikkepo.token_cache_key'));

        Http::fake([
            'https://sikkepo.test/api/v1/platform/token' => Http::sequence()
                ->push(['access_token' => 'expired-token', 'expires_in' => 60], 200)
                ->push(['access_token' => 'fresh-token', 'expires_in' => 3600], 200),
            'https://sikkepo.test/api/v1/platform/pegawai*' => Http::sequence()
                ->push(['message' => 'Unauthenticated.'], 401)
                ->push([
                    'data' => [[
                        'pegawai_id' => '11111111-1111-4111-8111-111111111111',
                        'nip' => '198001012010011001',
                        'nama' => 'Nama Pegawai',
                        'aktif' => true,
                    ]],
                    'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1],
                ], 200),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/references/pegawai?nip=198001012010011001')
            ->assertOk()
            ->assertJsonPath('data.0.nip', '198001012010011001');

        Http::assertSentCount(4);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://sikkepo.test/api/v1/platform/pegawai?nip=198001012010011001'
                && $request->header('Authorization') === ['Bearer fresh-token'];
        });
    }
}
