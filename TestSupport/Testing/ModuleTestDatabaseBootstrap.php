<?php

namespace Modules\TestSupport\Testing;

use App\Classes\Cache as NsCache;
use App\Services\CoreService;
use App\Services\DateService;
use App\Services\Options;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleTestDatabaseBootstrap
{
    public static function prepare(object $testCase, ?string $moduleMigrationPath = null): void
    {
        self::ensureSqliteDatabaseFile();

        $testCase->artisan('migrate');
        self::runCoreMigrations($testCase);
        self::runPathMigration($testCase, 'database/migrations/create');
        self::patchCoreTestSchema();
        self::seedRequiredRoles();
        self::patchLegacyFactoryDefaults();
        self::registerKnownModuleMiddlewareAliases();

        if ($moduleMigrationPath !== null) {
            self::runPathMigration($testCase, $moduleMigrationPath);
        }

        self::refreshInstalledStateCache();
        self::seedRequiredOptions();
        self::registerModuleProviderForPath($moduleMigrationPath);
        self::registerGatePermissions();
    }

    public static function ensureSqliteDatabaseFile(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $database = config('database.connections.sqlite.database');
        if (! is_string($database) || $database === '' || $database === ':memory:') {
            return;
        }

        $path = str_starts_with($database, DIRECTORY_SEPARATOR)
            ? $database
            : base_path($database);

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (! file_exists($path)) {
            touch($path);
        }
    }

    private static function runPathMigration(object $testCase, string $path): void
    {
        if (! is_dir(base_path($path))) {
            return;
        }

        $testCase->artisan('migrate', [
            '--path' => $path,
            '--realpath' => true,
        ]);
    }

    private static function runCoreMigrations(object $testCase): void
    {
        try {
            self::runPathMigration($testCase, 'database/migrations/core');
        } catch (\Throwable $e) {
            // Some core migrations assume role seed data already exists.
            self::seedRequiredRoles();
            self::runPathMigration($testCase, 'database/migrations/core');
        }
    }

    private static function patchCoreTestSchema(): void
    {
        if (Schema::hasTable('nexopos_users') && ! Schema::hasColumn('nexopos_users', 'email_verified_at')) {
            Schema::table('nexopos_users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable();
            });
        }
    }

    private static function seedRequiredRoles(): void
    {
        if (! Schema::hasTable('nexopos_roles')) {
            return;
        }

        $now = now();
        $roles = [
            ['namespace' => 'admin', 'name' => 'Admin'],
            ['namespace' => 'nexopos.store.administrator', 'name' => 'Store Admin'],
            ['namespace' => 'nexopos.store.cashier', 'name' => 'Store Cashier'],
            ['namespace' => 'nexopos.store.customer', 'name' => 'Store Customer'],
            ['namespace' => 'user', 'name' => 'User'],
        ];

        foreach ($roles as $role) {
            $exists = DB::table('nexopos_roles')->where('namespace', $role['namespace'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('nexopos_roles')->insert([
                'namespace' => $role['namespace'],
                'name' => $role['name'],
                'description' => $role['name'],
                'reward_system_id' => null,
                'minimal_credit_payment' => 0,
                'author' => null,
                'locked' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private static function seedRequiredOptions(): void
    {
        if (! Schema::hasTable('nexopos_options')) {
            return;
        }

        $now = now();
        $defaults = [
            'ns_store_language' => 'en',
            'ns_date_format' => 'Y-m-d',
            'ns_datetime_format' => 'Y-m-d H:i:s',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('nexopos_options')->updateOrInsert(
                ['key' => $key, 'user_id' => null],
                [
                    'value' => $value,
                    'array' => false,
                    'expire_on' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private static function patchLegacyFactoryDefaults(): void
    {
        if (! class_exists(\App\Classes\Hook::class)) {
            return;
        }

        \App\Classes\Hook::addFilter('ns-user-factory', function (array $data) {
            if (empty($data['username'])) {
                $seed = preg_replace('/[^a-z0-9_]/i', '_', (string) ($data['email'] ?? uniqid('user_', true)));
                $data['username'] = substr($seed ?: ('user_' . uniqid()), 0, 60);
            }

            return $data;
        });
    }

    private static function refreshInstalledStateCache(): void
    {
        if (! class_exists(NsCache::class)) {
            return;
        }

        NsCache::forget('ns-core-installed');
        NsCache::set('ns-core-installed', Schema::hasTable('nexopos_options'), 60);
        self::refreshCoreSingletons();
    }

    private static function refreshCoreSingletons(): void
    {
        $app = app();

        foreach ([DateService::class, Options::class, CoreService::class] as $abstract) {
            if (method_exists($app, 'forgetInstance')) {
                $app->forgetInstance($abstract);
            }
        }
    }

    private static function registerGatePermissions(): void
    {
        if (! class_exists(CoreService::class)) {
            return;
        }

        try {
            app(CoreService::class)->registerGatePermissions();
        } catch (\Throwable $e) {
            // Ignore in module tests when core services are partially bootstrapped.
        }
    }

    private static function registerModuleProviderForPath(?string $moduleMigrationPath): void
    {
        if (! is_string($moduleMigrationPath) || $moduleMigrationPath === '') {
            return;
        }

        $providerMap = [
            'modules/NsContainerManagement/Migrations' => \Modules\NsContainerManagement\Providers\ContainerManagementServiceProvider::class,
            'modules/NsManufacturing/Migrations' => \Modules\NsManufacturing\Providers\NsManufacturingServiceProvider::class,
            'modules/NsSpecialCustomer/Migrations' => \Modules\NsSpecialCustomer\Providers\NsSpecialCustomerServiceProvider::class,
        ];

        $providerClass = $providerMap[$moduleMigrationPath] ?? null;
        if (! is_string($providerClass) || ! class_exists($providerClass)) {
            return;
        }

        app()->register($providerClass);
    }

    private static function registerKnownModuleMiddlewareAliases(): void
    {
        $router = app('router');

        $aliases = [
            'ns.special-customer.permission' => \Modules\NsSpecialCustomer\Http\Middleware\CheckSpecialCustomerPermission::class,
            'ns.special-customer.ownership' => \Modules\NsSpecialCustomer\Http\Middleware\EnsureCustomerOwnership::class,
            'ns.special-customer.balance-access' => \Modules\NsSpecialCustomer\Http\Middleware\CheckBalanceAccess::class,
        ];

        foreach ($aliases as $alias => $class) {
            if (! class_exists($class)) {
                continue;
            }

            $router->aliasMiddleware($alias, $class);
        }
    }
}
