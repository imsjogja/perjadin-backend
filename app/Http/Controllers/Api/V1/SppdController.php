<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateSppdAction;
use App\Actions\UpdateSppdDraftAction;
use App\Exceptions\SikkepoPlatformException;
use App\Exceptions\SppdAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSppdRequest;
use App\Models\Sppd;
use App\Models\Spt;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SppdController extends Controller
{
    public function store(Spt $spt, StoreSppdRequest $request, CreateSppdAction $action): JsonResponse
    {
        try {
            return response()->json([
                'data' => $action->handle($spt, $request->validated()),
            ], 201);
        } catch (SikkepoPlatformException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Referensi pegawai sedang tidak tersedia.',
                'code' => 'sikkepo_unavailable',
            ], 502);
        } catch (SppdAlreadyExistsException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->apiCode,
            ], 409);
        }
    }

    public function show(Sppd $sppd): JsonResponse
    {
        return response()->json([
            'data' => $sppd->load(['spt.destination', 'followers', 'signatory', 'verifier']),
        ]);
    }

    public function update(Sppd $sppd, StoreSppdRequest $request, UpdateSppdDraftAction $action): JsonResponse
    {
        try {
            return response()->json([
                'data' => $action->handle($sppd, $request->validated()),
            ]);
        } catch (SikkepoPlatformException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Referensi pegawai sedang tidak tersedia.',
                'code' => 'sikkepo_unavailable',
            ], 502);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'invalid_sppd_status',
            ], 409);
        }
    }

    public function destroy(Sppd $sppd): JsonResponse|Response
    {
        if ($sppd->status !== Sppd::STATUS_DRAFT) {
            return response()->json([
                'message' => 'Hanya draft SPPD yang dapat dihapus.',
                'code' => 'invalid_sppd_status',
            ], 409);
        }

        $sppd->delete();

        return response()->noContent();
    }
}
