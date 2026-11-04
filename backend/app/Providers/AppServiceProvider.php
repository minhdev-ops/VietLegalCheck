<?php

namespace App\Providers;

use App\Domain\Support\RequestId;
use App\Models\PersonalAccessToken;
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
    }
}
