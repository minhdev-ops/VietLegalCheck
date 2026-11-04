<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainVerification extends BaseModel
{
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'VERIFIED';
    }

    public function instruction(): string
    {
        return $this->method === 'META_TAG'
            ? '<meta name="vietlegal-verification" content="'.$this->token.'"> trong <head> của trang chủ'
            : 'Bản ghi TXT: vietlegal-verify='.$this->token.' trên tên miền '.$this->domain;
    }
}
