<?php

namespace App\Actions;

use App\Models\Sppd;
use App\Models\Spt;
use App\Services\EmployeeSnapshotFactory;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSppdDraftAction
{
    public function __construct(private readonly EmployeeSnapshotFactory $employeeSnapshots) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Sppd $sppd, array $payload): Sppd
    {
        $traveller = $this->employeeSnapshots->fromNip($payload['traveller_nip'], 'traveller_nip');
        $signatory = $this->employeeSnapshots->fromNip($payload['signatory']['nip'], 'signatory.nip');
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

        return DB::transaction(function () use ($sppd, $payload, $traveller, $signatory, $followers): Sppd {
            $lockedSpt = Spt::query()->lockForUpdate()->findOrFail($sppd->spt_id);
            $draft = Sppd::query()->lockForUpdate()->findOrFail($sppd->getKey());
            if ($draft->status !== Sppd::STATUS_DRAFT) {
                throw new DomainException('Hanya draft SPPD yang dapat diubah.');
            }

            $requiredAssigneeIds = array_merge([$traveller['pegawai_id']], array_column($followers, 'pegawai_id'));
            $assignedEmployeeIds = $lockedSpt->assignees()
                ->whereIn('sikkepo_pegawai_id', $requiredAssigneeIds)
                ->pluck('sikkepo_pegawai_id')
                ->all();

            if (array_diff($requiredAssigneeIds, $assignedEmployeeIds) !== []) {
                throw ValidationException::withMessages([
                    'traveller_nip' => 'Setiap pelaksana perjalanan harus terlebih dahulu tercatat pada SPT.',
                ]);
            }

            $hasConflict = $lockedSpt->sppds()
                ->where('sikkepo_pegawai_id', $traveller['pegawai_id'])
                ->where('status', Sppd::STATUS_DRAFT)
                ->where('id', '<>', $draft->getKey())
                ->exists();
            if ($hasConflict) {
                throw new DomainException('Pelaksana tersebut masih memiliki draft SPPD lain pada SPT ini.');
            }

            $draft->update([
                'sikkepo_pegawai_id' => $traveller['pegawai_id'],
                'employee_snapshot' => $traveller,
                'order_giver' => $payload['order_giver'],
                'letterhead_type' => $payload['letterhead_type']
                    ?? $draft->letterhead_type
                    ?? Sppd::LETTERHEAD_AGENCY,
                'travel_level' => $payload['travel_level'] ?? null,
                'travel_type' => $payload['travel_type'] ?? null,
                'departure_date' => $payload['departure_date'],
                'return_date' => $payload['return_date'],
                'budget_agency' => $payload['budget_agency'],
                'budget_account' => $payload['budget_account'] ?? null,
                'description' => $payload['description'] ?? null,
                'issued_place' => $payload['issued_place'],
                'issued_date' => Carbon::parse($payload['issued_date']),
            ]);
            $draft->followers()->delete();
            $draft->followers()->createMany(array_map(
                static fn (array $follower): array => [
                    'sikkepo_pegawai_id' => $follower['pegawai_id'],
                    'employee_snapshot' => $follower,
                ],
                $followers
            ));
            $draft->signatory()->update([
                'sikkepo_pegawai_id' => $signatory['pegawai_id'],
                'employee_snapshot' => $signatory,
                'behalf_of' => $payload['signatory']['behalf_of'] ?? null,
                'signatory_role' => $payload['signatory']['signatory_role'] ?? null,
                'is_acting' => $payload['signatory']['is_acting'] ?? false,
            ]);

            return $draft->fresh()->load(['spt.destination', 'followers', 'signatory']);
        }, 3);
    }
}
