<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Allocate an annual document number.
     *
     * This method must be called inside the transaction that creates the
     * corresponding document.
     *
     * @return array{year: int, sequence_number: int, registration_number: string, document_number: string}
     */
    public function next(string $documentType, CarbonInterface $issuedDate): array
    {
        $year = (int) $issuedDate->year;
        $now = now();

        DB::table('document_sequences')->insertOrIgnore([
            'document_type' => $documentType,
            'year' => $year,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sequence = DocumentSequence::query()
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->increment('last_number');
        $sequence->refresh();

        $number = $sequence->last_number;
        $registrationNumber = sprintf('%05d', $number);
        $format = ApplicationSetting::query()
            ->where('key', $this->settingKey($documentType))
            ->value('value');
        $format = filled($format)
            ? $format
            : config("perjadin.number_formats.$documentType");
        $format = filled($format)
            ? $format
            : config("perjadin.number_formats.defaults.$documentType");

        return [
            'year' => $year,
            'sequence_number' => $number,
            'registration_number' => $registrationNumber,
            'document_number' => strtr((string) $format, [
                '{number}' => $registrationNumber,
                '{year}' => (string) $year,
                '{type}' => strtoupper($documentType),
            ]),
        ];
    }

    private function settingKey(string $documentType): string
    {
        return match ($documentType) {
            'spt' => ApplicationSetting::KEY_SPT_NUMBER_FORMAT,
            'sppd' => ApplicationSetting::KEY_SPPD_NUMBER_FORMAT,
            default => "document_number_format.{$documentType}",
        };
    }
}
