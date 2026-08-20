<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SikkepoPlatformException;
use App\Http\Controllers\Controller;
use App\Services\SikkepoPlatformClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitReferenceController extends Controller
{
    public function index(Request $request, SikkepoPlatformClient $client): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'uuid'],
            'recursive' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json($client->units($filters));
        } catch (SikkepoPlatformException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Referensi unit sedang tidak tersedia.',
                'code' => 'sikkepo_unavailable',
            ], 502);
        }
    }
}
