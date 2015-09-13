<?php

namespace Colligator;

class CoverCache
{
    /**
     * @param string $key
     *
     * @return string
     */
    public function url($key)
    {
        return sprintf('https://s3.%s.amazonaws.com/%s/%s',
            \Config::get('filesystems.disks.s3.region'),
            \Config::get('filesystems.disks.s3.bucket'),
            $key
        );
    }

    /**
     * @param string $url
     *
     * @return CachedImage
     */
    public function get($url)
    {
        return new CachedImage($url);
    }

    /**
     * @param string $url
     * @param int    $maxHeight
     *
     * @throws \ErrorException
     *
     * @return CachedImage
     */
    public function put($url, $maxHeight = 0)
    {
        $item = new CachedImage($url, $maxHeight);
        $item->store();

        return $item;
    }
}
