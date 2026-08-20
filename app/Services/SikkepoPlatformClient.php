<?php

namespace App\Services;

use App\Exceptions\SikkepoPlatformException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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

    private const UNIT_FILTERS = [
        'q',
        'parent_id',
        'recursive',
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

        $response = $this->platformGet('/api/v1/platform/pegawai', $query);

        if (! $response->successful()) {
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

    /**
     * Retrieve the scoped work-unit reference list from SIKKEPO Platform API.
     *
     * @return array<string, mixed>
     */
    public function units(array $filters = []): array
    {
        $query = array_filter(
            Arr::only($filters, self::UNIT_FILTERS),
            static fn ($value): bool => $value !== null && $value !== ''
        );

        $response = $this->platformGet('/api/v1/data/unit', $query);

        if (! $response->successful()) {
            throw new SikkepoPlatformException(
                'SIKKEPO Platform API gagal mengambil referensi unit.',
                $response->status()
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SikkepoPlatformException('Respons SIKKEPO Platform API tidak valid.');
        }

        return $payload;
    }

    /**
     * Find one employee by NIP within the caller's SIKKEPO unit scope.
     *
     * The Platform API intentionally has no direct ID filter. The returned
     * NIP is checked again so an unexpected upstream payload is never used
     * as a transaction snapshot.
     *
     * @return array<string, mixed>|null
     */
    public function pegawaiByNip(string $nip): ?array
    {
        $payload = $this->pegawai([
            'nip' => $nip,
            'per_page' => 1,
        ]);

        $pegawai = $payload['data'][0] ?? null;

        if (! is_array($pegawai) || ($pegawai['nip'] ?? null) !== $nip) {
            return null;
        }

        return $pegawai;
    }

    /**
     * Send one authenticated Platform API request. A 401 normally means the
     * cached bearer token was revoked or expired before its cache TTL, so it
     * is replaced once and the original request is repeated.
     *
     * @param  array<string, mixed>  $query
     */
    private function platformGet(string $uri, array $query): Response
    {
        $response = $this->request()
            ->withToken($this->accessToken())
            ->get($uri, $query);

        if ($response->status() !== 401) {
            return $response;
        }

        Cache::forget((string) config('sikkepo.token_cache_key'));

        return $this->request()
            ->withToken($this->accessToken())
            ->get($uri, $query);
    }

    private function accessToken(): string
    {
        $clientId = config('sikkepo.platform_client_id');
        $clientSecret = config('sikkepo.platform_client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new SikkepoPlatformException('Kredensial SIKKEPO Platform API belum dikonfigurasi.');
        }

        $cacheKey = (string) config('sikkepo.token_cache_key');
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $response = $this->request()->post('/api/v1/platform/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new SikkepoPlatformException(
                'SIKKEPO Platform API menolak penerbitan token.',
                $response->status()
            );
        }

        $expiresIn = (int) $response->json(
            'expires_in',
            (int) config('sikkepo.token_ttl_seconds', 300)
        );
        $ttl = max(
            30,
            $expiresIn - (int) config('sikkepo.token_refresh_margin_seconds', 30)
        );

        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('sikkepo.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withoutRedirecting()
            ->timeout((int) config('sikkepo.timeout', 20))
            ->connectTimeout((int) config('sikkepo.connect_timeout', 10))
            ->retry(
                (int) config('sikkepo.retry_times', 2),
                (int) config('sikkepo.retry_sleep_ms', 300),
                static function (\Exception $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && $exception->response->serverError();
                },
                throw: false
            );
    }
}
