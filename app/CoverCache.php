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
     * @param int    $maxHeight
     *
     * @throws \ErrorException
     *
     * @return CachedImage
     */
    public function putUrl($url, $maxHeight = 0)
    {
        $item = new CachedImage($url, $maxHeight);
        $item->store();

        return $item;
    }

    /**
     * @param binary $blob
     * @param int    $maxHeight
     *
     * @throws \ErrorException
     *
     * @return CachedImage
     */
    public function putBlob($blob, $maxHeight = 0)
    {
        $item = new CachedImage(null, $maxHeight);
        $item->store($blob);

        return $item;
    }
}
