<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApplicationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentNumberFormatController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'spt' => $this->formatData('spt', ApplicationSetting::KEY_SPT_NUMBER_FORMAT),
                'sppd' => $this->formatData('sppd', ApplicationSetting::KEY_SPPD_NUMBER_FORMAT),
            ],
            'meta' => [
                'tokens' => [
                    '{number}' => 'Nomor urut lima digit',
                    '{year}' => 'Tahun dokumen',
                    '{type}' => 'Jenis dokumen (SPT/SPPD)',
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'spt_format' => $this->formatRules(),
            'sppd_format' => $this->formatRules(),
        ]);

        $this->saveFormat(
            ApplicationSetting::KEY_SPT_NUMBER_FORMAT,
            $validated['spt_format'] ?? null,
        );
        $this->saveFormat(
            ApplicationSetting::KEY_SPPD_NUMBER_FORMAT,
            $validated['sppd_format'] ?? null,
        );

        return $this->show();
    }

    /**
     * @return array<int, mixed>
     */
    private function formatRules(): array
    {
        return [
            'nullable',
            'string',
            'max:100',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! filled($value)) {
                    return;
                }

                if (! str_contains($value, '{number}')) {
                    $fail('Format nomor wajib memuat placeholder {number}.');
                    return;
                }

                preg_match_all('/\{[^}]+\}/', $value, $matches);
                $unsupported = array_diff($matches[0], ['{number}', '{year}', '{type}']);

                if ($unsupported !== []) {
                    $fail('Placeholder yang didukung hanya {number}, {year}, dan {type}.');
                }
            },
        ];
    }

    private function saveFormat(string $key, ?string $value): void
    {
        if (! filled($value)) {
            ApplicationSetting::query()->where('key', $key)->delete();

            return;
        }

        ApplicationSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * @return array{custom_value: ?string, effective_value: string, default_value: string, source: string}
     */
    private function formatData(string $documentType, string $settingKey): array
    {
        $customValue = ApplicationSetting::query()
            ->where('key', $settingKey)
            ->value('value');
        $environmentValue = config("perjadin.number_formats.{$documentType}");
        $defaultValue = config("perjadin.number_formats.defaults.{$documentType}");

        return [
            'custom_value' => $customValue,
            'effective_value' => filled($customValue)
                ? $customValue
                : (filled($environmentValue) ? $environmentValue : $defaultValue),
            'default_value' => $defaultValue,
            'source' => filled($customValue)
                ? 'application'
                : (filled($environmentValue) ? 'environment' : 'default'),
        ];
    }
}
