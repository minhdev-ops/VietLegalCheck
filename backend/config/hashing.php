<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | SRS §5.2/FR-AUTH-02: mật khẩu phải hash bằng Argon2id. Sodium có sẵn trong
    | image phục vụ; nếu môi trường thiếu Sodium, Laravel tự fallback sang bcrypt.
    |
    */

    'driver' => env('HASH_DRIVER', extension_loaded('sodium') ? 'argon' : 'bcrypt'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    */

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
    ],

];
