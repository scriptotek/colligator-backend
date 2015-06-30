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
     * @var int
     */
    public $total;

    /**
     * Create a new event instance.
     *
     * @param int $harvested
     * @param int $position
     * @param int $total
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
