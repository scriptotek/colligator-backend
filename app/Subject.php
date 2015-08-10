<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['vocabulary', 'term', 'type'];

    /**
     * The documents indexed with the subject.
     */
    public function documents()
    {
        return $this->morphToMany('Colligator\Document', 'entity')->withTimestamps();
    }

    public static function lookup($vocabulary, $term, $type)
    {
        return self::where('vocabulary', '=', $vocabulary)
            ->where('term', '=', $term)
            ->where('type', '=', $type)
            ->first();
    }
}
