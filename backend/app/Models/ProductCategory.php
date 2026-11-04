<?php

namespace App\Models;

class ProductCategory extends BaseModel
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';
}
