<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Timing extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['event', 'event_time', 'msecs', 'data'];
}
