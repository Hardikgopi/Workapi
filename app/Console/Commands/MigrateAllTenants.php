<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\TenantManager;

class MigrateAllTenants extends Command
{
    protected $signature   = 'tenant:migrate-all {--fresh : Drop all tables and re-run migrations}';
    protected $description = 'Run tenant migrations on every existing tenant database';

    public function handle(TenantManager $tenantManager): void
    {
        $tenants = DB::table('tenants')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->info("Migrating tenant: {$tenant->name}");

            try {
                $tenantManager->switchToTenant($tenant->name);
                $tenantManager->migrateTenant();
                $this->info("  ✔ Done: {$tenant->name}");
            } catch (\Exception $e) {
                $this->error("  ✘ Failed [{$tenant->name}]: " . $e->getMessage());
            }
        }

        $this->info('All tenant migrations complete.');
    }
}
