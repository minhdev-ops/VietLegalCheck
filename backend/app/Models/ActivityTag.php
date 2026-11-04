<?php

namespace App\Models;

class ActivityTag extends BaseModel
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public function label(): string
    {
        return $this->name_vi;
    }
}
