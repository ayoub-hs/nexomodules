<?php

namespace Modules\MobileApi;

use Illuminate\Support\Facades\Event;
use App\Services\Module;

class MobileApiModule extends Module
{
    public function __construct()
    {
        parent::__construct( __FILE__ );
    }
}
