<?php

namespace App\Providers;

use App\Domain\Support\RequestId;
use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RequestId::register();

        // PersonalAccessToken mặc định có id tăng dần — không hợp với UUID khóa chính.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // SRS §8.1: giới hạn tốc độ riêng cho đăng nhập, quét và AI.
        // Khóa theo người dùng (+ tên miền đích với quét) để giới hạn từng tài khoản,
        // không chỉ theo IP — chống lạm dụng khi nhiều user dùng chung địa chỉ.
        RateLimiter::for('vlc-login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip().'|'.$request->input('email', '')),
        ]);

        RateLimiter::for('vlc-scan', function (Request $request) {
            $host = strtolower((string) parse_url((string) $request->input('url', ''), PHP_URL_HOST));

            return [
                Limit::perHour(5)->by('scan-host|'.$host),
                Limit::perDay(40)->by('scan-user|'.$request->user()?->id),
            ];
        });

        RateLimiter::for('vlc-ai', fn (Request $request) => [
            Limit::perMinute(10)->by('ai|'.$request->user()?->id),
            Limit::perDay(200)->by('ai-day|'.$request->user()?->id),
        ]);

        RateLimiter::for('vlc-verify', fn (Request $request) => [
            Limit::perHour(10)->by('verify|'.$request->user()?->id),
        ]);
    }
}
