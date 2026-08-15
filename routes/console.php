<?php

use Illuminate\Foundation\Inspiring;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('tenants:run bank:closing')
    ->dailyAt('23:59')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'tenant:register
    {id : Tenant ID, for example kp}
    {domain : Full tenant domain}
    {database : Existing tenant database name}
    {--company= : Company name}
    {--status=active : Tenant status}',
    function () {
        $id = strtolower(trim((string) $this->argument('id')));
        $domain = strtolower(trim((string) $this->argument('domain')));
        $database = trim((string) $this->argument('database'));
        $company = trim((string) $this->option('company'));
        $status = trim((string) $this->option('status'));

        if ($company === '') {
            $company = strtoupper($id);
        }

        /*
         * Validate tenant ID.
         */
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $id)) {
            $this->error(
                'Invalid tenant ID. Use lowercase letters, numbers and hyphens only.'
            );

            return 1;
        }

        /*
         * Validate full domain.
         */
        if (! preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
            $domain
        )) {
            $this->error('Invalid domain name.');

            return 1;
        }

        /*
         * Validate database name.
         */
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            $this->error(
                'Invalid database name. Use letters, numbers and underscores only.'
            );

            return 1;
        }

        try {
            /*
             * Ensure this domain is not assigned to another tenant.
             */
            $domainModel = config('tenancy.domain_model');

            $existingDomain = $domainModel::query()
                ->where('domain', $domain)
                ->first();

            if (
                $existingDomain &&
                (string) $existingDomain->tenant_id !== $id
            ) {
                $this->error(
                    "Domain {$domain} is already assigned to tenant "
                    .$existingDomain->tenant_id
                );

                return 1;
            }

            /*
             * Register or update the tenant.
             *
             * Events are disabled because the database was created
             * manually through cPanel.
             */
            $tenant = Tenant::withoutEvents(function () use (
                $id,
                $company,
                $status,
                $database
            ) {
                return Tenant::updateOrCreate(
                    [
                        'id' => $id,
                    ],
                    [
                        'company_name' => $company,
                        'status' => $status,
                        'tenancy_db_name' => $database,
                    ]
                );
            });

            /*
             * Register the tenant domain without duplicating it.
             */
            $tenant->domains()->firstOrCreate([
                'domain' => $domain,
            ]);

            $this->newLine();
            $this->info('Tenant registered successfully.');

            $this->table(
                ['Property', 'Value'],
                [
                    ['Tenant ID', $tenant->id],
                    ['Company', $company],
                    ['Status', $status],
                    ['Database', $database],
                    ['Domain', $domain],
                ]
            );

            return 0;
        } catch (\Throwable $exception) {
            $this->error('Tenant registration failed.');
            $this->error($exception->getMessage());

            return 1;
        }
    }
)->purpose('Register a manually created tenant database and domain');

Artisan::command('tenant:test {id}', function () {
    $tenant = Tenant::find($this->argument('id'));

    if (! $tenant) {
        $this->error('Tenant not found.');

        return 1;
    }

    try {
        $tenant->run(function () {
            $database = DB::connection()->getDatabaseName();

            $this->info("Connected tenant database: {$database}");
        });

        return 0;
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
});
