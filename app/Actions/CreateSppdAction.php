<?php

namespace App\Actions;

use App\Exceptions\SppdAlreadyExistsException;
use App\Models\Sppd;
use App\Models\Spt;
use App\Services\DocumentNumberService;
use App\Services\EmployeeSnapshotFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSppdAction
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly EmployeeSnapshotFactory $employeeSnapshots
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Spt $spt, array $payload): Sppd
    {
        $traveller = $this->employeeSnapshots->fromNip($payload['traveller_nip'], 'traveller_nip');
        $signatory = $this->employeeSnapshots->fromNip(
            $payload['signatory']['nip'],
            'signatory.nip'
        );
        $followers = [];

        foreach ($payload['followers'] ?? [] as $index => $nip) {
            $followers[] = $this->employeeSnapshots->fromNip($nip, "followers.$index");
        }

        $followerIds = array_column($followers, 'pegawai_id');

        if (in_array($traveller['pegawai_id'], $followerIds, true)) {
            throw ValidationException::withMessages([
                'followers' => 'Pegawai pelaksana perjalanan tidak dapat menjadi pengikut.',
            ]);
        }

        if (count($followerIds) !== count(array_unique($followerIds))) {
            throw ValidationException::withMessages([
                'followers' => 'Pengikut tidak boleh duplikat.',
            ]);
        }

        $issuedDate = Carbon::parse($payload['issued_date']);

        return DB::transaction(function () use ($spt, $payload, $traveller, $signatory, $followers, $issuedDate): Sppd {
            $lockedSpt = Spt::query()
                ->lockForUpdate()
                ->findOrFail($spt->getKey());

            $requiredAssigneeIds = array_merge(
                [$traveller['pegawai_id']],
                array_column($followers, 'pegawai_id')
            );
            $assignedEmployeeIds = $lockedSpt->assignees()
                ->whereIn('sikkepo_pegawai_id', $requiredAssigneeIds)
                ->pluck('sikkepo_pegawai_id')
                ->all();
            $missingAssigneeIds = array_diff($requiredAssigneeIds, $assignedEmployeeIds);

            if ($missingAssigneeIds !== []) {
                $field = in_array($traveller['pegawai_id'], $missingAssigneeIds, true)
                    ? 'traveller_nip'
                    : 'followers';

                throw ValidationException::withMessages([
                    $field => 'Setiap pelaksana perjalanan harus terlebih dahulu tercatat pada SPT.',
                ]);
            }

            $existingSppd = $lockedSpt->sppds()
                ->where('sikkepo_pegawai_id', $traveller['pegawai_id'])
                ->first();

            if ($existingSppd) {
                $isVerified = $existingSppd->status === Sppd::STATUS_VERIFIED;

                throw new SppdAlreadyExistsException(
                    $isVerified
                        ? "Pelaksana tersebut sudah memiliki SPPD terverifikasi ({$existingSppd->document_number}) pada SPT ini dan tidak dapat dibuat ulang."
                        : "Pelaksana tersebut masih memiliki draft SPPD ({$existingSppd->document_number}) pada SPT ini.",
                    $isVerified ? 'sppd_already_verified' : 'sppd_draft_exists'
                );
            }

            $number = $this->numbers->next('sppd', $issuedDate);

            $sppd = $lockedSpt->sppds()->create([
                'unit_id' => $lockedSpt->unit_id,
                'sikkepo_pegawai_id' => $traveller['pegawai_id'],
                'employee_snapshot' => $traveller,
                'document_year' => $number['year'],
                'sequence_number' => $number['sequence_number'],
                'registration_number' => $number['registration_number'],
                'document_number' => $number['document_number'],
                'order_giver' => $payload['order_giver'],
                'letterhead_type' => $payload['letterhead_type'] ?? Sppd::LETTERHEAD_AGENCY,
                'travel_level' => $payload['travel_level'] ?? null,
                'travel_type' => $payload['travel_type'] ?? null,
                'departure_date' => $payload['departure_date'],
                'return_date' => $payload['return_date'],
                'budget_agency' => $payload['budget_agency'],
                'budget_account' => $payload['budget_account'] ?? null,
                'description' => $payload['description'] ?? null,
                'issued_place' => $payload['issued_place'],
                'issued_date' => $issuedDate,
                'status' => Sppd::STATUS_DRAFT,
            ]);

            $sppd->followers()->createMany(array_map(
                static fn (array $follower): array => [
                    'sikkepo_pegawai_id' => $follower['pegawai_id'],
                    'employee_snapshot' => $follower,
                ],
                $followers
            ));
            $sppd->signatory()->create([
                'sikkepo_pegawai_id' => $signatory['pegawai_id'],
                'employee_snapshot' => $signatory,
                'behalf_of' => $payload['signatory']['behalf_of'] ?? null,
                'signatory_role' => $payload['signatory']['signatory_role'] ?? null,
                'is_acting' => $payload['signatory']['is_acting'] ?? false,
            ]);

            return $sppd->load(['spt.destination', 'followers', 'signatory']);
        }, 3);
    }
}
