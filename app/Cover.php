<?php

namespace Colligator;

use Illuminate\Database\Eloquent\Model;
use Colligator\Facades\CoverCache;

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
        return CoverCache::url($this->id);
    }

    public function isCached()
    {
        return $this->width && CoverCache::has($this->id);
    }

    public function cache()
    {
        if (!isset($this->url)) {
            die('no URL');
        }

        CoverCache::store($this->id, $this->url);
        $dim = CoverCache::getDimensions($this->id);
        if (is_null($dim[0])) {
            return false;
        }
        $this->width = $dim[0];
        $this->height = $dim[1];
        $this->mime = $dim['mime'];
        $this->save();

        // $fs = \Storage::disk('local');
        // $fs->put($localName, );
        // $size = $fs->getSize($localName);
        // dd($size);
        return true;
    }

}
