<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

class TenantManager
{
    /**
     * Switch the database connection to a specific tenant.
     */
    public function switchToTenant(string $companyName): string
    {
        $dbName = $this->getTenantDatabaseName($companyName);

        Config::set('database.connections.tenant.database', $dbName);
        DB::purge('tenant');
        DB::reconnect('tenant');

        return $dbName;
    }

    /**
     * Create a new tenant database and run migrations.
     */
    public function createTenant(string $companyName): string
    {
        $dbName = $this->getTenantDatabaseName($companyName);

        // 1. Create the database
        DB::statement("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 2. Switch connection to this new DB
        $this->switchToTenant($companyName);

        // 3. Run migrations for the tenant
        $this->migrateTenant();

        return $dbName;
    }

    /**
     * Run migrations on the current tenant connection.
     */
    public function migrateTenant(): void
    {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    /**
     * Format the tenant database name.
     */
    protected function getTenantDatabaseName(string $companyName): string
    {
        return 'tenant_' . preg_replace('/\W+/', '_', strtolower($companyName));
    }
}
