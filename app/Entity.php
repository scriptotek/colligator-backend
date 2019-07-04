<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    const SUBJECT = 'subject';
    const GENRE = 'genre';
    const LOCAL_SUBJECT = 'local_subject';
    const LOCAL_GENRE = 'local_genre';
    const TYPES = ['subject', 'genre', 'local_subject', 'local_genre'];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'extras' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['vocabulary', 'term', 'type', 'local_id'];

    /**
     * The documents indexed with the subject.
     */
    public function documents()
    {
        return $this->belongsToMany('Colligator\Document')->withTimestamps();
    }

    public static function lookup($vocabulary, $term, $type)
    {
        return self::where('vocabulary', '=', $vocabulary)
            ->where('term', '=', $term)
            ->where('type', '=', $type)
            ->first();
    }
}
