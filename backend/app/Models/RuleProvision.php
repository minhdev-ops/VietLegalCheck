<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot `rule_provisions` với UUID (HasUuids qua BaseModel không áp dụng cho pivot mặc định).
 */
class RuleProvision extends Pivot
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';
}
