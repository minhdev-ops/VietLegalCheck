<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Khóa chính UUID cho toàn bộ bảng nghiệp vụ (SRS 7.1).
 */
abstract class BaseModel extends Model
{
    use HasUuids;

    protected $guarded = [];
}
