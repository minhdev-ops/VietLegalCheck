<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Nhật ký hoạt động quan trọng, không ghi nội dung nhạy cảm (SRS 5.2, FR-LAW-05).
 */
final class AuditLogger
{
    public function log(
        string $action,
        ?Model $entity = null,
        array $meta = [],
        ?string $organizationId = null,
    ): AuditLog {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $organizationId ??= $this->organizationId($entity);

        return AuditLog::create([
            'actor_id' => $user?->id,
            'actor_role' => $user?->system_role?->value,
            'action' => $action,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
            'organization_id' => $organizationId,
            'ip_address' => Request::ip(),
            'meta' => $meta,
        ]);
    }

    private function organizationId(?Model $entity = null): ?string
    {
        // Phiên API Sanctum không có session: lấy tổ chức từ thực thể khi có,
        // các action hệ thống (login, 2FA) giữ null.
        return session('vlc.current_organization_id')
            ?? ($entity !== null && isset($entity->organization_id) ? (string) $entity->organization_id : null);
    }

    public function withOrganization(string $organizationId, callable $callback): void
    {
        session(['vlc.current_organization_id' => $organizationId]);
        try {
            $callback();
        } finally {
            session()->forget('vlc.current_organization_id');
        }
    }
}
