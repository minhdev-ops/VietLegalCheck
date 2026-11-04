<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LegalDocument;
use App\Models\LegalProvision;
use App\Services\LegalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalController extends ApiController
{
    private const PER_PAGE = 20;

    public function __construct(private LegalService $legal) {}

    /** Tra cứu văn bản công khai (FR-LAW-06): luôn kèm as_of_date. */
    public function documents(Request $request): JsonResponse
    {
        $asOf = $this->asOf($request);
        $term = $request->string('q')->toString();

        $documents = LegalDocument::query()
            ->with(['provisions' => fn ($query) => $query->effectiveAt($asOf)])
            ->when($term, function ($query) use ($term) {
                $query->where(fn ($builder) => $builder
                    ->where('title', 'ilike', "%{$term}%")
                    ->orWhere('doc_number', 'ilike', "%{$term}%"));
            })
            ->orderByDesc('issued_at')
            ->paginate(self::PER_PAGE);

        return $this->collection($documents->items(), [
            'as_of_date' => $asOf->toDateString(),
            'meta' => [
                'page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    public function document(Request $request, string $id): JsonResponse
    {
        $document = LegalDocument::with(['provisions' => fn ($query) => $query->effectiveAt($this->asOf($request))])
            ->findOrFail($id);

        return $this->ok(['document' => $document]);
    }

    public function provisions(Request $request): JsonResponse
    {
        $asOf = $this->asOf($request);
        $term = $request->string('q')->toString();

        $provisions = LegalProvision::query()
            ->with('document')
            ->effectiveAt($asOf)
            ->when($term, function ($query) use ($term) {
                $query->where(fn ($builder) => $builder
                    ->where('content', 'ilike', "%{$term}%")
                    ->orWhere('heading', 'ilike', "%{$term}%"));
            })
            ->limit(self::PER_PAGE)
            ->get();

        return $this->ok(['provisions' => $provisions, 'as_of_date' => $asOf->toDateString()]);
    }

    private function asOf(Request $request): Carbon
    {
        $date = $request->string('as_of_date')->toString();

        return $date !== '' ? Carbon::parse($date) : Carbon::today();
    }
}
