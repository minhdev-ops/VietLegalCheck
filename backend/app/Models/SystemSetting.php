<?php

namespace App\Models;

class SystemSetting extends BaseModel
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::find($key)?->value;

        return is_array($value) && array_key_exists('v', $value) ? $value['v'] : $default;
    }

    public static function put(string $key, mixed $value, ?string $description = null, ?string $actorId = null): static
    {
        /** @var static $setting */
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => ['v' => $value], 'description' => $description, 'updated_by' => $actorId, 'updated_at' => now()],
        );

        return $setting;
    }
}
