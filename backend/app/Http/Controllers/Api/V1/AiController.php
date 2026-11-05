<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AiConversation;
use App\Models\Project;
use App\Services\AiService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiController extends ApiController
{
    public function __construct(private AiService $ai, private AuditLogger $audit) {}

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = AiConversation::where('user_id', $user->id)
            ->orWhereIn('organization_id', $user->organizations()->pluck('organizations.id'))
            ->latest('updated_at')
            ->with('messages')
            ->get();

        return $this->collection($conversations);
    }

    public function storeConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
        ]);

        $projectId = $data['project_id'] ?? null;
        $organizationId = $request->user()->personalOrganization()?->id ?? $request->user()->organizations()->first()->id;

        if ($projectId !== null) {
            $project = Project::find($projectId);
            abort_unless($project !== null && $project->organization_id === $organizationId, 403);
        }

        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'title' => $data['title'] ?? 'Trò chuyện mới',
        ]);

        return $this->ok(['conversation' => $conversation], 201);
    }

    public function messages(AiConversation $conversation): JsonResponse
    {
        $this->guard($conversation);

        return $this->collection($conversation->messages()->orderBy('created_at')->get());
    }

    public function ask(Request $request, AiConversation $conversation): JsonResponse|StreamedResponse
    {
        $this->guard($conversation);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:5', 'max:1000'],
            'as_of_date' => ['nullable', 'date'],
            'stream' => ['nullable', 'boolean'],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['question'],
        ]);

        if (filter_var($data['stream'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return response()->streamDownload(function () use ($conversation, $data) {
                foreach ($this->ai->streamAnswer($conversation, $data['question'], $data) as $chunk) {
                    echo 'data: '.json_encode($chunk, JSON_UNESCAPED_UNICODE)."\n\n";
                    ob_flush();
                    flush();
                }
            }, 'ai-stream.txt', ['Content-Type' => 'text/event-stream']);
        }

        $message = $this->ai->answer($conversation, $data['question'], $data);
        $this->audit->log('AI_ASKED', $conversation, ['refused' => $message->refused]);

        return $this->ok(['message' => $message->load('retrievals')]);
    }

    private function guard(AiConversation $conversation): void
    {
        $user = request()->user();
        $inOrg = $user->organizations()->where('organizations.id', $conversation->organization_id)->exists();
        abort_unless($conversation->user_id === $user->id || $inOrg, 403);
    }
}
