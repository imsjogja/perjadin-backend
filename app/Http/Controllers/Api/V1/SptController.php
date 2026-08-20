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
        ]);

        $query = Spt::query()
            ->with(['destination', 'signatory'])
            ->orderByDesc('issued_date')
            ->orderByDesc('sequence_number');

        if (isset($filters['year'])) {
            $query->where('document_year', $filters['year']);
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
            'data' => $spt->load(['destination', 'signatory', 'assignees', 'sppds']),
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

    private function sikkepoUnavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Referensi pegawai sedang tidak tersedia.',
            'code' => 'sikkepo_unavailable',
        ], 502);
    }
}
