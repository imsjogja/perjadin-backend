<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sppd;
use App\Models\Spt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $year = $filters['year'] ?? now()->year;

        $spts = Spt::query()
            ->where('document_year', $year)
            ->with('destination')
            ->withCount(['assignees', 'sppds'])
            ->orderByDesc('issued_date')
            ->orderByDesc('sequence_number')
            ->get();

        $sppdTotals = Sppd::query()
            ->where('document_year', $year)
            ->get(['status'])
            ->countBy('status');

        $monthlySpts = collect(range(1, 12))
            ->map(fn (int $month) => [
                'month' => $month,
                'total' => $spts->filter(fn (Spt $spt) => $spt->issued_date->month === $month)->count(),
            ])
            ->values();

        $destinations = $spts
            ->map(fn (Spt $spt) => $spt->destination?->destination_place ?: 'Belum ditentukan')
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(fn (int $total, string $destination) => [
                'label' => $destination,
                'total' => $total,
            ])
            ->values();

        return response()->json([
            'data' => [
                'year' => $year,
                'kpis' => [
                    'spts' => $spts->count(),
                    'assignees' => $spts->sum('assignees_count'),
                    'sppds' => $sppdTotals->sum(),
                    'verified_sppds' => $sppdTotals->get(Sppd::STATUS_VERIFIED, 0),
                ],
                'monthly_spts' => $monthlySpts,
                'sppd_statuses' => [
                    [
                        'key' => Sppd::STATUS_DRAFT,
                        'label' => 'Draft',
                        'total' => $sppdTotals->get(Sppd::STATUS_DRAFT, 0),
                    ],
                    [
                        'key' => Sppd::STATUS_VERIFIED,
                        'label' => 'Terverifikasi',
                        'total' => $sppdTotals->get(Sppd::STATUS_VERIFIED, 0),
                    ],
                ],
                'destinations' => $destinations,
                'recent_spts' => $spts
                    ->take(6)
                    ->map(fn (Spt $spt) => [
                        'id' => $spt->id,
                        'document_number' => $spt->document_number,
                        'issued_date' => $spt->issued_date,
                        'destination' => $spt->destination?->destination_place ?: 'Belum ditentukan',
                        'assignees_count' => $spt->assignees_count,
                        'sppds_count' => $spt->sppds_count,
                    ])
                    ->values(),
            ],
        ]);
    }
}
