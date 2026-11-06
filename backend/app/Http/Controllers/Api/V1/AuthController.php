<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TotpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Random\RandomException;

class AuthController extends ApiController
{
    private const LOCK_THRESHOLD = 5;

    public function __construct(private TotpService $totp, private AuditLogger $audit) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc,dns', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'max:72', 'confirmed'],
            'terms' => ['required', 'accepted'],
        ]);

        return DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'system_role' => \App\Domain\Enums\SystemRole::USER,
                'email_verification_token' => \Illuminate\Support\Str::uuid(),
            ]);

            $organization = \App\Models\Organization::create([
                'name' => $data['name'],
                'is_personal' => true,
                'settings' => ['ai_enabled' => true],
            ]);
            $organization->members()->create([
                'user_id' => $user->id,
                'role' => \App\Domain\Enums\OrgRole::OWNER,
            ]);

            $this->audit->log('USER_REGISTERED', $user, ['email' => $user->email], $organization->id);

            $token = $user->createToken('web')->plainTextToken;

            return $this->ok([
                'token' => $token,
                'user' => $this->userPayload($user),
            ], 201);
        });
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'size:6'],
        ]);

        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->registerFailure($request, $user);

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản tạm khóa do nhập sai nhiều lần. Thử lại sau '.max(1, (int) now()->diffInMinutes($user->locked_until, false)).' phút.',
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            if (! isset($data['two_factor_code']) || ! $this->totp->verify((string) $user->two_factor_secret, (string) $data['two_factor_code'])) {
                $this->registerFailure($request, $user);

                throw ValidationException::withMessages([
                    'two_factor_code' => 'Mã xác thực hai bước không đúng.',
                ]);
            }
        }

        if ($user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            // Tài khoản quản trị/nội bộ chưa bật 2FA: yêu cầu thiết lập trước (FR-AUTH-04).
            // Giữ lại secret đã tạo (chưa xác nhận) để người dùng không phải quét lại;
            // cấp token tạm thời duy nhất quyền thiết lập 2FA thay vì phiên đăng nhập.
            if (! $user->two_factor_secret) {
                $user->update(['two_factor_secret' => $this->totp->generateSecret()]);
                $user->refresh();
            }

            $setupToken = $user->createToken('2fa-setup', ['setup-2fa'], now()->addMinutes(15))->plainTextToken;

            return $this->ok([
                'requires_two_factor_setup' => true,
                'setup_token' => $setupToken,
                'email' => $user->email,
                'secret' => $user->two_factor_secret,
                'otpauth' => $this->totp->otpauthUri($user->email, $user->two_factor_secret),
            ]);
        }

        $user->update(['failed_login_attempts' => 0, 'last_login_at' => now()]);
        RateLimiter::clear($this->throttleKey($request));

        $token = $user->createToken('web', ['*'], now()->addDays(7))->plainTextToken;
        $this->audit->log('USER_LOGIN', $user);

        return $this->ok(['token' => $token, 'user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $this->audit->log('USER_LOGOUT', $request->user());

        return $this->ok(['message' => 'Đăng xuất thành công.']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(['user' => $this->userPayload($request->user())]);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        // Cho phép người dùng tự bật, hoặc hoàn tất thiết lập bắt buộc bằng token tạm
        // chỉ có quyền 'setup-2fa' (FR-AUTH-04).
        abort_unless(
            $user->requiresTwoFactor() || $user->hasTwoFactorEnabled() === false || $user->tokenCan('setup-2fa') || $user->isSystemAdmin(),
            403,
            'Không cần thiết lập xác thực hai bước lúc này.',
        );
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        if (! $user->two_factor_secret || ! $this->totp->verify((string) $user->two_factor_secret, (string) $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Mã không đúng.']);
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->totp->recoveryCodes(),
        ]);
        $this->audit->log('USER_2FA_ENABLED', $user);

        // Token thiết lập tạm thời chỉ dùng một lần (FR-AUTH-04).
        if ($user->tokenCan('setup-2fa')) {
            $user->currentAccessToken()?->delete();
        }

        return $this->ok(['recovery_codes' => $user->two_factor_recovery_codes]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        if (! $this->totp->verify((string) $user->two_factor_secret, (string) $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Mã không đúng.']);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);
        $this->audit->log('USER_2FA_DISABLED', $user);

        return $this->ok(['message' => 'Đã tắt xác thực hai bước.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'system_role' => $user->system_role?->value,
            'email_verified' => $user->email_verified_at !== null,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'created_at' => $user->created_at?->toIso8601String(),
            'organizations' => $user->organizations()->get()->map(fn ($org) => [
                'id' => $org->id,
                'name' => $org->name,
                'role' => $org->pivot->role instanceof OrgRole ? $org->pivot->role->value : (string) $org->pivot->role,
                'is_personal' => $org->is_personal,
            ]),
        ];
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $user = User::where('email', $data['email'])->first();

        // Tránh rò rỉ tài khoản tồn tại; luôn trả 200 (OWASP).
        if ($user) {
            $token = str()->random(48);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => $token, 'created_at' => now()],
            );
            Mail::raw(
                'Đặt lại mật khẩu VietLegal Check. Token: '.$token,
                fn ($message) => $message->to($user->email)->subject('Đặt lại mật khẩu'),
            );
            $this->audit->log('PASSWORD_RESET_REQUESTED', $user, ['email' => $user->email]);
        }

        return $this->ok(['sent' => true]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:10', 'max:72', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        $valid = $record !== null
            && hash_equals((string) $record->token, $data['token'])
            && Carbon::parse($record->created_at)->addMinutes(60)->isFuture();

        abort_unless($valid, 422, 'Token đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['password' => Hash::make($data['password']), 'locked_until' => null, 'failed_login_attempts' => 0]);
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        $this->audit->log('PASSWORD_RESET', $user);

        return $this->ok(['message' => 'Đặt lại mật khẩu thành công.']);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:100']]);
        $user = User::where('email_verification_token', $data['token'])->first();
        abort_unless($user !== null, 422, 'Token xác nhận email không hợp lệ.');

        $user->forceFill(['email_verified_at' => now(), 'email_verification_token' => null])->save();
        $this->audit->log('EMAIL_VERIFIED', $user);

        return $this->ok(['verified' => true]);
    }

    private function registerFailure(Request $request, ?User $user): void
    {
        $executed = RateLimiter::hit($this->throttleKey($request), 900);

        if ($user && $executed >= self::LOCK_THRESHOLD) {
            $user->update([
                'locked_until' => now()->addMinutes(15),
                'failed_login_attempts' => $executed,
            ]);
            $this->audit->log('USER_LOCKED', $user, ['attempts' => $executed]);
        }
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.$request->ip().'|'.(string) $request->input('email');
    }
}
