<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum mặc định dùng id tăng dần —不合 với khóa chính UUID của User (SRS 7.1).
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    protected $guarded = [];
}
