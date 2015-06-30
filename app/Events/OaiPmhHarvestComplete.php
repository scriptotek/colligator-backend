<?php

namespace Colligator\Events;

use Illuminate\Queue\SerializesModels;

class OaiPmhHarvestComplete extends Event
{
    use SerializesModels;

    /**
     * @var int
     */
    public $count;

    /**
     * Create a new event instance.
     *
     * @param int $count
     */
    public function __construct($count)
    {
        $this->count = $count;
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
