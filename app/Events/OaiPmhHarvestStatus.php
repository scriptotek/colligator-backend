<?php

namespace Colligator\Events;

use Colligator\Events\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OaiPmhHarvestStatus extends Event
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($harvested, $position, $total)
    {
        $this->harvested = $harvested;
        $this->position = $position;
        $this->total = $total;
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