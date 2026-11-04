<?php

namespace App\Domain\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Laravel skeleton cũ không có `Request::id()`; macro này giữ contract JSON
 * `{error: {request_id}}` cho mọi response lỗi.
 */
final class RequestId
{
    public const HEADER = 'X-Request-ID';

    public static function register(): void
    {
        // Macro được mix vào Request nên `self::` sẽ resolve theo class gọi — phải_dẫn hằng.
        $headerName = self::HEADER;

        Request::macro('id', function () use ($headerName) {
            /** @var Request $this */
            $header = $this->header($headerName);

            if (is_string($header) && $header !== '') {
                return substr($header, 0, 64);
            }

            return Str::uuid()->toString();
        });
    }
}
