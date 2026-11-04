<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiRetrieval;
use App\Models\LegalDocument;
use App\Models\LegalProvision;
use App\Models\Organization;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Trợ lý pháp lý AI (SRS 4.11). RAG: Laravel chọn nguồn (lọc hiệu lực tại `as_of_date`),
 * dịch vụ Python tạo câu trả lời và trích dẫn. AI không ghi trạng thái (BR-21).
 */
final class AiService
{
    private const RELEVANT_MIN_HITS = 1;

    public function __construct(private InternalServiceClient $client) {}

    /**
     * @return array{sources: array<int, array<string, mixed>>, as_of_date: string}
     */
    public function retrieve(string $question, ?Carbon $asOfDate = null): array
    {
        $asOfDate ??= Carbon::today();
        $query = $this->sanitize($question);

        $documents = LegalDocument::query()
            ->where('source_type', 'PRIMARY')
            ->where(fn ($builder) => $builder->whereNull('effective_to')->orWhere('effective_to', '>=', $asOfDate->toDateString()))
            ->get();

        $provisions = LegalProvision::with('document')
            ->whereIn('legal_document_id', $documents->pluck('id'))
            ->get()
            ->filter(fn (LegalProvision $provision) => $provision->isEffectiveOn($asOfDate))
            ->sortByDesc(fn (LegalProvision $provision) => $this->score($query, $provision))
            ->take(5)
            ->values();

        return [
            'sources' => $provisions->map(fn (LegalProvision $provision) => [
                'id' => $provision->id,
                'doc_number' => $provision->document?->doc_number,
                'doc_title' => $provision->document?->title,
                'article' => $provision->article,
                'clause' => $provision->clause,
                'point' => $provision->point,
                'content' => $provision->content,
                'effective_from' => $provision->effective_from?->toDateString() ?? $provision->document?->effective_from?->toDateString(),
                'official_url' => $provision->document?->official_url,
            ])->all(),
            'as_of_date' => $asOfDate->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function answer(AiConversation $conversation, string $question, array $context = []): AiMessage
    {
        /** @var Organization $organization */
        $organization = $conversation->organization;

        if (! $organization->aiEnabled()) {
            return $this->persistRefusal($conversation, $question, 'Tổ chức đã tắt tính năng AI (FR-AI-08).');
        }

        $retrieval = $this->retrieve($question, isset($context['as_of_date']) ? Carbon::parse($context['as_of_date']) : null);

        if ($retrieval['sources'] === []) {
            return $this->persistRefusal($conversation, $question, 'Chưa đủ căn cứ: không có điều khoản hợp lệ tại ngày đánh giá.');
        }

        try {
            $payload = $this->client->postJson('/internal/ai/answer', [
                'question' => $question,
                'as_of_date' => $retrieval['as_of_date'],
                'stream' => false,
                'sources' => $retrieval['sources'],
            ]);
        } catch (ConnectionException|RuntimeException $exception) {
            Log::warning('AI service unavailable: '.$exception->getMessage());

            return $this->persistRefusal($conversation, $question, 'Dịch vụ AI không phản hồi. Vui lòng thử lại sau.');
        }

        $message = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => (string) ($payload['answer'] ?? ''),
            'citations' => $payload['cited_source_ids'] ?? [],
            'categories' => $payload['categories'] ?? null,
            'refused' => (bool) ($payload['refused'] ?? false),
            'model' => (string) ($payload['model'] ?? 'unknown'),
        ]);

        $this->storeRetrievals($message, $retrieval['sources'], (array) ($payload['cited_source_ids'] ?? []));

        return $message;
    }

    /**
     * Streaming (NFR-04): trả về generator chunk. Cũng lưu message cuối cùng.
     *
     * @param  array<string, mixed>  $context
     * @return \Generator<int, array{type: string, text?: string, final?: array<string, mixed>}>
     */
    public function streamAnswer(AiConversation $conversation, string $question, array $context = []): \Generator
    {
        /** @var Organization $organization */
        $organization = $conversation->organization;

        if (! $organization->aiEnabled()) {
            yield ['type' => 'final', 'refused' => true, 'reason' => 'Tổ chức đã tắt tính năng AI.'];

            return;
        }

        $retrieval = $this->retrieve($question, isset($context['as_of_date']) ? Carbon::parse($context['as_of_date']) : null);

        if ($retrieval['sources'] === []) {
            yield ['type' => 'final', 'refused' => true, 'reason' => 'Chưa đủ căn cứ để trả lời.'];

            return;
        }

        // Dịch vụ hỗ trợ streaming theo hợp đồng; ở đây tách chunk từ câu trả lời đầy đủ
        // để chạy được cả khi dịch vụ chỉ trả về JSON (không bắt buộc SSE nội bộ).
        $answer = $this->answer($conversation, $question, $context);

        foreach (str_split($answer->content, 90) as $chunk) {
            yield ['type' => 'delta', 'text' => $chunk];
        }

        yield [
            'type' => 'final',
            'refused' => $answer->refused,
            'cited_source_ids' => $answer->citations ?? [],
            'categories' => $answer->categories,
        ];
    }

    private function persistRefusal(AiConversation $conversation, string $question, string $reason): AiMessage
    {
        return AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reason,
            'citations' => [],
            'refused' => true,
            'model' => 'none',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int, string>  $citedIds
     */
    private function storeRetrievals(AiMessage $message, array $sources, array $citedIds): void
    {
        DB::transaction(function () use ($message, $sources, $citedIds) {
            foreach ($sources as $index => $source) {
                AiRetrieval::create([
                    'message_id' => $message->id,
                    'provision_id' => $source['id'],
                    'score' => count($sources) - $index,
                    'snippet' => isset($source['content']) ? substr((string) $source['content'], 0, 300) : null,
                ]);
            }
        });

        unset($citedIds);
    }

    private function score(string $query, LegalProvision $provision): int
    {
        $haystack = mb_strtolower($provision->content.' '.($provision->heading ?? '').' '.($provision->document?->title ?? ''));
        $hits = 0;

        foreach ($this->tokens($query) as $token) {
            if (mb_strpos($haystack, $token) !== false) {
                $hits++;
            }
        }

        return $hits + ($provision->document?->isVerifiedStale(365) === false ? 1 : 0);
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $query): array
    {
        $stop = ['toi', 'minh', 'ban', 'la', 'cua', 'va', 'co', 'khong', 'phai', 'khi', 'thi', 'mot', 'cach', 'nhung', 'gi', 'the', 'nao', 'duoc', 'can'];
        $tokens = preg_split('/\s+/u', trim($this->unaccent(mb_strtolower($query)))) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $token) => mb_strlen($token) >= 3 && ! in_array($token, $stop, true))));
    }

    private function unaccent(string $value): string
    {
        $map = [
            'a' => ['à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ'],
            'e' => ['è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ'],
            'i' => ['ì', 'í', 'ị', 'ỉ', 'ĩ'],
            'o' => ['ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ'],
            'u' => ['ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ'],
            'y' => ['ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ'],
            'd' => ['đ'],
        ];

        foreach ($map as $plain => $chars) {
            $value = str_replace($chars, $plain, $value);
        }

        return $value;
    }

    private function sanitize(string $question): string
    {
        return trim(mb_substr($question, 0, 1000));
    }
}
