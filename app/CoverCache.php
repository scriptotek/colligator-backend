<?php

namespace Colligator;

class CoverCache
{
    protected function path($key)
    {
        return public_path('covers/' . sha1($key) . '.jpg');
    }

    public function url($key)
    {
        return \URL::to('/covers/' . sha1($key) . '.jpg');
    }

    public function has($key)
    {
        return file_exists($this->path($key));
    }

    public function store($key, $url)
    {
        $path = $this->path($key);
        file_put_contents($path, fopen($url, 'r'));
        \Log::info('Cached cover from ' . $url . ' as ' . $path);
        return $path;
    }

    public function getDimensions($key)
    {
        return getimagesize($this->path($key));
    }
}
