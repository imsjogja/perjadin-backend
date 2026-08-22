<?php

namespace Tests\Feature;

use App\Models\Sppd;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentPrintApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_spt_print_uses_legal_pdf_and_sppd_documents_require_verification(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$spt, $sppd] = $this->documents();

        $this->get("/api/v1/spts/{$spt->id}/print")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="SPT-00001.pdf"')
            ->assertSee('%PDF-', false);

        $this->get("/api/v1/sppds/{$sppd->id}/print")
            ->assertStatus(409)
            ->assertJson([
                'code' => 'sppd_not_verified',
            ]);

        $preview = $this->get("/api/v1/sppds/{$sppd->id}/preview")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="PREVIEW-SPPD-00001.pdf"')
            ->assertSee('%PDF-', false);
        $this->assertPdfPageCount($preview, 1);

        $sppd->update(['status' => Sppd::STATUS_VERIFIED, 'verified_at' => now()]);

        $this->get("/api/v1/sppds/{$sppd->id}/print")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="SPPD-00001.pdf"')
            ->assertSee('%PDF-', false);

        $visum = $this->get("/api/v1/sppds/{$sppd->id}/visum")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="VISUM-SPPD-00001.pdf"')
            ->assertSee('%PDF-', false);
        $this->assertPdfPageCount($visum, 1);
    }

    /**
     * @return array{Spt, Sppd}
     */
    private function documents(): array
    {
        $employee = [
            'pegawai_id' => '10000000-0000-4000-8000-000000000001',
            'nip' => '198001012010011001',
            'nama' => 'Pejabat Penandatangan',
            'jabatan' => ['nama' => 'Kepala BKD'],
            'golongan' => ['nama' => 'IV/a', 'pangkat' => 'Pembina'],
            'eselon' => ['nama' => 'II.b'],
        ];
        $traveller = array_replace($employee, [
            'pegawai_id' => '10000000-0000-4000-8000-000000000002',
            'nip' => '198001012010011002',
            'nama' => 'Pelaksana Perjalanan',
            'jabatan' => ['nama' => 'Analis Kepegawaian'],
            'golongan' => ['nama' => 'III/a', 'pangkat' => 'Penata Muda'],
            'eselon' => null,
        ]);

        $spt = Spt::query()->create([
            'unit_id' => '20000000-0000-4000-8000-000000000001',
            'document_year' => 2026,
            'sequence_number' => 1,
            'registration_number' => '00001',
            'document_number' => '823-00001/BKD-SPT/2026',
            'dasar' => 'Surat tugas.',
            'dalam_rangka' => 'Koordinasi antar instansi.',
            'issued_place' => 'Manokwari',
            'issued_date' => '2026-08-20',
        ]);
        $spt->destination()->create([
            'transportation' => 'Pesawat',
            'departure_place' => 'Manokwari',
            'destination_place' => 'Jakarta',
            'duration_days' => 3,
        ]);
        $spt->signatory()->create([
            'sikkepo_pegawai_id' => $employee['pegawai_id'],
            'employee_snapshot' => $employee,
            'signatory_role' => 'Kepala BKD',
        ]);
        $spt->assignees()->create([
            'sikkepo_pegawai_id' => $traveller['pegawai_id'],
            'employee_snapshot' => $traveller,
            'assignment_revision' => 1,
            'assigned_at' => now(),
        ]);

        $sppd = $spt->sppds()->create([
            'unit_id' => $spt->unit_id,
            'sikkepo_pegawai_id' => $traveller['pegawai_id'],
            'employee_snapshot' => $traveller,
            'document_year' => 2026,
            'sequence_number' => 1,
            'registration_number' => '00001',
            'document_number' => '823-00001/BKD-SPPD/2026',
            'order_giver' => 'Kepala BKD',
            'travel_level' => 'Dalam Negeri',
            'departure_date' => '2026-08-25',
            'return_date' => '2026-08-27',
            'budget_agency' => 'BKD',
            'budget_account' => '5.1.02',
            'issued_place' => 'Manokwari',
            'issued_date' => '2026-08-20',
            'status' => Sppd::STATUS_DRAFT,
        ]);
        $sppd->signatory()->create([
            'sikkepo_pegawai_id' => $employee['pegawai_id'],
            'employee_snapshot' => $employee,
            'signatory_role' => 'Kepala BKD',
        ]);

        return [$spt, $sppd];
    }

    private function assertPdfPageCount(TestResponse $response, int $expected): void
    {
        preg_match_all('/\/Type\s*\/Page\b/', (string) $response->getContent(), $pages);

        $this->assertCount($expected, $pages[0]);
    }
}
