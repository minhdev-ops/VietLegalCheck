<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            SystemSetting::put($key, $value);
        }
    }

    private const SETTINGS = [
        'ai.enabled_globally' => true,
        'ai.refusal_when_unverified' => true,
        'report.template_version' => 1,
        'rules.two_person_approval' => true,
        'legal.stale_days' => 365,
        'scan.light_max_pages' => 5,
        'scan.deep_max_pages' => 15,
        'notification.digest_daily' => true,
    ];
}
