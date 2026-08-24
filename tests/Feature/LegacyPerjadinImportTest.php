<?php

namespace Tests\Feature;

use App\Models\LegacyImportRecord;
use App\Services\LegacyPerjadinImportService;
use App\Services\LegacyPerjadinMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyPerjadinImportTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = tempnam(sys_get_temp_dir(), 'perjadin-legacy-');
        config([
            'database.connections.legacy' => [
                'driver' => 'sqlite',
                'database' => $this->legacyDatabasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'perjadin.legacy_import.connection' => 'legacy',
        ]);
        DB::purge('legacy');

        $this->createLegacySchema();
        $this->seedLegacyDocument();
    }

    protected function tearDown(): void
    {
        DB::disconnect('legacy');

        if (isset($this->legacyDatabasePath) && file_exists($this->legacyDatabasePath)) {
            unlink($this->legacyDatabasePath);
        }

        parent::tearDown();
    }

    public function test_dry_run_quarantines_unmapped_rows_without_writing_target_data(): void
    {
        $report = app(LegacyPerjadinImportService::class)->import(dryRun: true);

        $this->assertSame(2, $report['quarantined']);
        $this->assertDatabaseCount('spts', 0);
        $this->assertDatabaseCount('sppds', 0);
        $this->assertDatabaseCount('legacy_import_records', 0);
    }

    public function test_import_creates_documents_from_mapped_legacy_data_and_is_idempotent(): void
    {
        $this->seedMappings();

        $firstReport = app(LegacyPerjadinImportService::class)->import(dryRun: false);

        $this->assertSame(1, $firstReport['spts_imported']);
        $this->assertSame(1, $firstReport['sppds_imported']);
        $this->assertSame(0, $firstReport['quarantined']);
        $this->assertDatabaseHas('spts', [
            'document_number' => '090.1/001/BKD/2024',
            'unit_id' => '20000000-0000-4000-8000-000000000010',
        ]);
        $this->assertDatabaseHas('sppds', [
            'document_number' => '094/001/BKD/2024',
            'status' => 'verified',
        ]);
        $this->assertDatabaseCount('spt_assignees', 1);
        $this->assertDatabaseCount('legacy_import_records', 2);

        $secondReport = app(LegacyPerjadinImportService::class)->import(dryRun: false);

        $this->assertSame(1, $secondReport['spts_skipped']);
        $this->assertSame(1, $secondReport['sppds_skipped']);
        $this->assertDatabaseCount('spts', 1);
        $this->assertDatabaseCount('sppds', 1);
        $this->assertDatabaseCount('legacy_import_records', 2);
        $this->assertSame(
            LegacyImportRecord::STATUS_IMPORTED,
            LegacyImportRecord::query()
                ->where('source_table', 'perjadin_sppd')
                ->value('status')
        );
    }

    public function test_dry_run_simulates_importable_parent_spts_for_sppds_without_writing(): void
    {
        $this->seedMappings();

        $report = app(LegacyPerjadinImportService::class)->import(dryRun: true);

        $this->assertSame(1, $report['spts_imported']);
        $this->assertSame(1, $report['sppds_imported']);
        $this->assertSame(0, $report['quarantined']);
        $this->assertDatabaseCount('spts', 0);
        $this->assertDatabaseCount('sppds', 0);
        $this->assertDatabaseCount('legacy_import_records', 0);
    }

    public function test_mapping_preparation_only_persists_exact_sikkepo_matches(): void
    {
        config([
            'sikkepo.base_url' => 'https://sikkepo.test',
            'sikkepo.platform_client_id' => 'perjadin',
            'sikkepo.platform_client_secret' => 'secret',
            'perjadin.legacy_import.mapping_delay_ms' => 0,
        ]);
        Cache::forget(config('sikkepo.token_cache_key'));

        Http::fake([
            'https://sikkepo.test/api/v1/platform/token' => Http::response([
                'access_token' => 'platform-token',
            ]),
            'https://sikkepo.test/api/v1/platform/pegawai*' => function (Request $request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $nip = $query['nip'] ?? '';
                $employeeId = $nip === '198001012010011001'
                    ? '10000000-0000-4000-8000-000000000001'
                    : '10000000-0000-4000-8000-000000000002';

                return Http::response([
                    'data' => [[
                        'pegawai_id' => $employeeId,
                        'nip' => $nip,
                        'nama' => 'Pegawai SIKKEPO',
                        'aktif' => true,
                    ]],
                ]);
            },
            'https://sikkepo.test/api/v1/data/unit*' => Http::response([
                'data' => [[
                    'id' => '20000000-0000-4000-8000-000000000010',
                    'kode' => 'BKD',
                    'nama' => 'Badan Kepegawaian Daerah',
                ]],
            ]),
        ]);

        $report = app(LegacyPerjadinMappingService::class)->prepare(dryRun: false);

        $this->assertSame(2, $report['employees_mapped']);
        $this->assertSame(0, $report['employees_unresolved']);
        $this->assertSame(0, $report['employees_upstream_failed']);
        $this->assertSame(1, $report['units_mapped']);
        $this->assertSame(0, $report['units_unresolved']);
        $this->assertSame(0, $report['units_upstream_failed']);
        $this->assertDatabaseCount('legacy_employee_mappings', 2);
        $this->assertDatabaseHas('legacy_unit_mappings', [
            'legacy_unit_id' => 10,
            'sikkepo_unit_id' => '20000000-0000-4000-8000-000000000010',
        ]);
    }

    public function test_mapping_preparation_can_limit_lookups_to_unmapped_valid_nips(): void
    {
        config([
            'sikkepo.base_url' => 'https://sikkepo.test',
            'sikkepo.platform_client_id' => 'perjadin',
            'sikkepo.platform_client_secret' => 'secret',
            'perjadin.legacy_import.mapping_delay_ms' => 0,
        ]);
        Cache::forget(config('sikkepo.token_cache_key'));

        DB::table('legacy_employee_mappings')->insert([
            'source_database' => $this->legacyDatabasePath,
            'legacy_employee_id' => 1,
            'nip' => '198001012010011001',
            'sikkepo_pegawai_id' => '10000000-0000-4000-8000-000000000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('legacy')->table('pegawai')->insert([
            'id' => 3,
            'nip' => '--',
            'nama' => 'NIP Placeholder',
            'record' => '2024-01-10 08:00:00',
            'id_jabatan' => 1,
            'id_golru' => 1,
            'id_eselon' => 1,
            'id_unit' => 10,
        ]);
        DB::connection('legacy')->table('perjadin_sppd_pengikut')->insert([
            'id' => 1,
            'id_sppd' => 1,
            'id_pegawai' => 3,
        ]);

        Http::fake([
            'https://sikkepo.test/api/v1/platform/token' => Http::response([
                'access_token' => 'platform-token',
            ]),
            'https://sikkepo.test/api/v1/platform/pegawai*' => Http::response([
                'data' => [[
                    'pegawai_id' => '10000000-0000-4000-8000-000000000002',
                    'nip' => '198001012010011002',
                    'nama' => 'Pegawai SIKKEPO',
                    'aktif' => true,
                ]],
            ]),
        ]);

        $report = app(LegacyPerjadinMappingService::class)->prepare(
            dryRun: false,
            employees: true,
            units: false,
            unmappedOnly: true,
            validNipsOnly: true
        );

        $this->assertSame(1, $report['employees_mapped']);
        $this->assertSame(0, $report['employees_unresolved']);
        $this->assertSame(0, $report['employees_upstream_failed']);
        $this->assertDatabaseCount('legacy_employee_mappings', 2);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://sikkepo.test/api/v1/platform/pegawai')
                && ($query['nip'] ?? null) === '198001012010011002';
        });
        Http::assertNotSent(function (Request $request): bool {
            return str_contains($request->url(), 'nip=198001012010011001')
                || str_contains($request->url(), 'nip=--');
        });
    }

    private function createLegacySchema(): void
    {
        Schema::connection('legacy')->create('pegawai', function ($table) {
            $table->integer('id')->primary();
            $table->string('nip');
            $table->string('nama');
            $table->timestamp('record')->nullable();
            $table->integer('id_jabatan')->nullable();
            $table->integer('id_golru')->nullable();
            $table->integer('id_eselon')->nullable();
            $table->integer('id_unit')->nullable();
        });
        Schema::connection('legacy')->create('ref_unit', function ($table) {
            $table->integer('id')->primary();
            $table->string('kode')->nullable();
            $table->string('unit');
        });
        Schema::connection('legacy')->create('ref_jabatan', function ($table) {
            $table->integer('id')->primary();
            $table->string('jabatan');
        });
        Schema::connection('legacy')->create('ref_golru', function ($table) {
            $table->integer('id')->primary();
            $table->string('golongan');
            $table->string('pangkat');
        });
        Schema::connection('legacy')->create('ref_eselon', function ($table) {
            $table->integer('id')->primary();
            $table->string('eselon');
        });
        Schema::connection('legacy')->create('ref_transportasi', function ($table) {
            $table->integer('id')->primary();
            $table->string('transportasi');
        });
        Schema::connection('legacy')->create('perjadin_spt', function ($table) {
            $table->integer('id')->primary();
            $table->string('no_registrasi');
            $table->string('no_spt');
            $table->string('dasar')->nullable();
            $table->string('disposisi')->nullable();
            $table->text('dalam_rangka')->nullable();
            $table->timestamp('record')->nullable();
            $table->string('no_spt_text');
            $table->string('tempat_dikeluarkan');
            $table->date('tanggal');
            $table->integer('id_unit');
        });
        Schema::connection('legacy')->create('perjadin_spt_tujuan', function ($table) {
            $table->integer('id')->primary();
            $table->integer('id_spt');
            $table->string('tempat_berangkat');
            $table->string('tempat_tujuan');
            $table->integer('id_transportasi');
            $table->integer('lamanya');
        });
        Schema::connection('legacy')->create('perjadin_spt_pejabat', function ($table) {
            $table->integer('id')->primary();
            $table->integer('id_spt');
            $table->integer('id_pegawai');
            $table->string('atas_nama')->nullable();
            $table->string('pejabat')->nullable();
            $table->string('pejabat_sementara')->nullable();
        });
        Schema::connection('legacy')->create('perjadin_sppd', function ($table) {
            $table->integer('id')->primary();
            $table->integer('id_spt');
            $table->integer('id_pegawai');
            $table->string('no_registrasi');
            $table->string('no_sppd');
            $table->string('pj_tingkat')->nullable();
            $table->string('pj_jenis')->nullable();
            $table->date('pj_tgl_berangkat')->nullable();
            $table->date('pj_tgl_kembali')->nullable();
            $table->string('ba_instansi')->nullable();
            $table->string('ba_mata_anggaran')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('verifikasi')->nullable();
            $table->timestamp('record')->nullable();
            $table->string('no_sppd_text');
            $table->string('pemberi_perintah');
            $table->string('tempat_dikeluarkan');
            $table->date('tanggal');
        });
        Schema::connection('legacy')->create('perjadin_sppd_pengikut', function ($table) {
            $table->integer('id')->primary();
            $table->integer('id_sppd');
            $table->integer('id_pegawai');
        });
        Schema::connection('legacy')->create('perjadin_sppd_pejabat', function ($table) {
            $table->integer('id')->primary();
            $table->integer('id_sppd');
            $table->integer('id_pegawai');
            $table->string('atas_nama')->nullable();
            $table->string('pejabat')->nullable();
            $table->string('pejabat_sementara')->nullable();
        });
    }

    private function seedLegacyDocument(): void
    {
        DB::connection('legacy')->table('ref_unit')->insert([
            'id' => 10,
            'kode' => 'BKD',
            'unit' => 'Badan Kepegawaian Daerah',
        ]);
        DB::connection('legacy')->table('ref_jabatan')->insert([
            'id' => 1,
            'jabatan' => 'Kepala BKD',
        ]);
        DB::connection('legacy')->table('ref_golru')->insert([
            'id' => 1,
            'golongan' => 'III/a',
            'pangkat' => 'Penata Muda',
        ]);
        DB::connection('legacy')->table('ref_eselon')->insert([
            'id' => 1,
            'eselon' => 'II.b',
        ]);
        DB::connection('legacy')->table('ref_transportasi')->insert([
            'id' => 1,
            'transportasi' => 'Pesawat',
        ]);
        DB::connection('legacy')->table('pegawai')->insert([
            [
                'id' => 1,
                'nip' => '198001012010011001',
                'nama' => 'Pejabat Penandatangan',
                'record' => '2024-01-10 08:00:00',
                'id_jabatan' => 1,
                'id_golru' => 1,
                'id_eselon' => 1,
                'id_unit' => 10,
            ],
            [
                'id' => 2,
                'nip' => '198001012010011002',
                'nama' => 'Pelaksana Perjalanan',
                'record' => '2024-01-10 08:00:00',
                'id_jabatan' => 1,
                'id_golru' => 1,
                'id_eselon' => 1,
                'id_unit' => 10,
            ],
        ]);
        DB::connection('legacy')->table('perjadin_spt')->insert([
            'id' => 1,
            'no_registrasi' => '00001',
            'no_spt' => '001',
            'dasar' => 'Surat undangan',
            'disposisi' => 'Segera',
            'dalam_rangka' => 'Koordinasi',
            'record' => '2024-01-10 08:00:00',
            'no_spt_text' => '090.1/001/BKD/2024',
            'tempat_dikeluarkan' => 'Manokwari',
            'tanggal' => '2024-01-10',
            'id_unit' => 10,
        ]);
        DB::connection('legacy')->table('perjadin_spt_tujuan')->insert([
            'id' => 1,
            'id_spt' => 1,
            'tempat_berangkat' => 'Manokwari',
            'tempat_tujuan' => 'Jakarta',
            'id_transportasi' => 1,
            'lamanya' => 3,
        ]);
        DB::connection('legacy')->table('perjadin_spt_pejabat')->insert([
            'id' => 1,
            'id_spt' => 1,
            'id_pegawai' => 1,
            'atas_nama' => 'a.n. Gubernur',
            'pejabat' => 'KABAN',
            'pejabat_sementara' => null,
        ]);
        DB::connection('legacy')->table('perjadin_sppd')->insert([
            'id' => 1,
            'id_spt' => 1,
            'id_pegawai' => 2,
            'no_registrasi' => '00001',
            'no_sppd' => '001',
            'pj_tingkat' => 'B',
            'pj_jenis' => 'Luar Daerah',
            'pj_tgl_berangkat' => '2024-01-12',
            'pj_tgl_kembali' => '2024-01-14',
            'ba_instansi' => 'BKD',
            'ba_mata_anggaran' => '5.1.02',
            'keterangan' => 'Rapat koordinasi',
            'verifikasi' => '1',
            'record' => '2024-01-10 08:00:00',
            'no_sppd_text' => '094/001/BKD/2024',
            'pemberi_perintah' => 'Kepala BKD',
            'tempat_dikeluarkan' => 'Manokwari',
            'tanggal' => '2024-01-10',
        ]);
        DB::connection('legacy')->table('perjadin_sppd_pejabat')->insert([
            'id' => 1,
            'id_sppd' => 1,
            'id_pegawai' => 1,
            'atas_nama' => 'a.n. Gubernur',
            'pejabat' => 'KABAN',
            'pejabat_sementara' => null,
        ]);
    }

    private function seedMappings(): void
    {
        DB::table('legacy_unit_mappings')->insert([
            'source_database' => $this->legacyDatabasePath,
            'legacy_unit_id' => 10,
            'sikkepo_unit_id' => '20000000-0000-4000-8000-000000000010',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('legacy_employee_mappings')->insert([
            [
                'source_database' => $this->legacyDatabasePath,
                'legacy_employee_id' => 1,
                'nip' => '198001012010011001',
                'sikkepo_pegawai_id' => '10000000-0000-4000-8000-000000000001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'source_database' => $this->legacyDatabasePath,
                'legacy_employee_id' => 2,
                'nip' => '198001012010011002',
                'sikkepo_pegawai_id' => '10000000-0000-4000-8000-000000000002',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
