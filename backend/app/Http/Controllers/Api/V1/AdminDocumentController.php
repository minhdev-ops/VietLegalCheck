<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LegalDocument;
use App\Services\AuditLogger;
use App\Services\LegalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDocumentController extends ApiController
{
    public function __construct(private AuditLogger $audit, private LegalService $legal) {}

    public function index(Request $request): JsonResponse
    {
        $documents = LegalDocument::query()
            ->withCount('provisions')
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where('title', 'ilike', "%{$term}%")->orWhere('doc_number', 'ilike', "%{$term}%"))
            ->orderByDesc('issued_at')
            ->paginate(20);

        return $this->collection($documents->items(), [
            'meta' => ['page' => $documents->currentPage(), 'last_page' => $documents->lastPage(), 'total' => $documents->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'doc_number' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:500'],
            'doc_type' => ['required', 'string', 'max:60'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'issued_at' => ['nullable', 'date'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['IN_EFFECT', 'NOT_IN_EFFECT', 'REPLACED', 'EXPIRED', 'PENDING'])],
            'official_url' => ['nullable', 'url', 'max:500'],
            'source_type' => ['nullable', Rule::in(['PRIMARY', 'SECONDARY'])],
            'notes' => ['nullable', 'string'],
        ]);

        $document = LegalDocument::create($data);
        $this->audit->log('DOCUMENT_CREATED', $document, ['doc_number' => $document->doc_number]);

        return $this->ok(['document' => $document], 201);
    }

    public function update(Request $request, LegalDocument $document): JsonResponse
    {
        $data = $request->validate([
            'doc_number' => ['sometimes', 'string', 'max:100'],
            'title' => ['sometimes', 'string', 'max:500'],
            'doc_type' => ['sometimes', 'string', 'max:60'],
            'issuer' => ['sometimes', 'nullable', 'string', 'max:200'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
            'effective_from' => ['sometimes', 'nullable', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'string'],
            'official_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $changes = $document->update($data);
        if ($changes) {
            $this->legal->logUpdate($document, 'Cập nhật văn bản', $request->user()->id);
        }
        $this->audit->log('DOCUMENT_UPDATED', $document);

        return $this->ok(['document' => $document->fresh()]);
    }

    public function verify(Request $request, LegalDocument $document): JsonResponse
    {
        $document->update(['last_verified_at' => Carbon::now(), 'verified_by' => $request->user()->id]);
        $this->audit->log('DOCUMENT_VERIFIED', $document);

        return $this->ok(['document' => $document->fresh()]);
    }

    public function destroy(LegalDocument $document): JsonResponse
    {
        $document->delete();
        $this->audit->log('DOCUMENT_DELETED', $document);

        return $this->ok();
    }
}
