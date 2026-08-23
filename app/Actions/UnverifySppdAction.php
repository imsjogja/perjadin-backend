<?php

namespace App\Actions;

use App\Models\Sppd;
use DomainException;

class UnverifySppdAction
{
    public function handle(Sppd $sppd): Sppd
    {
        if ($sppd->status !== Sppd::STATUS_VERIFIED) {
            throw new DomainException('Hanya SPPD terverifikasi yang dapat dibatalkan verifikasinya.');
        }

        $sppd->update([
            'status' => Sppd::STATUS_DRAFT,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return $sppd->fresh(['spt.destination', 'followers', 'signatory', 'verifier']);
    }
}
