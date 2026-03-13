<?php

namespace Modules\MobileApi\Crud;

use App\Models\Register;
use App\Services\CrudEntry;
use Modules\MobileApi\Services\RegisterSyncService;

class RegisterCrud extends \App\Crud\RegisterCrud
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

    public function setActions( CrudEntry $entry ): CrudEntry
    {
        if ( $entry->status === Register::STATUS_OPENED ) {
            $this->registerSyncService()->syncRegisterBalance( (int) $entry->id );

            $freshRegister = Register::query()->find( $entry->id );

            if ( $freshRegister instanceof Register ) {
                $entry->balance = $freshRegister->balance;
                $entry->status = $freshRegister->status;
                $entry->used_by = $freshRegister->used_by;
            }
        }

        return parent::setActions( $entry );
    }
}
