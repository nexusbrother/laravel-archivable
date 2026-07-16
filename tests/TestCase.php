<?php

namespace Nexusbrother\Archivable\Tests;

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nexusbrother\Archivable\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__.'/../');
        $dotenv->load();
        parent::setUp();

        $default = config('database.default');
        $this->runMigrationsOnConnection('default');
        config(['database.default' => $default]);

        $this->cleanArchiveDatabase();
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
        $dbConfig = require __DIR__.'/config/database.php';
        $app['config']->set('database', $dbConfig);
    }

    /**
     * Run migrations on the given database connection.
     *
     * @return void
     */
    protected function runMigrationsOnConnection(string $connection)
    {
        config(['database.default' => $connection]);
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->artisan('migrate', ['--database' => $connection])->run();
    }

    /**
     * Truncate all tables in the archive database to ensure test isolation.
     */
    protected function cleanArchiveDatabase(): void
    {
        $archiveDb = DB::connection('archive');
        $tables = $archiveDb->select('SHOW TABLES');

        $archiveDb->statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $row) {
            $tableName = array_values((array) $row)[0];
            $archiveDb->statement("TRUNCATE TABLE `{$tableName}`");
        }
        $archiveDb->statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
