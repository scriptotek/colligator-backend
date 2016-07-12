<?php

namespace Colligator\Events;

use Illuminate\Queue\SerializesModels;

class OaiPmhHarvestStatus extends Event
{
    use SerializesModels;

    /**
     * @var int
     */
    public $harvested;

    /**
     * @var int
     */
    public $position;

    /**
     * Create a new event instance.
     *
     * @param int $harvested
     * @param int $position
     */
    public function __construct($harvested, $position)
    {
        $this->harvested = $harvested;
        $this->position = $position;
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
