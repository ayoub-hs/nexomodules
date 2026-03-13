<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Register;
use Modules\MobileApi\Services\RegisterSyncService;

class MobileRegisterSyncController extends Controller
{
    public function sync( Register $register, RegisterSyncService $registerSyncService )
    {
        $registerSyncService->syncRegisterBalance( $register->id );
        $register->refresh();

        return response()->json( [
            'status' => 'success',
            'message' => __( 'The register has been synchronized.' ),
            'data' => [
                'register' => $register,
            ],
        ] );
    }
}
