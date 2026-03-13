<?php

namespace Modules\MobileApi\Crud;

use Modules\MobileApi\Services\RegisterSyncService;

class RegisterHistoryCrud extends \App\Crud\RegisterHistoryCrud
{
    private ?RegisterSyncService $registerSyncService = null;

    public function __construct()
    {
        parent::__construct();
    }

    private function registerSyncService(): RegisterSyncService
    {
        return $this->registerSyncService ??= app()->make( RegisterSyncService::class );
    }

    public function hook( $query ): void
    {
        $registerId = (int) request()->query( 'register_id', 0 );

        if ( $registerId > 0 ) {
            $this->registerSyncService()->syncRegisterBalance( $registerId );
        }

        parent::hook( $query );
    }
}
