<?php

namespace Colligator\Events;

use Illuminate\Queue\SerializesModels;

class JobError extends Event
{
    use SerializesModels;

    /**
     * @var string
     */
    public $msg;

    /**
     * Create a new event instance.
     *
     * @param $msg
     */
    public function __construct($msg)
    {
        $this->msg = $msg;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }
}
