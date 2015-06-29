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
        return \URL::to('/covers/' . sha1($this->id) . '.jpg');
    }

    public function getCachedPath()
    {
        return public_path('covers/' . sha1($this->id) . '.jpg');
    }

    public function isCached()
    {
        return file_exists($this->getCachedPath());
    }

    public function cache()
    {
        if (!isset($this->url)) {
            die('no URL');
        }

        $cachedPath = $this->getCachedPath();

        file_put_contents($cachedPath, fopen($this->url, 'r'));

        $dim = getimagesize($cachedPath);
        $this->width = $dim[0];
        $this->height = $dim[1];
        $this->mime = $dim['mime'];
        $this->save();

        \Log::info('Cached cover from ' . $this->url . ' as ' . $cachedPath);

        // $fs = \Storage::disk('local');
        // $fs->put($localName, );
        // $size = $fs->getSize($localName);
        // dd($size);
    }

}
