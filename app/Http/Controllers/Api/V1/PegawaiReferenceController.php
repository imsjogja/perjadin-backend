<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SikkepoPlatformException;
use App\Http\Controllers\Controller;
use App\Services\SikkepoPlatformClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PegawaiReferenceController extends Controller
{
    public function index(Request $request, SikkepoPlatformClient $client): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'nip' => ['nullable', 'string', 'max:32'],
            'unit_id' => ['nullable', 'uuid'],
            'aktif' => ['nullable', 'boolean'],
            'updated_since' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:nama,nip,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return response()->json($client->pegawai($filters));
        } catch (SikkepoPlatformException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Referensi pegawai sedang tidak tersedia.',
                'code' => 'sikkepo_unavailable',
            ], 502);
        }
    }
}
