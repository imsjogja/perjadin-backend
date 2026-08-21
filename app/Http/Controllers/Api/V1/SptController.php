<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateSptAction;
use App\Actions\UpdateSptAction;
use App\Exceptions\SikkepoPlatformException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSptRequest;
use App\Models\Spt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SptController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'assignee' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:unassigned,ready,archived'],
        ]);

        $query = Spt::query()
            ->with(['bases', 'destination', 'signatory'])
            ->withCount(['assignees', 'sppds'])
            ->orderByDesc('issued_date')
            ->orderByDesc('sequence_number');

        if (isset($filters['year'])) {
            $query->where('document_year', $filters['year']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('issued_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('issued_date', '<=', $filters['date_to']);
        }

        if (isset($filters['assignee'])) {
            $query->whereHas('assignees', function ($assignees) use ($filters) {
                $assignees->where('employee_snapshot->nama', 'like', '%'.$filters['assignee'].'%');
            });
        }

        if (($filters['status'] ?? null) === 'archived') {
            $query->whereNotNull('archived_at');
        }

        if (($filters['status'] ?? null) === 'unassigned') {
            $query->whereNull('archived_at')->doesntHave('assignees');
        }

        if (($filters['status'] ?? null) === 'ready') {
            $query->whereNull('archived_at')->has('assignees');
        }

        return response()->json($query->paginate($filters['per_page'] ?? 25));
    }

    public function store(StoreSptRequest $request, CreateSptAction $action): JsonResponse
    {
        try {
            return response()->json([
                'data' => $action->handle($request->validated()),
            ], 201);
        } catch (SikkepoPlatformException $exception) {
            report($exception);

            return $this->sikkepoUnavailable();
        }
    }

    public function show(Spt $spt): JsonResponse
    {
        return response()->json([
            'data' => $spt->load(['bases', 'destination', 'signatory', 'assignees', 'sppds']),
        ]);
    }

    public function update(StoreSptRequest $request, Spt $spt, UpdateSptAction $action): JsonResponse
    {
        try {
            return response()->json([
                'data' => $action->handle($spt, $request->validated()),
            ]);
        } catch (SikkepoPlatformException $exception) {
            report($exception);

            return $this->sikkepoUnavailable();
        }
    }

    public function archive(Request $request, Spt $spt): JsonResponse
    {
        if (! $spt->archived_at) {
            $spt->forceFill([
                'archived_at' => now(),
                'archived_by' => $request->user()->id,
            ])->save();
        }

        return response()->json([
            'data' => $spt->fresh()->load(['bases', 'destination', 'signatory'])->loadCount(['assignees', 'sppds']),
        ]);
    }

    public function destroy(Spt $spt): JsonResponse
    {
        if ($spt->sppds()->exists()) {
            return response()->json([
                'message' => 'SPT yang sudah memiliki SPPD tidak dapat dihapus. Arsipkan SPT untuk menyimpan riwayat dokumen.',
                'code' => 'spt_has_sppds',
            ], 409);
        }

        $spt->delete();

        return response()->json([], 204);
    }

    private function sikkepoUnavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Referensi pegawai sedang tidak tersedia.',
            'code' => 'sikkepo_unavailable',
        ], 502);
    }
}
