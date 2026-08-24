<?php

namespace App\Services;

use App\Exceptions\SikkepoPlatformException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyPerjadinMappingService
{
    private readonly Connection $legacy;

    private readonly string $sourceDatabase;

    public function __construct(private readonly SikkepoPlatformClient $sikkepo)
    {
        $connection = (string) config('perjadin.legacy_import.connection', 'legacy');
        $this->legacy = DB::connection($connection);
        $this->sourceDatabase = (string) config("database.connections.{$connection}.database");
    }

    /**
     * @return array<string, int>
     */
    public function prepare(bool $dryRun = true, bool $employees = true, bool $units = true): array
    {
        $report = [
            'employees_mapped' => 0,
            'employees_unresolved' => 0,
            'employees_upstream_failed' => 0,
            'units_mapped' => 0,
            'units_unresolved' => 0,
            'units_upstream_failed' => 0,
        ];

        if ($employees) {
            $this->prepareEmployees($dryRun, $report);
        }

        if ($units) {
            $this->prepareUnits($dryRun, $report);
        }

        return $report;
    }

    /**
     * @param  array<string, int>  $report
     */
    private function prepareEmployees(bool $dryRun, array &$report): void
    {
        $employeeIds = $this->legacy->table('perjadin_spt_pejabat')
            ->select('id_pegawai')
            ->union($this->legacy->table('perjadin_sppd')->select('id_pegawai'))
            ->union($this->legacy->table('perjadin_sppd_pejabat')->select('id_pegawai'))
            ->union($this->legacy->table('perjadin_sppd_pengikut')->select('id_pegawai'))
            ->pluck('id_pegawai')
            ->unique()
            ->values();

        foreach ($employeeIds as $legacyEmployeeId) {
            $legacyEmployee = $this->legacy->table('pegawai')
                ->where('id', $legacyEmployeeId)
                ->first(['id', 'nip']);

            if (! $legacyEmployee || trim((string) $legacyEmployee->nip) === '') {
                $report['employees_unresolved']++;

                continue;
            }

            $this->pauseBetweenLookups();

            try {
                $payload = $this->sikkepo->pegawai([
                    'nip' => (string) $legacyEmployee->nip,
                    'per_page' => 1,
                ]);
            } catch (SikkepoPlatformException $exception) {
                report($exception);
                $report['employees_upstream_failed']++;

                continue;
            }

            $employee = $payload['data'][0] ?? null;
            $pegawaiId = is_array($employee) ? $employee['pegawai_id'] ?? null : null;

            if (! is_array($employee)
                || ($employee['nip'] ?? null) !== $legacyEmployee->nip
                || ! is_string($pegawaiId)
                || ! Str::isUuid($pegawaiId)) {
                $report['employees_unresolved']++;

                continue;
            }

            if (! $dryRun) {
                DB::table('legacy_employee_mappings')->updateOrInsert([
                    'source_database' => $this->sourceDatabase,
                    'legacy_employee_id' => $legacyEmployee->id,
                ], [
                    'nip' => $legacyEmployee->nip,
                    'sikkepo_pegawai_id' => $pegawaiId,
                    'employee_snapshot' => json_encode($employee, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            $report['employees_mapped']++;
        }
    }

    /**
     * @param  array<string, int>  $report
     */
    private function prepareUnits(bool $dryRun, array &$report): void
    {
        $units = $this->legacy->table('perjadin_spt as spt')
            ->join('ref_unit as unit', 'unit.id', '=', 'spt.id_unit')
            ->distinct()
            ->orderBy('unit.id')
            ->get(['unit.id', 'unit.kode', 'unit.unit']);

        foreach ($units as $legacyUnit) {
            $this->pauseBetweenLookups();

            try {
                $payload = $this->sikkepo->units([
                    'q' => (string) $legacyUnit->unit,
                    'per_page' => 100,
                ]);
            } catch (SikkepoPlatformException $exception) {
                report($exception);
                $report['units_upstream_failed']++;

                continue;
            }

            $matches = collect($payload['data'] ?? [])
                ->filter(function ($unit) use ($legacyUnit): bool {
                    if (! is_array($unit) || ! is_string($unit['id'] ?? null)) {
                        return false;
                    }

                    $sameName = mb_strtolower(trim((string) ($unit['nama'] ?? '')))
                        === mb_strtolower(trim((string) $legacyUnit->unit));
                    $sameCode = trim((string) $legacyUnit->kode) !== ''
                        && trim((string) ($unit['kode'] ?? '')) === trim((string) $legacyUnit->kode);

                    return ($sameName || $sameCode) && Str::isUuid($unit['id']);
                })
                ->values();

            if ($matches->count() !== 1) {
                $report['units_unresolved']++;

                continue;
            }

            $unit = $matches->first();

            if (! $dryRun) {
                DB::table('legacy_unit_mappings')->updateOrInsert([
                    'source_database' => $this->sourceDatabase,
                    'legacy_unit_id' => $legacyUnit->id,
                ], [
                    'sikkepo_unit_id' => $unit['id'],
                    'unit_snapshot' => json_encode($unit, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            $report['units_mapped']++;
        }
    }

    private function pauseBetweenLookups(): void
    {
        $delay = max(0, (int) config('perjadin.legacy_import.mapping_delay_ms', 1000));

        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }
}
