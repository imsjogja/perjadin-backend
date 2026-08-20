<?php

namespace App\Actions;

use App\Models\Spt;
use App\Services\DocumentNumberService;
use App\Services\EmployeeSnapshotFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateSptAction
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly EmployeeSnapshotFactory $employeeSnapshots
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): Spt
    {
        $signatory = $this->employeeSnapshots->fromNip(
            $payload['signatory']['nip'],
            'signatory.nip'
        );
        $issuedDate = Carbon::parse($payload['issued_date']);

        return DB::transaction(function () use ($payload, $signatory, $issuedDate): Spt {
            $number = $this->numbers->next('spt', $issuedDate);

            $spt = Spt::query()->create([
                'unit_id' => $payload['unit_id'],
                'document_year' => $number['year'],
                'sequence_number' => $number['sequence_number'],
                'registration_number' => $number['registration_number'],
                'document_number' => $number['document_number'],
                'dasar' => $payload['dasar'],
                'disposisi' => $payload['disposisi'] ?? null,
                'dalam_rangka' => $payload['dalam_rangka'],
                'issued_place' => $payload['issued_place'],
                'issued_date' => $issuedDate,
            ]);

            $spt->destination()->create($payload['destination']);
            $spt->signatory()->create([
                'sikkepo_pegawai_id' => $signatory['pegawai_id'],
                'employee_snapshot' => $signatory,
                'behalf_of' => $payload['signatory']['behalf_of'] ?? null,
                'signatory_role' => $payload['signatory']['signatory_role'] ?? null,
                'is_acting' => $payload['signatory']['is_acting'] ?? false,
            ]);

            return $spt->load(['destination', 'signatory']);
        }, 3);
    }
}
