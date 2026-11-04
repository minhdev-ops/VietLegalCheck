<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

/**
 * Cấu hình kiểm thử: sqlite trong bộ nhớ + queue sync, migrate qua
 * `migrate:fresh --force` trong createApplication.
 *
 * Lý do không dùng trait RefreshDatabase: biến môi trường container
 * (APP_ENV=production, DB_CONNECTION=pgsql) vượt qua phpunit.xml `<env>`;
 * trait gọi `migrate:fresh` không --force nên bị ConfirmableTrait hủy,
 * và method trait ghi đè mọi override ở lớp cha.
 */
abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('app.env', 'testing');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('queue.default', 'sync');

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => 'ActivityTagSeeder', '--force' => true]);

        return $app;
    }
}
