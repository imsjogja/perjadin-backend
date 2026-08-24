<?php

namespace App\Services;

use App\Models\DocumentReference;
use App\Models\DocumentSequence;
use App\Models\LegacyImportBatch;
use App\Models\LegacyImportRecord;
use App\Models\Sppd;
use App\Models\Spt;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyPerjadinImportService
{
    private readonly Connection $legacy;

    private readonly string $sourceConnection;

    private readonly string $sourceDatabase;

    /** @var array<int, array<string, mixed>|null> */
    private array $employeeSnapshots = [];

    /** @var array<int, string|null> */
    private array $unitMappings = [];

    public function __construct()
    {
        $this->sourceConnection = (string) config('perjadin.legacy_import.connection', 'legacy');
        $this->sourceDatabase = (string) config(
            "database.connections.{$this->sourceConnection}.database"
        );
        $this->legacy = DB::connection($this->sourceConnection);
    }

    /**
     * @return array<string, int|string>
     */
    public function import(bool $dryRun = true, ?int $limit = null): array
    {
        $report = [
            'references' => 0,
            'spts_imported' => 0,
            'spts_skipped' => 0,
            'sppds_imported' => 0,
            'sppds_skipped' => 0,
            'quarantined' => 0,
            'batch_id' => '',
        ];
        $batch = null;

        try {
            $this->assertSourceTables();

            if (! $dryRun) {
                $batch = LegacyImportBatch::query()->create([
                    'source_connection' => $this->sourceConnection,
                    'source_database' => $this->sourceDatabase,
                    'dry_run' => false,
                    'status' => 'running',
                    'started_at' => now(),
                ]);
                $report['batch_id'] = $batch->getKey();
            }

            $this->importReferences($dryRun, $report);
            $this->importSpts($batch, $dryRun, $limit, $report);
            $this->importSppds($batch, $dryRun, $limit, $report);

            if (! $dryRun) {
                $this->synchronizeDocumentSequences();
                $batch->forceFill([
                    'status' => 'completed',
                    'summary' => $report,
                    'completed_at' => now(),
                ])->save();
            }
        } catch (Throwable $exception) {
            if ($batch) {
                $batch->forceFill([
                    'status' => 'failed',
                    'summary' => $report,
                    'completed_at' => now(),
                ])->save();
            }

            throw $exception;
        }

        return $report;
    }

    private function assertSourceTables(): void
    {
        foreach ([
            'pegawai',
            'perjadin_spt',
            'perjadin_spt_pejabat',
            'perjadin_spt_tujuan',
            'perjadin_sppd',
            'perjadin_sppd_pejabat',
            'perjadin_sppd_pengikut',
            'ref_transportasi',
        ] as $table) {
            if (! $this->legacy->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException("Tabel legacy {$table} tidak tersedia.");
            }
        }
    }

    /**
     * @param  array<string, int|string>  $report
     */
    private function importReferences(bool $dryRun, array &$report): void
    {
        $references = [];

        foreach ($this->legacy->table('ref_transportasi')->orderBy('id')->pluck('transportasi') as $value) {
            $references[] = [DocumentReference::CATEGORY_TRANSPORTATION, $value];
        }

        foreach ($this->legacy->table('perjadin_sppd')->distinct()->pluck('pj_tingkat') as $value) {
            $references[] = [DocumentReference::CATEGORY_TRAVEL_LEVEL, $value];
        }

        foreach ($this->legacy->table('perjadin_sppd')->distinct()->pluck('pj_jenis') as $value) {
            $references[] = [DocumentReference::CATEGORY_TRAVEL_TYPE, $this->travelType($value)];
        }

        foreach ($this->legacy->table('perjadin_sppd')->distinct()->pluck('ba_mata_anggaran') as $value) {
            $references[] = [DocumentReference::CATEGORY_BUDGET_ACCOUNT, $value];
        }

        foreach ($references as [$category, $value]) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            if (! $dryRun) {
                DocumentReference::query()->updateOrCreate([
                    'category' => $category,
                    'value' => $value,
                ]);
            }

            $report['references']++;
        }
    }

    /**
     * @param  array<string, int|string>  $report
     */
    private function importSpts(
        ?LegacyImportBatch $batch,
        bool $dryRun,
        ?int $limit,
        array &$report
    ): void {
        $query = $this->legacy->table('perjadin_spt')->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        foreach ($query->get() as $legacySpt) {
            $sourceId = (int) $legacySpt->id;
            $destination = $this->legacy->table('perjadin_spt_tujuan as destination')
                ->join(
                    'ref_transportasi as transportation',
                    'transportation.id',
                    '=',
                    'destination.id_transportasi'
                )
                ->where('destination.id_spt', $sourceId)
                ->select([
                    'destination.tempat_berangkat',
                    'destination.tempat_tujuan',
                    'destination.lamanya',
                    'transportation.transportasi',
                ])
                ->first();
            $legacySignatory = $this->legacy->table('perjadin_spt_pejabat')
                ->where('id_spt', $sourceId)
                ->first();
            $checksum = $this->checksum([
                'spt' => (array) $legacySpt,
                'destination' => (array) $destination,
                'signatory' => (array) $legacySignatory,
            ]);

            if ($this->alreadyImported('perjadin_spt', $sourceId, $checksum)) {
                $report['spts_skipped']++;

                continue;
            }

            $unitId = $this->mappedUnitId((int) $legacySpt->id_unit);
            if (! $unitId) {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'spt',
                    'perjadin_spt',
                    $sourceId,
                    $checksum,
                    'unit_not_mapped',
                    'Unit SPT legacy belum memiliki mapping ke SIKKEPO.'
                );
                $report['quarantined']++;

                continue;
            }

            if (! $destination) {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'spt',
                    'perjadin_spt',
                    $sourceId,
                    $checksum,
                    'destination_not_found',
                    'Tujuan SPT legacy tidak ditemukan.'
                );
                $report['quarantined']++;

                continue;
            }

            $signatory = $legacySignatory
                ? $this->employeeSnapshot((int) $legacySignatory->id_pegawai)
                : null;
            if (! $signatory) {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'spt',
                    'perjadin_spt',
                    $sourceId,
                    $checksum,
                    'signatory_not_mapped',
                    'Penandatangan SPT legacy belum memiliki mapping pegawai SIKKEPO.'
                );
                $report['quarantined']++;

                continue;
            }

            $issuedDate = $this->dateValue($legacySpt->tanggal);
            $registrationNumber = trim((string) $legacySpt->no_registrasi);
            $documentNumber = trim((string) ($legacySpt->no_spt_text ?: $legacySpt->no_spt));
            $sequenceNumber = $this->sequenceNumber($registrationNumber);

            if (! $issuedDate || ! $sequenceNumber || $documentNumber === '') {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'spt',
                    'perjadin_spt',
                    $sourceId,
                    $checksum,
                    'invalid_document_metadata',
                    'Tanggal, registrasi, atau nomor SPT legacy tidak valid.'
                );
                $report['quarantined']++;

                continue;
            }

            if ($this->hasSptConflict($issuedDate->year, $registrationNumber, $documentNumber)) {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'spt',
                    'perjadin_spt',
                    $sourceId,
                    $checksum,
                    'document_number_conflict',
                    'Nomor registrasi atau nomor SPT sudah digunakan di database target.'
                );
                $report['quarantined']++;

                continue;
            }

            if ($dryRun) {
                $report['spts_imported']++;

                continue;
            }

            DB::transaction(function () use (
                $batch,
                $checksum,
                $destination,
                $documentNumber,
                $issuedDate,
                $legacySignatory,
                $legacySpt,
                $registrationNumber,
                $sequenceNumber,
                $signatory,
                $sourceId,
                $unitId
            ): void {
                $recordedAt = $this->timestampValue($legacySpt->record);
                $spt = new Spt;
                $spt->forceFill([
                    'unit_id' => $unitId,
                    'document_year' => $issuedDate->year,
                    'sequence_number' => $sequenceNumber,
                    'registration_number' => $registrationNumber,
                    'document_number' => $documentNumber,
                    'dasar' => trim((string) $legacySpt->dasar) ?: '-',
                    'disposisi' => $this->nullableString($legacySpt->disposisi),
                    'dalam_rangka' => trim((string) $legacySpt->dalam_rangka) ?: '-',
                    'issued_place' => trim((string) $legacySpt->tempat_dikeluarkan) ?: '-',
                    'issued_date' => $issuedDate,
                    'assignment_revision' => 0,
                    'created_at' => $recordedAt,
                    'updated_at' => $recordedAt,
                ])->save();

                $spt->destination()->create([
                    'transportation' => trim((string) $destination->transportasi),
                    'departure_place' => trim((string) $destination->tempat_berangkat),
                    'destination_place' => trim((string) $destination->tempat_tujuan),
                    'duration_days' => max(1, (int) $destination->lamanya),
                ]);
                $spt->signatory()->create([
                    'sikkepo_pegawai_id' => $signatory['pegawai_id'],
                    'employee_snapshot' => $signatory,
                    'behalf_of' => $this->nullableString($legacySignatory->atas_nama),
                    'signatory_role' => $this->signatoryRole($legacySignatory),
                    'is_acting' => $this->isActing($legacySignatory),
                ]);
                $spt->bases()->create([
                    'content' => trim((string) $legacySpt->dasar) ?: '-',
                    'sort_order' => 1,
                ]);

                $this->storeRecord(
                    $batch,
                    'perjadin_spt',
                    $sourceId,
                    $checksum,
                    'spts',
                    $spt->getKey()
                );
            }, 3);

            $report['spts_imported']++;
        }
    }

    /**
     * @param  array<string, int|string>  $report
     */
    private function importSppds(
        ?LegacyImportBatch $batch,
        bool $dryRun,
        ?int $limit,
        array &$report
    ): void {
        $query = $this->legacy->table('perjadin_sppd')->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        foreach ($query->get() as $legacySppd) {
            $sourceId = (int) $legacySppd->id;
            $followers = $this->legacy->table('perjadin_sppd_pengikut')
                ->where('id_sppd', $sourceId)
                ->orderBy('id')
                ->get();
            $legacySignatory = $this->legacy->table('perjadin_sppd_pejabat')
                ->where('id_sppd', $sourceId)
                ->first();
            $checksum = $this->checksum([
                'sppd' => (array) $legacySppd,
                'followers' => $followers->map(static fn ($follower) => (array) $follower)->all(),
                'signatory' => (array) $legacySignatory,
            ]);

            if ($this->alreadyImported('perjadin_sppd', $sourceId, $checksum)) {
                $report['sppds_skipped']++;

                continue;
            }

            $targetSptId = $this->targetIdFor('perjadin_spt', (int) $legacySppd->id_spt);
            if (! $targetSptId) {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'sppd',
                    'perjadin_sppd',
                    $sourceId,
                    $checksum,
                    'parent_spt_not_imported',
                    'SPT induk belum dapat diimpor.'
                );
                $report['quarantined']++;

                continue;
            }

            $traveller = $this->employeeSnapshot((int) $legacySppd->id_pegawai);
            $signatory = $legacySignatory
                ? $this->employeeSnapshot((int) $legacySignatory->id_pegawai)
                : null;
            if (! $traveller || ! $signatory) {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'sppd',
                    'perjadin_sppd',
                    $sourceId,
                    $checksum,
                    'employee_not_mapped',
                    'Pelaksana atau penandatangan SPPD belum memiliki mapping pegawai SIKKEPO.'
                );
                $report['quarantined']++;

                continue;
            }

            $followerSnapshots = [];
            foreach ($followers as $follower) {
                $snapshot = $this->employeeSnapshot((int) $follower->id_pegawai);

                if (! $snapshot) {
                    $this->quarantine(
                        $batch,
                        $dryRun,
                        'sppd',
                        'perjadin_sppd',
                        $sourceId,
                        $checksum,
                        'follower_not_mapped',
                        'Salah satu pengikut SPPD belum memiliki mapping pegawai SIKKEPO.'
                    );
                    $report['quarantined']++;

                    continue 2;
                }

                $followerSnapshots[] = $snapshot;
            }

            $issuedDate = $this->dateValue($legacySppd->tanggal);
            $departureDate = $this->dateValue($legacySppd->pj_tgl_berangkat);
            $returnDate = $this->dateValue($legacySppd->pj_tgl_kembali);
            $registrationNumber = trim((string) $legacySppd->no_registrasi);
            $documentNumber = trim((string) ($legacySppd->no_sppd_text ?: $legacySppd->no_sppd));
            $sequenceNumber = $this->sequenceNumber($registrationNumber);

            if (! $issuedDate || ! $departureDate || ! $returnDate || ! $sequenceNumber || $documentNumber === '') {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'sppd',
                    'perjadin_sppd',
                    $sourceId,
                    $checksum,
                    'invalid_document_metadata',
                    'Tanggal, registrasi, atau nomor SPPD legacy tidak valid.'
                );
                $report['quarantined']++;

                continue;
            }

            if ($this->hasSppdConflict($issuedDate->year, $registrationNumber, $documentNumber)) {
                $this->quarantine(
                    $batch,
                    $dryRun,
                    'sppd',
                    'perjadin_sppd',
                    $sourceId,
                    $checksum,
                    'document_number_conflict',
                    'Nomor registrasi atau nomor SPPD sudah digunakan di database target.'
                );
                $report['quarantined']++;

                continue;
            }

            if ($dryRun) {
                $report['sppds_imported']++;

                continue;
            }

            DB::transaction(function () use (
                $batch,
                $checksum,
                $departureDate,
                $documentNumber,
                $followerSnapshots,
                $issuedDate,
                $legacySignatory,
                $legacySppd,
                $registrationNumber,
                $returnDate,
                $sequenceNumber,
                $signatory,
                $sourceId,
                $targetSptId,
                $traveller
            ): void {
                $spt = Spt::query()->lockForUpdate()->findOrFail($targetSptId);
                $recordedAt = $this->timestampValue($legacySppd->record);

                $this->ensureAssignee($spt, $traveller, $recordedAt);
                foreach ($followerSnapshots as $follower) {
                    $this->ensureAssignee($spt, $follower, $recordedAt);
                }

                $sppd = new Sppd;
                $sppd->forceFill([
                    'spt_id' => $spt->getKey(),
                    'unit_id' => $spt->unit_id,
                    'sikkepo_pegawai_id' => $traveller['pegawai_id'],
                    'employee_snapshot' => $traveller,
                    'document_year' => $issuedDate->year,
                    'sequence_number' => $sequenceNumber,
                    'registration_number' => $registrationNumber,
                    'document_number' => $documentNumber,
                    'order_giver' => trim((string) $legacySppd->pemberi_perintah) ?: '-',
                    'travel_level' => $this->nullableString($legacySppd->pj_tingkat),
                    'travel_type' => $this->nullableString($this->travelType($legacySppd->pj_jenis)),
                    'departure_date' => $departureDate,
                    'return_date' => $returnDate,
                    'budget_agency' => trim((string) $legacySppd->ba_instansi) ?: '-',
                    'budget_account' => $this->nullableString($legacySppd->ba_mata_anggaran),
                    'description' => $this->nullableString($legacySppd->keterangan),
                    'issued_place' => trim((string) $legacySppd->tempat_dikeluarkan) ?: '-',
                    'issued_date' => $issuedDate,
                    'status' => (string) $legacySppd->verifikasi === '1'
                        ? Sppd::STATUS_VERIFIED
                        : Sppd::STATUS_DRAFT,
                    'created_at' => $recordedAt,
                    'updated_at' => $recordedAt,
                ])->save();

                foreach ($followerSnapshots as $follower) {
                    if ($follower['pegawai_id'] === $traveller['pegawai_id']) {
                        continue;
                    }

                    $sppd->followers()->create([
                        'sikkepo_pegawai_id' => $follower['pegawai_id'],
                        'employee_snapshot' => $follower,
                    ]);
                }

                $sppd->signatory()->create([
                    'sikkepo_pegawai_id' => $signatory['pegawai_id'],
                    'employee_snapshot' => $signatory,
                    'behalf_of' => $this->nullableString($legacySignatory->atas_nama),
                    'signatory_role' => $this->signatoryRole($legacySignatory),
                    'is_acting' => $this->isActing($legacySignatory),
                ]);

                $this->storeRecord(
                    $batch,
                    'perjadin_sppd',
                    $sourceId,
                    $checksum,
                    'sppds',
                    $sppd->getKey()
                );
            }, 3);

            $report['sppds_imported']++;
        }
    }

    private function ensureAssignee(Spt $spt, array $snapshot, Carbon $assignedAt): void
    {
        $created = $spt->assignees()->firstOrCreate(
            ['sikkepo_pegawai_id' => $snapshot['pegawai_id']],
            [
                'employee_snapshot' => $snapshot,
                'assignment_revision' => 1,
                'assigned_at' => $assignedAt,
            ]
        );

        if ($created->wasRecentlyCreated && $spt->assignment_revision === 0) {
            $spt->forceFill([
                'assignment_revision' => 1,
                'assignment_updated_at' => $assignedAt,
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function employeeSnapshot(int $legacyEmployeeId): ?array
    {
        if (array_key_exists($legacyEmployeeId, $this->employeeSnapshots)) {
            return $this->employeeSnapshots[$legacyEmployeeId];
        }

        $mapping = DB::table('legacy_employee_mappings')
            ->where('source_database', $this->sourceDatabase)
            ->where('legacy_employee_id', $legacyEmployeeId)
            ->first();
        $legacyEmployee = $this->legacy->table('pegawai as employee')
            ->leftJoin('ref_unit as unit', 'unit.id', '=', 'employee.id_unit')
            ->leftJoin('ref_jabatan as position', 'position.id', '=', 'employee.id_jabatan')
            ->leftJoin('ref_golru as rank', 'rank.id', '=', 'employee.id_golru')
            ->leftJoin('ref_eselon as echelon', 'echelon.id', '=', 'employee.id_eselon')
            ->where('employee.id', $legacyEmployeeId)
            ->select([
                'employee.id',
                'employee.nip',
                'employee.nama',
                'employee.record',
                'unit.unit',
                'position.jabatan',
                'rank.golongan',
                'rank.pangkat',
                'echelon.eselon',
            ])
            ->first();

        if (! $mapping || ! $legacyEmployee) {
            return $this->employeeSnapshots[$legacyEmployeeId] = null;
        }

        $mappedSnapshot = $mapping->employee_snapshot
            ? json_decode((string) $mapping->employee_snapshot, true)
            : [];

        return $this->employeeSnapshots[$legacyEmployeeId] = [
            'pegawai_id' => (string) $mapping->sikkepo_pegawai_id,
            'nip' => (string) $legacyEmployee->nip,
            'nama' => (string) $legacyEmployee->nama,
            'tipe' => data_get($mappedSnapshot, 'tipe'),
            'unit' => [
                'id' => data_get($mappedSnapshot, 'unit.id'),
                'nama' => $legacyEmployee->unit,
            ],
            'jabatan' => [
                'id' => data_get($mappedSnapshot, 'jabatan.id'),
                'nama' => $legacyEmployee->jabatan,
            ],
            'golongan' => [
                'id' => data_get($mappedSnapshot, 'golongan.id'),
                'nama' => $legacyEmployee->golongan,
                'pangkat' => $legacyEmployee->pangkat,
            ],
            'eselon' => [
                'id' => data_get($mappedSnapshot, 'eselon.id'),
                'nama' => $legacyEmployee->eselon,
            ],
            'kelas_jabatan' => data_get($mappedSnapshot, 'kelas_jabatan'),
            'updated_at' => $legacyEmployee->record,
        ];
    }

    private function mappedUnitId(int $legacyUnitId): ?string
    {
        if (array_key_exists($legacyUnitId, $this->unitMappings)) {
            return $this->unitMappings[$legacyUnitId];
        }

        return $this->unitMappings[$legacyUnitId] = DB::table('legacy_unit_mappings')
            ->where('source_database', $this->sourceDatabase)
            ->where('legacy_unit_id', $legacyUnitId)
            ->value('sikkepo_unit_id');
    }

    private function alreadyImported(string $sourceTable, int $sourceId, string $checksum): bool
    {
        $record = LegacyImportRecord::query()
            ->where('source_database', $this->sourceDatabase)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->first();

        return $record?->status === LegacyImportRecord::STATUS_IMPORTED;
    }

    private function targetIdFor(string $sourceTable, int $sourceId): ?string
    {
        return LegacyImportRecord::query()
            ->where('source_database', $this->sourceDatabase)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', LegacyImportRecord::STATUS_IMPORTED)
            ->value('target_id');
    }

    private function hasSptConflict(int $year, string $registrationNumber, string $documentNumber): bool
    {
        return Spt::query()
            ->where('document_year', $year)
            ->where(function ($query) use ($registrationNumber, $documentNumber) {
                $query->where('registration_number', $registrationNumber)
                    ->orWhere('document_number', $documentNumber);
            })
            ->exists();
    }

    private function hasSppdConflict(int $year, string $registrationNumber, string $documentNumber): bool
    {
        return Sppd::query()
            ->where('document_year', $year)
            ->where(function ($query) use ($registrationNumber, $documentNumber) {
                $query->where('registration_number', $registrationNumber)
                    ->orWhere('document_number', $documentNumber);
            })
            ->exists();
    }

    private function storeRecord(
        LegacyImportBatch $batch,
        string $sourceTable,
        int $sourceId,
        string $checksum,
        string $targetTable,
        string $targetId
    ): void {
        LegacyImportRecord::query()->updateOrCreate([
            'source_database' => $this->sourceDatabase,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
        ], [
            'batch_id' => $batch->getKey(),
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'source_checksum' => $checksum,
            'status' => LegacyImportRecord::STATUS_IMPORTED,
            'message' => null,
        ]);
    }

    private function quarantine(
        ?LegacyImportBatch $batch,
        bool $dryRun,
        string $entityType,
        string $sourceTable,
        int $sourceId,
        string $checksum,
        string $code,
        string $message
    ): void {
        if ($dryRun || ! $batch) {
            return;
        }

        $record = LegacyImportRecord::query()->updateOrCreate([
            'source_database' => $this->sourceDatabase,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
        ], [
            'batch_id' => $batch->getKey(),
            'target_table' => null,
            'target_id' => null,
            'source_checksum' => $checksum,
            'status' => LegacyImportRecord::STATUS_QUARANTINED,
            'message' => $message,
        ]);

        DB::table('legacy_import_issues')->insert([
            'id' => (string) Str::uuid(),
            'batch_id' => $batch->getKey(),
            'record_id' => $record->getKey(),
            'entity_type' => $entityType,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'code' => $code,
            'message' => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function synchronizeDocumentSequences(): void
    {
        foreach ([
            ['spt', Spt::class],
            ['sppd', Sppd::class],
        ] as [$documentType, $model]) {
            foreach ($model::query()
                ->selectRaw('document_year, MAX(sequence_number) as last_number')
                ->groupBy('document_year')
                ->get() as $sequence) {
                DocumentSequence::query()->updateOrCreate([
                    'document_type' => $documentType,
                    'year' => $sequence->document_year,
                ], [
                    'last_number' => $sequence->last_number,
                ]);
            }
        }
    }

    private function checksum(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function dateValue(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    private function timestampValue(mixed $value): Carbon
    {
        $value = trim((string) $value);

        return $value === '' || $value === '0000-00-00 00:00:00'
            ? now()
            : Carbon::parse($value);
    }

    private function sequenceNumber(string $registrationNumber): ?int
    {
        return ctype_digit($registrationNumber) && (int) $registrationNumber > 0
            ? (int) $registrationNumber
            : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function travelType(mixed $value): string
    {
        return match (mb_strtolower(trim((string) $value))) {
            'dalam daerah', 'dalam kota' => 'Dalam Kota',
            'luar daerah', 'luar kota' => 'Luar Kota',
            default => trim((string) $value),
        };
    }

    private function signatoryRole(object $legacySignatory): ?string
    {
        return $this->nullableString($legacySignatory->pejabat);
    }

    private function isActing(object $legacySignatory): bool
    {
        return in_array(
            mb_strtoupper((string) ($legacySignatory->pejabat_sementara ?? '')),
            ['PLT', 'PLH'],
            true
        );
    }
}
