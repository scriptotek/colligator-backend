<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Cover extends Model
{
    /**
     * The attributes that should be visible in arrays.
     *
     * @var array
     */
    protected $visible = ['url', 'width', 'height', 'cached'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['cached'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['url'];

    /**
     * The documents indexed with the subject
     */
    public function document()
    {
        return $this->belongsTo('Colligator\Document');
    }

    /**
     * Returns the URL to the cached image
     */
    public function getCachedAttribute()
    {
        return \CoverCache::url($this->id);
    }

    public function isCached()
    {
        return $this->width && \CoverCache::has($this->id);
    }

    public function cache()
    {
        if (!isset($this->url)) {
            return false;
        }

        try {
            \CoverCache::store($this->id, $this->url);
        } catch (\ErrorException $e) {
            // 'ErrorException' with message 'fopen(http://innhold.bibsys.no/bilde/forside/?size=stor&id=STOR_150058460.jpg): failed to open stream: HTTP request failed! HTTP/1.1 404 Not Found
            \Log::error('Failed to fetch cover ' . $this->url . '. Got error: ' . $e->getMessage());
            return false;
        }
        $dim = \CoverCache::getDimensions($this->id);
        if (is_null($dim[0])) {
            return false;
        }
        $this->width = $dim[0];
        $this->height = $dim[1];
        $this->mime = $dim['mime'];
        $this->save();

        return true;
    }
}
