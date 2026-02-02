<?php

namespace Modules\MobileApi\Events;

use Illuminate\Queue\SerializesModels;

class MobileApiEvent
{
    use SerializesModels;

    public function __construct()
    {
        // 
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [];
    }
}
