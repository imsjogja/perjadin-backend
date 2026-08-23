<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnverifySppdAction;
use App\Actions\VerifySppdAction;
use App\Http\Controllers\Controller;
use App\Models\Sppd;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SppdVerificationController extends Controller
{
    public function update(Sppd $sppd, Request $request, VerifySppdAction $action): JsonResponse
    {
        try {
            return response()->json([
                'data' => $action->handle($sppd, $request->user()),
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'invalid_sppd_status',
            ], 409);
        }
    }

    public function destroy(Sppd $sppd, UnverifySppdAction $action): JsonResponse
    {
        try {
            return response()->json([
                'data' => $action->handle($sppd),
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'invalid_sppd_status',
            ], 409);
        }
    }
}
