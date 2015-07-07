<?php

namespace Colligator\Events;

use Illuminate\Queue\SerializesModels;

class Marc21RecordImported extends Event
{
    use SerializesModels;

    public $id;

    /**
     * Create a new event instance.
     *
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
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
