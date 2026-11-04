<?php

namespace Database\Seeders;

use App\Domain\Enums\OrgRole;
use App\Domain\Enums\SystemRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tài khoản quản trị/chuyên gia mẫu (SRS §14). Mật khẩu chỉ dùng demo; 2FA bắt buộc
 * với vai trò hành chính theo FR-AUTH-04 nên `two_factor_secret` được tạo sẵn.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP'; // mã OTP cố định của tài khoản mẫu, đổi khi triển khai.

        $systemOrganization = Organization::firstOrCreate(['name' => 'VietLegal Check (hệ thống)'], [
            'is_personal' => false,
            'settings' => ['ai_enabled' => true],
        ]);

        $users = [
            [
                'name' => 'Quản trị hệ thống',
                'email' => 'admin@vietlegal.local',
                'password' => 'admin-password-change-me',
                'system_role' => SystemRole::SYS_ADMIN,
            ],
            [
                'name' => 'Quản trị pháp lý',
                'email' => 'legal.admin@vietlegal.local',
                'password' => 'legal-admin-password-change-me',
                'system_role' => SystemRole::LEGAL_ADMIN,
            ],
            [
                'name' => 'Chuyên gia pháp lý',
                'email' => 'expert@vietlegal.local',
                'password' => 'expert-password-change-me',
                'system_role' => SystemRole::LEGAL_EXPERT,
            ],
        ];

        foreach ($users as $user) {
            $model = User::updateOrCreate(['email' => $user['email']], [
                'name' => $user['name'],
                'password' => Hash::make($user['password']),
                'system_role' => $user['system_role'],
                'email_verified_at' => now(),
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
            ]);
            $model->locked_until = null;
            $model->failed_login_attempts = 0;
            $model->save();

            $systemOrganization
                ->members()
                ->firstOrCreate(['user_id' => $model->id], ['role' => OrgRole::OWNER]);
        }
    }
}
