<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EmployeeSnapshotFactory
{
    public function __construct(private readonly SikkepoPlatformClient $client) {}

    /**
     * Resolve an active employee and normalize the immutable transaction
     * snapshot. Perjadin never persists a local employee master record.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function fromNip(string $nip, string $field = 'nip'): array
    {
        $pegawai = $this->client->pegawaiByNip($nip);

        if (! $pegawai || ($pegawai['aktif'] ?? false) !== true) {
            throw ValidationException::withMessages([
                $field => 'Pegawai tidak ditemukan, tidak aktif, atau berada di luar scope SIKKEPO.',
            ]);
        }

        $pegawaiId = $pegawai['pegawai_id'] ?? null;

        if (! is_string($pegawaiId) || $pegawaiId === '') {
            throw ValidationException::withMessages([
                $field => 'Data pegawai dari SIKKEPO tidak lengkap.',
            ]);
        }

        return [
            'pegawai_id' => $pegawaiId,
            'nip' => (string) $pegawai['nip'],
            'nama' => (string) ($pegawai['nama'] ?? ''),
            'tipe' => $pegawai['tipe'] ?? null,
            'unit' => $pegawai['unit'] ?? null,
            'jabatan' => $pegawai['jabatan'] ?? null,
            'golongan' => $pegawai['golongan'] ?? null,
            'eselon' => $pegawai['eselon'] ?? null,
            'kelas_jabatan' => $pegawai['kelas_jabatan'] ?? null,
            'updated_at' => $pegawai['updated_at'] ?? null,
        ];
    }
}
