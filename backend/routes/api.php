<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminDocumentController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AssessmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\LegalController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProcedureController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RuleController;
use App\Http\Controllers\Api\V1\ScanController;
use Illuminate\Support\Facades\Route;

/**
 * API công khai + xác thực (SRS 8).
 * Tiền tố `/api/v1` (config/cors.php + bootstrap/app.php).
 */
Route::prefix('v1')->name('api.v1.')->group(function () {

    // ---- Công khai ----
    Route::get('legal/documents', [LegalController::class, 'documents'])->name('legal.documents');
    Route::get('legal/documents/{id}', [LegalController::class, 'document'])->name('legal.document');
    Route::get('legal/provisions', [LegalController::class, 'provisions'])->name('legal.provisions');

    // ---- Xác thực ----
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:vlc-login')->name('auth.login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('auth/verify-email', [AuthController::class, 'verifyEmail'])->name('auth.verify-email');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/2fa/enable', [AuthController::class, 'enableTwoFactor'])->name('auth.2fa.enable');
        Route::post('auth/2fa/disable', [AuthController::class, 'disableTwoFactor'])->name('auth.2fa.disable');

        // ---- Tổ chức ----
        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::put('organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::get('organizations/{organization}/members', [OrganizationController::class, 'members'])->name('organizations.members');
        Route::post('organizations/{organization}/invite', [OrganizationController::class, 'invite'])->name('organizations.invite');
        Route::delete('organizations/{organization}/members/{userId}', [OrganizationController::class, 'removeMember'])->name('organizations.remove-member');
        Route::post('organizations/invitations/accept', [OrganizationController::class, 'acceptInvitation'])->name('organizations.accept');

        // ---- Dự án ----
        Route::apiResource('projects', ProjectController::class);

        // ---- Hồ sơ & phân loại ----
        Route::get('projects/{project}/profile', [ProfileController::class, 'show'])->name('projects.profile.show');
        Route::put('projects/{project}/profile', [ProfileController::class, 'update'])->name('projects.profile.update');
        Route::get('projects/{project}/tags', [ProfileController::class, 'tags'])->name('projects.tags');
        Route::post('projects/{project}/tags/override', [ProfileController::class, 'overrideTag'])->name('projects.tags.override');

        // ---- Đánh giá ----
        Route::get('projects/{project}/assessments', [AssessmentController::class, 'index'])->name('projects.assessments.index');
        Route::post('projects/{project}/assessments', [AssessmentController::class, 'store'])->name('projects.assessments.store');
        Route::get('projects/{project}/assessments/{assessment}', [AssessmentController::class, 'show'])->name('projects.assessments.show');
        Route::post('projects/{project}/assessments/{assessment}/run', [AssessmentController::class, 'run'])->name('projects.assessments.run');
        Route::patch('projects/{project}/assessments/{assessment}/findings/{finding}', [AssessmentController::class, 'updateFinding'])->name('projects.assessments.findings.update');

        // ---- Quét ----
        Route::get('projects/{project}/scans', [ScanController::class, 'index'])->name('projects.scans.index');
        Route::post('projects/{project}/scans', [ScanController::class, 'store'])->middleware('throttle:vlc-scan')->name('projects.scans.store');
        Route::get('projects/{project}/scans/{scan}', [ScanController::class, 'show'])->name('projects.scans.show');
        Route::post('projects/{project}/scans/{scan}/cancel', [ScanController::class, 'cancel'])->name('projects.scans.cancel');
        Route::post('projects/{project}/domain-verifications', [ScanController::class, 'verifyDomain'])->middleware('throttle:vlc-verify')->name('projects.domain.verify');
        Route::get('projects/{project}/domain-verifications/{domain}/check', [ScanController::class, 'checkDomain'])->middleware('throttle:vlc-verify')->name('projects.domain.check');

        // ---- Tài liệu ----
        Route::get('projects/{project}/documents', [DocumentController::class, 'index'])->name('projects.documents.index');
        Route::post('projects/{project}/documents', [DocumentController::class, 'store'])->name('projects.documents.store');
        Route::get('projects/{project}/documents/{fileId}/download', [DocumentController::class, 'download'])->name('projects.documents.download');
        Route::delete('projects/{project}/documents/{fileId}', [DocumentController::class, 'destroy'])->name('projects.documents.destroy');

        // ---- Thủ tục ----
        Route::get('procedures', [ProcedureController::class, 'index'])->name('procedures.index');
        Route::get('procedures/{id}', [ProcedureController::class, 'show'])->name('procedures.show');
        Route::get('projects/{project}/procedures', [ProcedureController::class, 'track'])->name('projects.procedures.track');
        Route::post('projects/{project}/procedures', [ProcedureController::class, 'storeTracked'])->name('projects.procedures.store');
        Route::patch('projects/{project}/procedures/{tracked}', [ProcedureController::class, 'updateTracked'])->name('projects.procedures.update');

        // ---- Báo cáo ----
        Route::get('projects/{project}/reports', [ReportController::class, 'index'])->name('projects.reports.index');
        Route::post('projects/{project}/assessments/{assessment}/reports', [ReportController::class, 'store'])->name('projects.reports.store');
        Route::get('projects/{project}/reports/{report}/download', [ReportController::class, 'download'])->name('projects.reports.download');

        // ---- AI ----
        Route::get('ai/conversations', [AiController::class, 'conversations'])->name('ai.conversations');
        Route::post('ai/conversations', [AiController::class, 'storeConversation'])->name('ai.conversations.store');
        Route::get('ai/conversations/{conversation}/messages', [AiController::class, 'messages'])->name('ai.conversations.messages');
        Route::post('ai/conversations/{conversation}/ask', [AiController::class, 'ask'])->middleware('throttle:vlc-ai')->name('ai.conversations.ask');

        // ---- Tác vụ & thông báo ----
        Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::post('jobs/{job}/cancel', [JobController::class, 'cancel'])->name('jobs.cancel');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // ---- Quản trị (system role) ----
        Route::middleware('admin')->group(function () {
            Route::get('admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
            Route::get('admin/audit', [AdminController::class, 'auditLog'])->name('admin.audit');
            Route::get('admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
            Route::put('admin/settings', [AdminController::class, 'updateSetting'])->name('admin.settings.update');
            Route::get('admin/users', [AdminController::class, 'users'])->name('admin.users');
            Route::get('admin/expert-reviews', [AdminController::class, 'expertReviews'])->name('admin.expert-reviews');
            Route::post('admin/expert-reviews', [AdminController::class, 'storeExpertReview'])->name('admin.expert-reviews.store');
            Route::patch('admin/expert-reviews/{review}', [AdminController::class, 'updateExpertReview'])->name('admin.expert-reviews.update');

            Route::apiResource('admin/legal-documents', AdminDocumentController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('admin/legal-documents/{document}/verify', [AdminDocumentController::class, 'verify'])->name('admin.documents.verify');

            Route::apiResource('admin/rules', RuleController::class)->only(['index', 'show', 'store']);
            Route::post('admin/rules/{rule}/versions', [RuleController::class, 'storeVersion'])->name('admin.rules.versions.store');
            Route::post('admin/rule-versions/{version}/test-cases', [RuleController::class, 'storeTestCase'])->name('admin.rule-versions.test-cases.store');
            Route::post('admin/rule-versions/{version}/run-tests', [RuleController::class, 'runTests'])->name('admin.rule-versions.run-tests');
            Route::post('admin/rule-versions/{version}/submit', [RuleController::class, 'submit'])->name('admin.rule-versions.submit');
            Route::post('admin/rule-versions/{version}/approve', [RuleController::class, 'approve'])->name('admin.rule-versions.approve');
            Route::post('admin/rule-versions/{version}/reject', [RuleController::class, 'reject'])->name('admin.rule-versions.reject');

            Route::get('admin/procedures', [AdminController::class, 'procedures'])->name('admin.procedures');
        });
    });
});
