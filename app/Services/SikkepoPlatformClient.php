<?php

namespace App\Services;

use App\Exceptions\SikkepoPlatformException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SikkepoPlatformClient
{
    private const PEGAWAI_FILTERS = [
        'q',
        'nip',
        'unit_id',
        'aktif',
        'updated_since',
        'sort',
        'direction',
        'per_page',
        'page',
    ];

    /**
     * Retrieve the scoped employee reference list from SIKKEPO Platform API.
     *
     * @return array<string, mixed>
     */
    public function pegawai(array $filters = []): array
    {
        $query = array_filter(
            Arr::only($filters, self::PEGAWAI_FILTERS),
            static fn ($value): bool => $value !== null && $value !== ''
        );

        $response = $this->request()
            ->withToken($this->accessToken())
            ->get('/api/v1/platform/pegawai', $query);

        if ($response->failed()) {
            throw new SikkepoPlatformException(
                'SIKKEPO Platform API gagal mengambil referensi pegawai.',
                $response->status()
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SikkepoPlatformException('Respons SIKKEPO Platform API tidak valid.');
        }

        return $payload;
    }

    private function accessToken(): string
    {
        $clientId = config('sikkepo.platform_client_id');
        $clientSecret = config('sikkepo.platform_client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new SikkepoPlatformException('Kredensial SIKKEPO Platform API belum dikonfigurasi.');
        }

        $ttl = max(
            30,
            (int) config('sikkepo.token_ttl_seconds', 300)
                - (int) config('sikkepo.token_refresh_margin_seconds', 30)
        );

        return Cache::remember(config('sikkepo.token_cache_key'), now()->addSeconds($ttl), function () use ($clientId, $clientSecret): string {
            $response = $this->request()->post('/api/v1/platform/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            $token = $response->json('access_token');

            if ($response->failed() || ! is_string($token) || $token === '') {
                throw new SikkepoPlatformException(
                    'SIKKEPO Platform API menolak penerbitan token.',
                    $response->status()
                );
            }

            return $token;
        });
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('sikkepo.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('sikkepo.timeout', 10))
            ->connectTimeout((int) config('sikkepo.connect_timeout', 3))
            ->retry(
                (int) config('sikkepo.retry_times', 1),
                (int) config('sikkepo.retry_sleep_ms', 150),
                throw: false
            );
    }
}
