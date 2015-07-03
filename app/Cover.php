<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;

class Cover extends Model
{

    /**
     * Default thumbnail height
     *
     * @var array
     */
    public $defaultThumbHeight = 600;

    /**
     * The attributes that should be visible in arrays.
     *
     * @var array
     */
    protected $visible = ['url', 'cached', 'thumb'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['cached', 'thumb'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['document_id', 'url'];

    /**
     * The document the cover belongs to.
     *
     * @return Document
     */
    public function document()
    {
        return $this->belongsTo('Colligator\Document');
    }

    /**
     * Returns the URL to the cached image
     *
     * @return array
     */
    public function getCachedAttribute()
    {
        return [
            'url' => \CoverCache::url($this->cache_key),
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * Returns the URL to the cached thumb image
     *
     * @return array
     */
    public function getThumbAttribute()
    {

        return !is_null($this->thumb_key) ? [
            'url' => \CoverCache::url($this->thumb_key),
            'width' => $this->thumb_width,
            'height' => $this->thumb_height,
        ] : $this->cached;
    }

    /**
     * Invalidate cache
     *
     * @return void
     */
    public function invalidateCache()
    {
        $this->width = null;
        $this->height = null;
        $this->cache_key = null;
        $this->thumb_width = null;
        $this->thumb_height = null;
        $this->thumb_key = null;
    }

    /**
     * Mutuator for the url attribute. Invalidates cache when the paramter changes.
     *
     * @param $value
     */
    public function setUrlAttribute($value)
    {
        if (array_get($this->attributes, 'url') == $value) {
            return;
        }
        $this->attributes['url'] = $value;
        $this->invalidateCache();
    }

    /**
     * Checks if the cover is cached
     *
     * @return bool
     */
    public function isCached()
    {
        return !is_null($this->width);
    }

    /**
     * Cache the cover and create thumbnail
     *
     * @throws \ErrorException
     */
    public function cache()
    {
        if (!isset($this->url)) {
            throw new \ErrorException('[Cover] Cannot cache when no URL set.');
        }
        if ($this->isCached()) {
            \Log::debug('Already cached: ' . $this->url);
            return;
        }

        \Log::debug('Cache add: ' . $this->url);

        $orig = \CoverCache::put($this->url);

        $this->width = $orig->width();
        $this->height = $orig->height();
        $this->mime = $orig->mime();
        $this->cache_key = $orig->basename();

        if ($orig->height() > $this->defaultThumbHeight) {
            $thumb = $orig->thumb($this->defaultThumbHeight);
            $this->thumb_width = $thumb->width();
            $this->thumb_height = $thumb->height();
            $this->thumb_key = $thumb->basename();
        }
    }
}
