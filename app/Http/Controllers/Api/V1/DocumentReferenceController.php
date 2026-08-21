<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentReferenceController extends Controller
{
    /**
     * @var array<string, array{category: string, label: string}>
     */
    private const TYPES = [
        'mata-anggaran' => [
            'category' => DocumentReference::CATEGORY_BUDGET_ACCOUNT,
            'label' => 'Mata anggaran',
        ],
        'transportasi' => [
            'category' => DocumentReference::CATEGORY_TRANSPORTATION,
            'label' => 'Transportasi',
        ],
        'tingkat-perjalanan' => [
            'category' => DocumentReference::CATEGORY_TRAVEL_LEVEL,
            'label' => 'Tingkat perjalanan',
        ],
        'jenis-perjalanan' => [
            'category' => DocumentReference::CATEGORY_TRAVEL_TYPE,
            'label' => 'Jenis perjalanan',
        ],
    ];

    public function index(string $referenceType): JsonResponse
    {
        $type = $this->type($referenceType);

        return response()->json([
            'data' => DocumentReference::query()
                ->where('category', $type['category'])
                ->orderBy('value')
                ->get(),
            'meta' => [
                'type' => $referenceType,
                'label' => $type['label'],
            ],
        ]);
    }

    public function store(string $referenceType, Request $request): JsonResponse
    {
        $type = $this->type($referenceType);
        $validated = $this->validateValue($request, $type['category']);

        $reference = DocumentReference::query()->create([
            'category' => $type['category'],
            'value' => trim($validated['value']),
        ]);

        return response()->json([
            'data' => $reference,
        ], 201);
    }

    public function update(
        string $referenceType,
        DocumentReference $documentReference,
        Request $request
    ): JsonResponse {
        $type = $this->type($referenceType);
        $this->ensureType($documentReference, $type['category']);
        $validated = $this->validateValue($request, $type['category'], $documentReference);

        $documentReference->update([
            'value' => trim($validated['value']),
        ]);

        return response()->json([
            'data' => $documentReference->fresh(),
        ]);
    }

    public function destroy(string $referenceType, DocumentReference $documentReference): JsonResponse
    {
        $type = $this->type($referenceType);
        $this->ensureType($documentReference, $type['category']);

        $documentReference->delete();

        return response()->json([], 204);
    }

    /**
     * @return array{category: string, label: string}
     */
    private function type(string $referenceType): array
    {
        abort_unless(isset(self::TYPES[$referenceType]), 404);

        return self::TYPES[$referenceType];
    }

    private function ensureType(DocumentReference $reference, string $category): void
    {
        abort_unless($reference->category === $category, 404);
    }

    /**
     * @return array{value: string}
     */
    private function validateValue(
        Request $request,
        string $category,
        ?DocumentReference $reference = null
    ): array {
        if (is_string($request->input('value'))) {
            $request->merge([
                'value' => trim($request->input('value')),
            ]);
        }

        return $request->validate([
            'value' => [
                'required',
                'string',
                'max:200',
                Rule::unique('document_references', 'value')
                    ->where('category', $category)
                    ->ignore($reference?->id),
            ],
        ]);
    }
}
