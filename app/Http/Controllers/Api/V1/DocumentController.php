<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sppd;
use App\Models\Spt;
use App\Services\DocumentQrCode;
use App\Services\PerjadinDocumentPdf;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function spt(Spt $spt, PerjadinDocumentPdf $pdf, DocumentQrCode $qrCode): Response
    {
        $spt->load(['destination', 'signatory', 'assignees']);

        return $pdf->inline(
            'documents.spt',
            [
                'spt' => $spt,
                'qrCode' => $qrCode->dataUri($spt->document_number),
            ],
            'SPT-'.$spt->registration_number
        );
    }

    public function sppd(Sppd $sppd, PerjadinDocumentPdf $pdf, DocumentQrCode $qrCode): Response
    {
        $this->ensureVerified($sppd);
        $sppd->load(['spt.destination', 'followers', 'signatory']);

        return $pdf->inline(
            'documents.sppd',
            [
                'sppd' => $sppd,
                'qrCode' => $qrCode->dataUri($sppd->document_number),
            ],
            'SPPD-'.$sppd->registration_number
        );
    }

    public function previewSppd(Sppd $sppd, PerjadinDocumentPdf $pdf, DocumentQrCode $qrCode): Response
    {
        $sppd->load(['spt.destination', 'followers', 'signatory']);

        return $pdf->inline(
            'documents.sppd',
            [
                'sppd' => $sppd,
                'preview' => true,
                'qrCode' => $qrCode->dataUri($sppd->document_number),
            ],
            'PREVIEW-SPPD-'.$sppd->registration_number
        );
    }

    public function visum(Sppd $sppd, PerjadinDocumentPdf $pdf): Response
    {
        $this->ensureVerified($sppd);
        $sppd->load(['spt.destination', 'signatory']);

        return $pdf->inline(
            'documents.visum',
            compact('sppd'),
            'VISUM-SPPD-'.$sppd->registration_number
        );
    }

    private function ensureVerified(Sppd $sppd): void
    {
        if ($sppd->status === Sppd::STATUS_VERIFIED) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'SPPD harus diverifikasi sebelum dokumen resmi dapat dicetak.',
            'code' => 'sppd_not_verified',
        ], 409));
    }
}
