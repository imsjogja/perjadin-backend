<?php

namespace App\Actions;

use App\Models\Spt;
use App\Services\EmployeeSnapshotFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddSptAssigneesAction
{
    public function __construct(private readonly EmployeeSnapshotFactory $employeeSnapshots) {}

    /**
     * @param  array<int, string>  $nips
     */
    public function handle(Spt $spt, array $nips, ?int $assignedBy): Spt
    {
        $assignees = [];

        foreach ($nips as $index => $nip) {
            $assignees[] = $this->employeeSnapshots->fromNip($nip, "nips.$index");
        }

        $employeeIds = array_column($assignees, 'pegawai_id');

        if (count($employeeIds) !== count(array_unique($employeeIds))) {
            throw ValidationException::withMessages([
                'nips' => 'Pegawai pelaksana tidak boleh duplikat.',
            ]);
        }

        return DB::transaction(function () use ($spt, $assignees, $employeeIds, $assignedBy): Spt {
            $lockedSpt = Spt::query()
                ->lockForUpdate()
                ->findOrFail($spt->getKey());

            $alreadyAssigned = $lockedSpt->assignees()
                ->whereIn('sikkepo_pegawai_id', $employeeIds)
                ->exists();

            if ($alreadyAssigned) {
                throw ValidationException::withMessages([
                    'nips' => 'Salah satu pegawai sudah menjadi pelaksana pada SPT ini.',
                ]);
            }

            $revision = $lockedSpt->assignment_revision + 1;
            $assignedAt = now();

            $lockedSpt->forceFill([
                'assignment_revision' => $revision,
                'assignment_updated_at' => $assignedAt,
                'assignment_updated_by' => $assignedBy,
            ])->save();

            $lockedSpt->assignees()->createMany(array_map(
                static fn (array $assignee): array => [
                    'sikkepo_pegawai_id' => $assignee['pegawai_id'],
                    'employee_snapshot' => $assignee,
                    'assignment_revision' => $revision,
                    'assigned_at' => $assignedAt,
                    'assigned_by' => $assignedBy,
                ],
                $assignees
            ));

            return $lockedSpt->load(['destination', 'signatory', 'assignees']);
        }, 3);
    }
}
