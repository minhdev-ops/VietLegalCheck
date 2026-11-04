<?php

namespace App\Models;

use App\Domain\Enums\OrgRole;
use App\Domain\Enums\SystemRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'system_role' => SystemRole::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
            'failed_login_attempts' => 'integer',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function roleIn(Organization|string $organization): ?OrgRole
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->memberships()->where('organization_id', $organizationId)->first()?->role;
    }

    public function isSystemAdmin(): bool
    {
        return $this->system_role === SystemRole::SYS_ADMIN;
    }

    public function isLegalAdmin(): bool
    {
        return $this->system_role === SystemRole::LEGAL_ADMIN;
    }

    public function isLegalExpert(): bool
    {
        return $this->system_role === SystemRole::LEGAL_EXPERT;
    }

    public function requiresTwoFactor(): bool
    {
        return $this->system_role->isAdministrative();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function personalOrganization(): ?Organization
    {
        return $this->organizations()->where('is_personal', true)->first();
    }
}
