<?php

namespace App\Console\Commands;

use App\Services\LegacyPerjadinMappingService;
use Illuminate\Console\Command;
use Throwable;

class PrepareLegacyPerjadinMappingsCommand extends Command
{
    protected $signature = 'perjadin:prepare-legacy-mappings
                            {--dry-run : Cocokkan data tanpa menyimpan mapping}
                            {--employees : Proses hanya mapping pegawai}
                            {--units : Proses hanya mapping unit}
                            {--unmapped-only : Lewati pegawai yang sudah memiliki mapping}
                            {--valid-nips-only : Proses hanya NIP yang bukan kosong atau placeholder}';

    protected $description = 'Siapkan mapping pegawai dan unit legacy terhadap referensi SIKKEPO';

    public function handle(LegacyPerjadinMappingService $mappings): int
    {
        $employees = (bool) $this->option('employees');
        $units = (bool) $this->option('units');

        if (! $employees && ! $units) {
            $employees = true;
            $units = true;
        }

        try {
            $report = $mappings->prepare(
                dryRun: (bool) $this->option('dry-run'),
                employees: $employees,
                units: $units,
                unmappedOnly: (bool) $this->option('unmapped-only'),
                validNipsOnly: (bool) $this->option('valid-nips-only')
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Pegawai termapping', $report['employees_mapped']],
                ['Pegawai belum cocok', $report['employees_unresolved']],
                ['Gagal hubungi SIKKEPO (pegawai)', $report['employees_upstream_failed']],
                ['Unit termapping', $report['units_mapped']],
                ['Unit belum cocok', $report['units_unresolved']],
                ['Gagal hubungi SIKKEPO (unit)', $report['units_upstream_failed']],
            ]
        );

        $this->info($this->option('dry-run')
            ? 'Dry-run selesai tanpa menyimpan mapping.'
            : 'Mapping tersimpan. Tinjau baris yang belum cocok sebelum menjalankan import.');

        return self::SUCCESS;
    }
}
