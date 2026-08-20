<?php

namespace App\Actions;

use App\Models\Spt;
use App\Services\EmployeeSnapshotFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateSptAction
{
    public function __construct(private readonly EmployeeSnapshotFactory $employeeSnapshots) {}

    /**
     * Update the editable contents of an issued SPT without regenerating its
     * registration or document number.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Spt $spt, array $payload): Spt
    {
        $signatory = $this->employeeSnapshots->fromNip(
            $payload['signatory']['nip'],
            'signatory.nip'
        );

        return DB::transaction(function () use ($spt, $payload, $signatory): Spt {
            $document = Spt::query()->lockForUpdate()->findOrFail($spt->getKey());

            $document->update([
                'unit_id' => $payload['unit_id'],
                'dasar' => $payload['dasar'],
                'disposisi' => $payload['disposisi'] ?? null,
                'dalam_rangka' => $payload['dalam_rangka'],
                'issued_place' => $payload['issued_place'],
                'issued_date' => Carbon::parse($payload['issued_date']),
            ]);
            $document->destination()->update($payload['destination']);
            $document->signatory()->updateOrCreate([], [
                'sikkepo_pegawai_id' => $signatory['pegawai_id'],
                'employee_snapshot' => $signatory,
                'behalf_of' => $payload['signatory']['behalf_of'] ?? null,
                'signatory_role' => $payload['signatory']['signatory_role'] ?? null,
                'is_acting' => $payload['signatory']['is_acting'] ?? false,
            ]);

            return $document->fresh()->load(['destination', 'signatory']);
        }, 3);
    }
}
