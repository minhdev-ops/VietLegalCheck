<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $secret = $this->totp->generateSecret();
            $user->update(['two_factor_secret' => $secret]);

            return $this->ok([
                'requires_two_factor_setup' => true,
                'secret' => $secret,
                'otpauth' => $this->totp->otpauthUri($user->email, $secret),
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

        return $this->ok();
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(['user' => $this->userPayload($request->user())]);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        if (! $user->two_factor_secret || ! $this->totp->verify((string) $user->two_factor_secret, (string) $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Mã không đúng.']);
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->totp->recoveryCodes(),
        ]);
        $this->audit->log('USER_2FA_ENABLED', $user);

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

        return $this->ok();
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
