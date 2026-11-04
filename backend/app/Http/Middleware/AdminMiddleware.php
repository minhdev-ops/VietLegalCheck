<?php

namespace App\Http\Middleware;

use App\Domain\Enums\SystemRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ cho phép vai trò hệ thống (quản trị pháp lý, chuyên gia, quản trị viên).
 * SRS 5.2 + sơ đồ RBAC: endpoint /admin không mở cho người dùng thường.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->system_role, [SystemRole::SYS_ADMIN, SystemRole::LEGAL_ADMIN, SystemRole::LEGAL_EXPERT], true)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Yêu cầu quyền quản trị pháp lý.',
                    'details' => null,
                    'request_id' => $request->id(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
