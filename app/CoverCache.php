<?php

namespace Colligator;

use Intervention\Image\ImageManager;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config as FlysystemConfig;

class CoverCache
{
    protected $filesystem;
    protected $imageManager;
    protected $fsConfig;

    public function __construct(AdapterInterface $filesystem, ImageManager $imageManager, FlysystemConfig $fsConfig)
    {
        $this->filesystem = $filesystem;
        $this->imageManager = $imageManager;
        $this->fsConfig = $fsConfig;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function url($key)
    {
        return sprintf('https://s3.%s.amazonaws.com/%s/%s',
            config('filesystems.disks.s3.region'),
            config('filesystems.disks.s3.bucket'),
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
        return $this->store($url, null, $maxHeight);
    }

    /**
     * @param string $blob
     * @param int    $maxHeight
     *
     * @throws \ErrorException
     *
     * @return CachedImage
     */
    public function putBlob($blob, $maxHeight = 0)
    {
        return $this->store(null, $blob, $maxHeight);
    }

    /**
     * Retrieves the content of an URL.
     *
     * @return string
     */
    protected function download($sourceUrl)
    {
        // TODO: Use flysystem-http-downloader instead, but needs update
        // https://github.com/indigophp/flysystem-http-downloader/pull/2
        return file_get_contents($sourceUrl);
    }

    /**
     * Store a file in cache.
     *
     * @param string $sourceUrl
     * @param string $data
     * @param int $maxHeight
     * @return CachedImage
     * @throws \ErrorException
     */
    protected function store($sourceUrl = null, $data = null, $maxHeight = 0)
    {
        if (is_null($data)) {
            $data = $this->download($sourceUrl);
            if (!$data) {
                throw new \ErrorException('[CoverCache] Failed to download ' . $sourceUrl);
            }
        }

        $cacheKey = sha1($data);

        $img = $this->imageManager->make($data);
        if ($maxHeight && $img->height() > $maxHeight) {
            \Log::debug('[CachedImage] Resizing from ' . $img->height() . ' to ' . $maxHeight);
            $img->heighten($maxHeight);
            $data = strval($img->encode('jpg'));
        }

        if ($img->width() / $img->height() > 1.4) {
            throw new \ErrorException('[CoverCache] Not accepting images with w/h ratio > 1.4');
        }


        \Log::debug('[CachedImage] Storing image as ' . $img->width() . ' x ' . $img->height() . ', ' . strlen($data) . ' bytes');
        if (!$this->filesystem->write($cacheKey, $data, $this->fsConfig)) {
            throw new \ErrorException('[CoverCache] Failed to upload thumb to S3');
        }

        \Log::debug('[CachedImage] Wrote cached version as ' . $cacheKey);

        return new CachedImage($this, $cacheKey);
    }

    public function getMetadata($cacheKey) {
        $data = $this->filesystem->read($cacheKey);
        $contents = strval($data['contents']);
        $img = $this->imageManager->make($contents);

        return [
            'size'   => strlen($contents),
            'width'  => $img->width(),
            'height' => $img->height(),
            'mime'   => $img->mime(),
        ];
    }

    /**
     * Return a representation with height no more than $maxHeight.
     *
     * @param string $maxHeight
     *
     * @throws \ErrorException
     *
     * @return CachedImage
     */
    public function thumb($cacheKey, $maxHeight)
    {
        $data = $this->filesystem->read($cacheKey);
        $blob = strval($data['contents']);
        \Log::debug('[CachedImage] Read ' .$cacheKey . ': ' . strlen($blob) . ' bytes');

        return $this->putBlob($blob, $maxHeight);
    }
}
