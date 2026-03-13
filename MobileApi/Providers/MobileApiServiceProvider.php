<?php

namespace Modules\MobileApi\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\MobileApi\Services\RegisterSyncService;
use TorMorten\Eventy\Facades\Events as Hook;

class MobileApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton( RegisterSyncService::class );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom( __DIR__ . '/../Migrations' );

        // The core app already has active cash-register listeners in this install.
        // Re-registering equivalent listeners here causes the running register balance
        // to drift away from the stored history. Keep synchronization explicit.
        Hook::addFilter( 'ns-crud-resource', function ( $identifier ) {
            switch ( $identifier ) {
                case 'ns.cash-registers':
                case \App\Crud\RegisterCrud::class:
                    return \Modules\MobileApi\Crud\RegisterCrud::class;
                case 'ns.cash-registers-history':
                case \App\Crud\RegisterHistoryCrud::class:
                    return \Modules\MobileApi\Crud\RegisterHistoryCrud::class;
            }

            return $identifier;
        } );
    }
}
