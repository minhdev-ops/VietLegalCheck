<?php

namespace App\Models;

use App\Domain\Enums\Applicability;
use App\Domain\Enums\Fulfillment;
use App\Domain\Enums\Severity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentFinding extends BaseModel
{
    protected function casts(): array
    {
        return [
            'applicability' => Applicability::class,
            'fulfillment' => Fulfillment::class,
            'severity' => Severity::class,
            'trace_json' => 'array',
            'obligation_json' => 'array',
            'legal_basis' => 'array',
            'required_info' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(LegalRuleVersion::class, 'rule_version_id');
    }

    public function isObligation(): bool
    {
        return $this->applicability === Applicability::APPLICABLE;
    }
}
