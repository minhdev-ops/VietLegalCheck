<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Thứ tự seed: văn bản → thẻ/câu hỏi/quy tắc → thủ tục → thiết lập → quản trị.
 * Nội dung pháp lý là MẪU (SRS §14) — đánh dấu `source_note`/`last_verified_at` trung thực.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LegalDocumentSeeder::class,
            ActivityTagSeeder::class,
            QuestionSetSeeder::class,
            RuleSeeder::class,
            ProcedureSeeder::class,
            SystemSettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
