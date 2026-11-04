<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\OrgRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrganizationController extends ApiController
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        return $this->collection($request->user()->organizations()->withCount('projects')->get());
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->guard($organization);
        $organization->load(['members.user', 'invitations']);

        return $this->ok(['organization' => $organization]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->guard($organization, OrgRole::OWNER);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'settings' => ['nullable', 'array'],
        ]);

        $organization->update($data);
        $this->audit->log('ORGANIZATION_UPDATED', $organization);

        return $this->ok(['organization' => $organization->fresh()]);
    }

    public function members(Organization $organization): JsonResponse
    {
        $this->guard($organization);

        return $this->collection($organization->members()->with('user')->get());
    }

    public function invite(Request $request, Organization $organization): JsonResponse
    {
        $this->guard($organization, OrgRole::ADMIN);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['nullable', 'string', 'in:VIEWER,MEMBER,ADMIN'],
        ]);

        $invitation = $organization->invitations()->create([
            'email' => $data['email'],
            'role' => $data['role'] ?? 'MEMBER',
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
            'invited_by' => $request->user()->id,
        ]);

        $this->audit->log('MEMBER_INVITED', $invitation, ['email' => $data['email']], $organization->id);

        Notification::route('mail', $data['email'])->notify(new \App\Notifications\OrganizationInvitationNotification($invitation));

        return $this->ok(['invitation' => $invitation], 201);
    }

    public function acceptInvitation(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:40']]);
        $invitation = OrganizationInvitation::where('token', $data['token'])->where('expires_at', '>', now())->firstOrFail();

        $organization = $invitation->organization;
        $organization->members()->firstOrCreate([
            'user_id' => $request->user()->id,
        ], [
            'role' => OrgRole::from($invitation->role),
        ]);

        $invitation->delete();
        $this->audit->log('MEMBER_JOINED', $request->user(), [], $organization->id);

        return $this->ok(['organization' => $organization->fresh()]);
    }

    public function removeMember(Organization $organization, string $userId): JsonResponse
    {
        $this->guard($organization, OrgRole::OWNER);
        abort_if($userId === $request()->user()->id, 422, 'Không thể tự xóa mình; dùng chuyển quyền sở hữu.');

        $organization->members()->where('user_id', $userId)->delete();
        $this->audit->log('MEMBER_REMOVED', $organization, ['user_id' => $userId]);

        return $this->ok();
    }

    private function guard(Organization $organization, ?OrgRole $required = null): void
    {
        $membership = request()->user()->memberships()->where('organization_id', $organization->id)->first();

        if (! $membership) {
            abort(403, 'Không thuộc tổ chức này.');
        }

        if ($required && $membership->role !== OrgRole::OWNER && $membership->role !== OrgRole::ADMIN && $required !== OrgRole::ADMIN) {
            abort(403, 'Cần quyền quản trị tổ chức.');
        }
    }
}
