<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitReferenceProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_client_proxies_scoped_unit_request_to_sikkepo(): void
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
            'https://sikkepo.test/api/v1/data/unit*' => Http::response([
                'data' => [[
                    'id' => '20000000-0000-4000-8000-000000000001',
                    'kode' => 'BKD',
                    'nama' => 'Badan Kepegawaian Daerah',
                ]],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 1],
            ], 200),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/references/units?q=Kepegawaian&per_page=20')
            ->assertOk()
            ->assertJsonPath('data.0.id', '20000000-0000-4000-8000-000000000001')
            ->assertJsonPath('data.0.nama', 'Badan Kepegawaian Daerah');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://sikkepo.test/api/v1/data/unit?q=Kepegawaian&per_page=20'
                && $request->header('Authorization') === ['Bearer platform-token'];
        });
    }

    public function test_unit_proxy_requires_local_api_authentication(): void
    {
        $this->getJson('/api/v1/references/units')->assertUnauthorized();
    }

    public function test_unit_upstream_failure_is_returned_as_safe_gateway_error(): void
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
            'https://sikkepo.test/api/v1/data/unit*' => Http::response([], 503),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/references/units')
            ->assertStatus(502)
            ->assertJsonPath('code', 'sikkepo_unavailable');
    }
}
