<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BackgroundJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends ApiController
{
    public function show(Request $request, BackgroundJob $job): JsonResponse
    {
        $user = $request->user();
        $canSee = $job->user_id === $user->id
            || $user->isSystemAdmin()
            || $user->memberships()->where('organization_id', $job->organization_id)->exists();

        abort_unless($canSee, 403);

        return $this->ok(['job' => $job]);
    }

    public function cancel(Request $request, BackgroundJob $job): JsonResponse
    {
        $user = $request->user();
        $canSee = $job->user_id === $user->id
            || $user->isSystemAdmin()
            || $user->memberships()->where('organization_id', $job->organization_id)->exists();

        abort_unless($canSee, 403);

        abort_if($job->status->isTerminal(), 422, 'Tác vụ đã kết thúc.');

        $job->update(['status' => \App\Domain\Enums\JobStatus::CANCELLED, 'finished_at' => now()]);

        return $this->ok();
    }
}
