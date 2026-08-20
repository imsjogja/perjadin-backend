<?php

namespace Tests\Unit;

use App\Services\DocumentQrCode;
use Tests\TestCase;

class DocumentQrCodeTest extends TestCase
{
    public function test_it_generates_a_png_data_uri_from_the_document_number(): void
    {
        $documentNumber = '823-00001/BKD-SPT/2026';
        $dataUri = app(DocumentQrCode::class)->dataUri($documentNumber);

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
        $this->assertNotFalse(base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true));
    }
}
