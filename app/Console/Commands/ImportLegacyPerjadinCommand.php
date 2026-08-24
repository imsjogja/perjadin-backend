<?php

namespace App\Console\Commands;

use App\Services\LegacyPerjadinImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyPerjadinCommand extends Command
{
    protected $signature = 'perjadin:import-legacy
                            {--dry-run : Validasi sumber dan target tanpa menyimpan data}
                            {--limit= : Batasi jumlah SPT dan SPPD yang diproses}';

    protected $description = 'Impor data Perjadin CodeIgniter dari koneksi legacy yang hanya-baca';

    public function handle(LegacyPerjadinImportService $importer): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('Opsi --limit harus berupa bilangan bulat positif.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $report = $importer->import($dryRun, $limit ? (int) $limit : null);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Referensi', $report['references']],
                ['SPT diimpor', $report['spts_imported']],
                ['SPT dilewati', $report['spts_skipped']],
                ['SPPD diimpor', $report['sppds_imported']],
                ['SPPD dilewati', $report['sppds_skipped']],
                ['Karantina', $report['quarantined']],
                ['Batch', $report['batch_id'] ?: '-'],
            ]
        );

        $this->info($dryRun
            ? 'Dry-run selesai tanpa menulis data target.'
            : 'Import selesai. Tinjau tabel legacy_import_issues sebelum cutover.');

        return self::SUCCESS;
    }
}
