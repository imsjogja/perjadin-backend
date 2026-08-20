<?php

namespace App\Actions;

use App\Models\Sppd;
use App\Models\User;
use DomainException;

class VerifySppdAction
{
    public function handle(Sppd $sppd, User $verifier): Sppd
    {
        if ($sppd->status !== Sppd::STATUS_DRAFT) {
            throw new DomainException('SPPD hanya dapat diverifikasi dari status draft.');
        }

        $sppd->update([
            'status' => Sppd::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifier->id,
        ]);

        return $sppd->fresh(['spt.destination', 'followers', 'signatory', 'verifier']);
    }
}
