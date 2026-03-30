<?php

namespace Nexusbrother\Archivable\Tests;

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Nexusbrother\Archivable\ServiceProvider;

/**
 * 基础测试类：用于初始化测试环境，不包含具体测试方法
 *
 * @testdox 基础测试环境配置类
 */
abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     *  Setup the test environment.
     */
    protected function setUp(): void // Load .env.testing file
    {$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        parent::setUp();

        $default = config('database.default');
        // 为默认数据库运行迁移
        $this->runMigrationsOnConnection('default');
        config(['database.default' => $default]);
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // 加载外部数据库配置
        $dbConfig = require __DIR__ . '/config/database.php';
        $app['config']->set('database', $dbConfig);
    }

    /**
     * 在指定数据库连接上运行迁移
     *
     * @return void
     */
    protected function runMigrationsOnConnection(string $connection)
    {
        // 设置当前连接
        config(['database.default' => $connection]);
        // 加载并运行迁移
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->artisan('migrate', ['--database' => $connection])->run();
    }
}
