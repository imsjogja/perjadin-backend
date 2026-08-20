<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddSptAssigneesAction;
use App\Exceptions\SikkepoPlatformException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSptAssigneesRequest;
use App\Models\Spt;
use Illuminate\Http\JsonResponse;

class SptAssigneeController extends Controller
{
    public function index(Spt $spt): JsonResponse
    {
        return response()->json([
            'data' => $spt->assignees()->orderBy('assigned_at')->get(),
        ]);
    }

    public function store(
        Spt $spt,
        StoreSptAssigneesRequest $request,
        AddSptAssigneesAction $action
    ): JsonResponse {
        try {
            return response()->json([
                'data' => $action->handle(
                    $spt,
                    $request->validated('nips'),
                    $request->user()?->getKey()
                ),
            ], 201);
        } catch (SikkepoPlatformException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Referensi pegawai sedang tidak tersedia.',
                'code' => 'sikkepo_unavailable',
            ], 502);
        }
    }
}
