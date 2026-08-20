<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\Factory as ViewFactory;

class PerjadinDocumentPdf
{
    public function __construct(private readonly ViewFactory $view) {}

    /**
     * Render the established Legal portrait format used by the legacy
     * CodeIgniter application. Data supplied to views is transaction data,
     * never a live lookup to SIKKEPO.
     *
     * @param  array<string, mixed>  $data
     */
    public function inline(string $view, array $data, string $filename): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->view->make($view, array_merge($data, [
            'stationery' => $this->stationery(),
        ]))->render());
        $dompdf->setPaper('legal', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stationery(): array
    {
        $stationery = config('perjadin.stationery', []);
        $logoPath = Arr::get($stationery, 'logo_path');

        if (is_string($logoPath) && $logoPath !== '' && is_readable($logoPath)) {
            $mime = mime_content_type($logoPath) ?: 'image/png';
            $stationery['logo_data_uri'] = sprintf(
                'data:%s;base64,%s',
                $mime,
                base64_encode((string) file_get_contents($logoPath))
            );
        }

        return $stationery;
    }
}
