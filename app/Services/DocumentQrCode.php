<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class DocumentQrCode
{
    /**
     * Create the same portable PNG QR Code used by legacy documents.
     * The QR payload is intentionally limited to the displayed document number.
     */
    public function dataUri(string $documentNumber): string
    {
        return (new QRCode(new QROptions([
            'eccLevel' => EccLevel::H,
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale' => 3,
        ])))->render(trim($documentNumber));
    }
}
