<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['bibsys_id', 'bibliographic', 'holdings'];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'bibliographic' => 'array',
        'holdings' => 'array',
        'xisbn' => 'array',
        'description' => 'array',
    ];

    /**
     * Get subjects associated with this document.
     */
    public function subjects()
    {
        return $this->morphedByMany('Colligator\Subject', 'authority');
    }
    }

    /**
     * The cover belonging to the document.
     */
    public function cover()
    {
        return $this->hasOne('Colligator\Cover');
    }

    /**
     * The collections the document belongs to.
     */
    public function collections()
    {
        return $this->belongsToMany('Colligator\Collection');
    }

    public function storeCover($url)
    {
        $cover = $this->cover;
        if (is_null($cover)) {
            $cover = new Cover(['document_id' => $this->id]);
        }
        $cover->url = $url;
        $cover->cache();
        $cover->save();

        return $cover;
    }
}
