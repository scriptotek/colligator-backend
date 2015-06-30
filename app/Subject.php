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
    protected $fillable = ['vocabulary', 'term'];

    /**
     * The documents indexed with the subject
     */
    public function documents()
    {
        return $this->belongsToMany('Colligator\Document');
    }

    public static function lookup($vocabulary, $term)
    {
        return Subject::where('vocabulary', '=', $vocabulary)
            ->where('term', '=', $term)
            ->first();
    }
}
