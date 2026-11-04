<?php

namespace App\Models;

use App\Domain\Enums\RuleVersionStatus;
use App\Domain\Enums\Severity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalRuleVersion extends BaseModel
{
    protected function casts(): array
    {
        return [
            'condition_json' => 'array',
            'result_json' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => RuleVersionStatus::class,
            'severity' => Severity::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LegalRule::class, 'rule_id');
    }

    public function provisions(): BelongsToMany
    {
        // Pivot UUID: BaseModel::HasUuids sinh id; pivot mặc định thì không.
        return $this->belongsToMany(LegalProvision::class, 'rule_provisions', 'rule_version_id', 'provision_id')
            ->using(RuleProvision::class)
            ->withTimestamps();
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(RuleTestCase::class, 'rule_version_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RuleApproval::class, 'rule_version_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Phiên bản có hiệu lực tại ngày cho trước (BR-18). */
    public function isEffectiveOn(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');
        $from = $this->effective_from->format('Y-m-d');
        $to = $this->effective_to?->format('Y-m-d');

        return $from <= $day && ($to === null || $to >= $day);
    }

    public function label(): string
    {
        return 'v'.$this->version;
    }
}
