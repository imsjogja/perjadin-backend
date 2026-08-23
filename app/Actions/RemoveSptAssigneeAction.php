<?php

namespace App\Actions;

use App\Exceptions\AssigneeHasSppdException;
use App\Models\Spt;
use App\Models\SptAssignee;
use Illuminate\Support\Facades\DB;

class RemoveSptAssigneeAction
{
    public function handle(Spt $spt, SptAssignee $assignee, ?int $updatedBy): Spt
    {
        return DB::transaction(function () use ($spt, $assignee, $updatedBy): Spt {
            $lockedSpt = Spt::query()
                ->lockForUpdate()
                ->findOrFail($spt->getKey());

            $lockedAssignee = $lockedSpt->assignees()
                ->lockForUpdate()
                ->findOrFail($assignee->getKey());

            if ($lockedSpt->sppds()
                ->where('sikkepo_pegawai_id', $lockedAssignee->sikkepo_pegawai_id)
                ->exists()) {
                throw new AssigneeHasSppdException(
                    'Pelaksana yang sudah memiliki SPPD tidak dapat dihapus dari SPT.'
                );
            }

            $lockedAssignee->delete();

            $lockedSpt->forceFill([
                'assignment_revision' => $lockedSpt->assignment_revision + 1,
                'assignment_updated_at' => now(),
                'assignment_updated_by' => $updatedBy,
            ])->save();

            return $lockedSpt->load([
                'bases',
                'destination',
                'signatory',
                'assignees',
                'sppds',
            ]);
        }, 3);
    }
}
