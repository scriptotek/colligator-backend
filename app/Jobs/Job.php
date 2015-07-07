<?php

namespace Colligator\Jobs;

use Colligator\Events\JobError;
use Event;
use Illuminate\Bus\Queueable;

abstract class Job
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "queueOn" and "delay" queue helper methods.
    |
    */

    use Queueable;

    protected function error($msg)
    {
        Event::fire(new JobError($msg));
    }
}
