<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerjadinCoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_spt_list_filters_by_date_assignee_and_status_and_can_be_archived(): void
    {
        $this->fakeSikkepo();
        Sanctum::actingAs(User::factory()->create());

        $unassignedSpt = $this->postJson('/api/v1/spts', $this->sptPayload([
            'issued_date' => '2026-08-18',
        ]))
            ->assertCreated()
            ->json('data');

        $readySpt = $this->postJson('/api/v1/spts', $this->sptPayload([
            'issued_date' => '2026-08-20',
        ]))
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/v1/spts/{$readySpt['id']}/assignees", [
            'nips' => ['198001012010011002'],
        ])->assertCreated();

        $this->getJson('/api/v1/spts?date_from=2026-08-19&date_to=2026-08-21&assignee=Pelaksana&status=ready')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $readySpt['id'])
            ->assertJsonPath('data.0.assignees_count', 1)
            ->assertJsonPath('data.0.sppds_count', 0);

        $this->patchJson("/api/v1/spts/{$readySpt['id']}/archive")
            ->assertOk()
            ->assertJsonPath('data.id', $readySpt['id']);

        $this->assertDatabaseHas('spts', [
            'id' => $readySpt['id'],
        ]);
        $this->assertDatabaseMissing('spts', [
            'id' => $readySpt['id'],
            'archived_at' => null,
        ]);

        $this->getJson('/api/v1/spts?status=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $readySpt['id']);

        $this->getJson('/api/v1/spts?status=unassigned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unassignedSpt['id']);
    }

    public function test_spt_without_sppd_can_be_deleted_but_spt_with_sppd_is_preserved(): void
    {
        $this->fakeSikkepo();
        Sanctum::actingAs(User::factory()->create());

        $deletableSpt = $this->postJson('/api/v1/spts', $this->sptPayload())
            ->assertCreated()
            ->json('data');

        $this->deleteJson("/api/v1/spts/{$deletableSpt['id']}")
            ->assertNoContent();
        $this->assertDatabaseMissing('spts', ['id' => $deletableSpt['id']]);

        $sptWithSppd = $this->postJson('/api/v1/spts', $this->sptPayload([
            'issued_date' => '2026-08-21',
        ]))
            ->assertCreated()
            ->json('data');
        $this->postJson("/api/v1/spts/{$sptWithSppd['id']}/assignees", [
            'nips' => ['198001012010011002'],
        ])->assertCreated();
        $this->postJson("/api/v1/spts/{$sptWithSppd['id']}/sppds", $this->sppdPayload())
            ->assertCreated();

        $this->deleteJson("/api/v1/spts/{$sptWithSppd['id']}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'spt_has_sppds');
        $this->assertDatabaseHas('spts', ['id' => $sptWithSppd['id']]);
    }

    public function test_spt_can_be_updated_without_regenerating_document_number(): void
    {
        $this->fakeSikkepo();
        Sanctum::actingAs(User::factory()->create());

        $spt = $this->postJson('/api/v1/spts', $this->sptPayload())
            ->assertCreated()
            ->json('data');

        $this->patchJson("/api/v1/spts/{$spt['id']}", $this->sptPayload([
            'dasar' => 'Dasar perjalanan yang diperbarui',
            'dalam_rangka' => 'Koordinasi yang diperbarui',
            'issued_place' => 'Sorong',
            'issued_date' => '2026-08-21',
            'destination' => [
                'transportation' => 'Kapal',
                'departure_place' => 'Sorong',
                'destination_place' => 'Jayapura',
                'duration_days' => 4,
            ],
            'signatory' => [
                'nip' => '198001012010011002',
                'behalf_of' => 'a.n. Kepala BKD',
                'signatory_role' => 'Sekretaris BKD',
                'is_acting' => true,
            ],
        ]))
            ->assertOk()
            ->assertJsonPath('data.document_number', $spt['document_number'])
            ->assertJsonPath('data.registration_number', $spt['registration_number'])
            ->assertJsonPath('data.dasar', 'Dasar perjalanan yang diperbarui')
            ->assertJsonPath('data.destination.destination_place', 'Jayapura')
            ->assertJsonPath('data.signatory.employee_snapshot.nama', 'Pelaksana Perjalanan')
            ->assertJsonPath('data.signatory.is_acting', true);

        $this->assertDatabaseHas('spts', [
            'id' => $spt['id'],
            'document_number' => $spt['document_number'],
            'registration_number' => $spt['registration_number'],
            'issued_place' => 'Sorong',
        ]);
        $this->assertDatabaseHas('spt_destinations', [
            'spt_id' => $spt['id'],
            'destination_place' => 'Jayapura',
            'duration_days' => 4,
        ]);
    }

    public function test_spt_stores_multiple_bases_in_order(): void
    {
        $this->fakeSikkepo();
        Sanctum::actingAs(User::factory()->create());

        $spt = $this->postJson('/api/v1/spts', $this->sptPayload([
            'dasar' => [
                'Undang-Undang Nomor 23 Tahun 2014.',
                'Surat undangan koordinasi.',
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.dasar', "Undang-Undang Nomor 23 Tahun 2014.\nSurat undangan koordinasi.")
            ->assertJsonPath('data.bases.0.content', 'Undang-Undang Nomor 23 Tahun 2014.')
            ->assertJsonPath('data.bases.1.content', 'Surat undangan koordinasi.')
            ->json('data');

        $this->assertDatabaseHas('spt_bases', [
            'spt_id' => $spt['id'],
            'content' => 'Undang-Undang Nomor 23 Tahun 2014.',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('spt_bases', [
            'spt_id' => $spt['id'],
            'content' => 'Surat undangan koordinasi.',
            'sort_order' => 2,
        ]);
    }

    public function test_spt_and_sppd_snapshot_active_sikkepo_employees_and_verify_once(): void
    {
        $this->fakeSikkepo();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $spt = $this->postJson('/api/v1/spts', $this->sptPayload())
            ->assertCreated()
            ->assertJsonPath('data.registration_number', '00001')
            ->assertJsonPath('data.document_number', '823-00001/BKD-SPT/2026')
            ->assertJsonPath('data.signatory.employee_snapshot.nama', 'Pejabat Penandatangan')
            ->json('data');

        $this->assertDatabaseHas('spts', [
            'id' => $spt['id'],
            'document_year' => 2026,
            'sequence_number' => 1,
        ]);
        $this->assertDatabaseHas('spt_signatories', [
            'spt_id' => $spt['id'],
            'sikkepo_pegawai_id' => '10000000-0000-4000-8000-000000000001',
        ]);

        $this->postJson("/api/v1/spts/{$spt['id']}/assignees", [
            'nips' => ['198001012010011002', '198001012010011003'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.assignment_revision', 1)
            ->assertJsonPath('data.assignees.0.employee_snapshot.nama', 'Pelaksana Perjalanan');

        $this->assertDatabaseHas('spt_assignees', [
            'spt_id' => $spt['id'],
            'sikkepo_pegawai_id' => '10000000-0000-4000-8000-000000000002',
            'assignment_revision' => 1,
            'assigned_by' => $user->id,
        ]);
        $this->assertDatabaseHas('spt_assignees', [
            'spt_id' => $spt['id'],
            'sikkepo_pegawai_id' => '10000000-0000-4000-8000-000000000003',
        ]);

        $sppd = $this->postJson("/api/v1/spts/{$spt['id']}/sppds", [
            'traveller_nip' => '198001012010011002',
            'order_giver' => 'Kepala BKD',
            'travel_level' => 'Dalam Negeri',
            'travel_type' => 'Dinas Luar',
            'departure_date' => '2026-08-25',
            'return_date' => '2026-08-27',
            'budget_agency' => 'BKD',
            'budget_account' => '5.1.02',
            'description' => 'Koordinasi',
            'issued_place' => 'Manokwari',
            'issued_date' => '2026-08-20',
            'followers' => ['198001012010011003'],
            'signatory' => [
                'nip' => '198001012010011001',
                'behalf_of' => 'a.n. Gubernur',
                'signatory_role' => 'Kepala BKD',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.registration_number', '00001')
            ->assertJsonPath('data.document_number', '823-00001/BKD-SPPD/2026')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.employee_snapshot.nama', 'Pelaksana Perjalanan')
            ->assertJsonPath('data.followers.0.employee_snapshot.nama', 'Pengikut Perjalanan')
            ->json('data');

        $this->assertDatabaseHas('sppd_followers', [
            'sppd_id' => $sppd['id'],
            'sikkepo_pegawai_id' => '10000000-0000-4000-8000-000000000003',
        ]);

        $this->postJson("/api/v1/spts/{$spt['id']}/sppds", $this->sppdPayload())
            ->assertStatus(409)
            ->assertJsonPath('code', 'sppd_draft_exists');

        $this->patchJson("/api/v1/sppds/{$sppd['id']}", array_replace_recursive(
            $this->sppdPayload(),
            ['description' => 'Koordinasi yang diperbarui']
        ))
            ->assertOk()
            ->assertJsonPath('data.description', 'Koordinasi yang diperbarui');

        $this->patchJson("/api/v1/sppds/{$sppd['id']}/verification")
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.verified_by', $user->id);

        $this->postJson("/api/v1/spts/{$spt['id']}/sppds", $this->sppdPayload())
            ->assertStatus(409)
            ->assertJsonPath('code', 'sppd_already_verified');

        $this->patchJson("/api/v1/sppds/{$sppd['id']}/verification")
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_sppd_status');

        $this->deleteJson("/api/v1/sppds/{$sppd['id']}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_sppd_status');
    }

    public function test_document_number_is_reset_for_a_new_year(): void
    {
        $this->fakeSikkepo();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/spts', $this->sptPayload([
            'issued_date' => '2026-12-31',
        ]))->assertJsonPath('data.document_number', '823-00001/BKD-SPT/2026');

        $this->postJson('/api/v1/spts', $this->sptPayload([
            'issued_date' => '2027-01-01',
        ]))->assertJsonPath('data.document_number', '823-00001/BKD-SPT/2027');

        config(['perjadin.number_formats.spt' => 'SPT/{year}/{number}']);
        $this->postJson('/api/v1/spts', $this->sptPayload([
            'issued_date' => '2027-01-02',
        ]))->assertJsonPath('data.document_number', 'SPT/2027/00002');
    }

    public function test_draft_sppd_can_be_deleted(): void
    {
        $this->fakeSikkepo();
        Sanctum::actingAs(User::factory()->create());

        $spt = $this->postJson('/api/v1/spts', $this->sptPayload())->json('data');
        $this->postJson("/api/v1/spts/{$spt['id']}/assignees", [
            'nips' => ['198001012010011002'],
        ]);
        $sppd = $this->postJson("/api/v1/spts/{$spt['id']}/sppds", $this->sppdPayload())->json('data');

        $this->deleteJson("/api/v1/sppds/{$sppd['id']}")
            ->assertNoContent();
        $this->assertDatabaseMissing('sppds', ['id' => $sppd['id']]);
    }

    public function test_sppd_rejects_inactive_employees_and_duplicate_traveller_follower(): void
    {
        $this->fakeSikkepo();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/spts', $this->sptPayload([
            'signatory' => ['nip' => '198001012010011004'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signatory.nip');

        $spt = $this->postJson('/api/v1/spts', $this->sptPayload())
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/v1/spts/{$spt['id']}/sppds", [
            'traveller_nip' => '198001012010011002',
            'order_giver' => 'Kepala BKD',
            'departure_date' => '2026-08-25',
            'return_date' => '2026-08-27',
            'budget_agency' => 'BKD',
            'issued_place' => 'Manokwari',
            'issued_date' => '2026-08-20',
            'followers' => ['198001012010011002'],
            'signatory' => ['nip' => '198001012010011001'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('followers');

        $this->assertDatabaseCount('sppds', 0);
    }

    public function test_spt_assignees_can_be_added_without_sppd_and_are_required_for_a_traveller(): void
    {
        $this->fakeSikkepo();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $spt = $this->postJson('/api/v1/spts', $this->sptPayload())
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/v1/spts/{$spt['id']}/sppds", $this->sppdPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('traveller_nip');

        $this->postJson("/api/v1/spts/{$spt['id']}/assignees", [
            'nips' => ['198001012010011002', '198001012010011003'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.assignment_revision', 1)
            ->assertJsonCount(2, 'data.assignees');

        $this->getJson("/api/v1/spts/{$spt['id']}/assignees")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.employee_snapshot.nama', 'Pengikut Perjalanan');

        $this->postJson("/api/v1/spts/{$spt['id']}/assignees", [
            'nips' => ['198001012010011002'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nips');

        $this->postJson("/api/v1/spts/{$spt['id']}/sppds", $this->sppdPayload())
            ->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sptPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'unit_id' => '20000000-0000-4000-8000-000000000001',
            'dasar' => 'Surat tugas',
            'disposisi' => 'Segera',
            'dalam_rangka' => 'Koordinasi antar instansi',
            'issued_place' => 'Manokwari',
            'issued_date' => '2026-08-20',
            'destination' => [
                'transportation' => 'Pesawat',
                'departure_place' => 'Manokwari',
                'destination_place' => 'Jakarta',
                'duration_days' => 3,
            ],
            'signatory' => [
                'nip' => '198001012010011001',
                'behalf_of' => 'a.n. Gubernur',
                'signatory_role' => 'Kepala BKD',
                'is_acting' => false,
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function sppdPayload(): array
    {
        return [
            'traveller_nip' => '198001012010011002',
            'order_giver' => 'Kepala BKD',
            'departure_date' => '2026-08-25',
            'return_date' => '2026-08-27',
            'budget_agency' => 'BKD',
            'issued_place' => 'Manokwari',
            'issued_date' => '2026-08-20',
            'signatory' => ['nip' => '198001012010011001'],
        ];
    }

    private function fakeSikkepo(): void
    {
        config([
            'sikkepo.base_url' => 'https://sikkepo.test',
            'sikkepo.platform_client_id' => 'perjadin',
            'sikkepo.platform_client_secret' => 'secret',
        ]);
        Cache::forget(config('sikkepo.token_cache_key'));

        $employees = [
            '198001012010011001' => $this->employee(
                '10000000-0000-4000-8000-000000000001',
                '198001012010011001',
                'Pejabat Penandatangan'
            ),
            '198001012010011002' => $this->employee(
                '10000000-0000-4000-8000-000000000002',
                '198001012010011002',
                'Pelaksana Perjalanan'
            ),
            '198001012010011003' => $this->employee(
                '10000000-0000-4000-8000-000000000003',
                '198001012010011003',
                'Pengikut Perjalanan'
            ),
            '198001012010011004' => $this->employee(
                '10000000-0000-4000-8000-000000000004',
                '198001012010011004',
                'Pegawai Nonaktif',
                false
            ),
        ];

        Http::fake([
            'https://sikkepo.test/api/v1/platform/token' => Http::response([
                'access_token' => 'platform-token',
            ]),
            'https://sikkepo.test/api/v1/platform/pegawai*' => function (Request $request) use ($employees) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $pegawai = $employees[$query['nip'] ?? ''] ?? null;

                return Http::response([
                    'data' => $pegawai ? [$pegawai] : [],
                    'meta' => ['current_page' => 1, 'per_page' => 1, 'total' => $pegawai ? 1 : 0],
                ]);
            },
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function employee(string $id, string $nip, string $nama, bool $aktif = true): array
    {
        return [
            'pegawai_id' => $id,
            'nip' => $nip,
            'nama' => $nama,
            'tipe' => 'pns',
            'aktif' => $aktif,
            'unit' => ['id' => '20000000-0000-4000-8000-000000000001', 'nama' => 'BKD'],
            'jabatan' => ['id' => '30000000-0000-4000-8000-000000000001', 'nama' => 'Analis'],
            'golongan' => ['id' => '40000000-0000-4000-8000-000000000001', 'nama' => 'III/a'],
            'eselon' => null,
            'kelas_jabatan' => ['id' => '50000000-0000-4000-8000-000000000001', 'nama' => '9'],
            'updated_at' => '2026-08-20T00:00:00Z',
        ];
    }
}
