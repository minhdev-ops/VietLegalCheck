<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'app_links' => 'array',
            'markets' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ProjectProfile::class);
    }

    public function activityTags(): HasMany
    {
        return $this->hasMany(ProjectActivityTag::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->latest();
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class)->latest();
    }

    public function domainVerifications(): HasMany
    {
        return $this->hasMany(DomainVerification::class);
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(ProjectProcedure::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest();
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(Assessment::class)->latestOfMany();
    }

    /** @return array<int, string> */
    public function tagCodes(): array
    {
        return $this->activityTags()->pluck('tag_code')->all();
    }
}
