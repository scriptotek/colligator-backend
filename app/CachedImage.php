<?php

namespace Colligator;

use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config as FlysystemConfig;

class CachedImage
{
    public $sourceUrl;
    public $maxHeight;
    public $cacheKey;
    protected $_metadata;

    public function __construct($url, $maxHeight = 0, AdapterInterface $filesystem = null, ImageManager $imageManager = null)
    {
        $this->sourceUrl = $url;
        $this->maxHeight = intval($maxHeight);
        $this->filesystem = $filesystem ?: \Storage::disk('s3')->getAdapter();
        $this->imageManager = $imageManager ?: app('Intervention\Image\ImageManager');
        $maxAge = 3153600; // 30 days
        $this->fsConfig = new FlysystemConfig([
            'CacheControl' => 'max-age=' . $maxAge . ', public',
        ]);
    }

    public function getMetadata()
    {
        if (is_null($this->_metadata)) {
            $data = $this->filesystem->read($this->cacheKey);
            $contents = strval($data['contents']);
            $img = $this->imageManager->make($contents);
            $this->setMetadata($contents, $img);
        }

        return $this->_metadata;
    }

    protected function setMetadata($file, Image $img)
    {
        $this->_metadata = [
            'size'   => strlen($file),
            'width'  => $img->width(),
            'height' => $img->height(),
            'mime'   => $img->mime(),
        ];
    }

    public function width()
    {
        return $this->getMetadata()['width'];
    }

    public function height()
    {
        return $this->getMetadata()['height'];
    }

    public function mime()
    {
        return $this->getMetadata()['mime'];
    }

    public function size()
    {
        return $this->getMetadata()['size'];
    }

    /**
     * Retrieves the content of an URL.
     *
     * @return string
     */
    public function download()
    {
        // TODO: Use flysystem-http-downloader instead, but needs update
        // https://github.com/indigophp/flysystem-http-downloader/pull/2
        return file_get_contents($this->sourceUrl);
    }

    /**
     * Store a file in cache.
     *
     * @throws \ErrorException
     *
     * @return CachedImage
     */
    public function store($data = null)
    {
        if (is_null($data)) {
            $data = $this->download();
            if (!$data) {
                throw new \ErrorException('[CoverCache] Failed to download ' . $this->sourceUrl);
            }
        }

        $this->cacheKey = sha1($data);

        $img = $this->imageManager->make($data);
        if ($this->maxHeight && $img->height() > $this->maxHeight) {
            \Log::debug('[CachedImage] Resizing from ' . $img->height() . ' to ' . $this->maxHeight);
            $img->heighten($this->maxHeight);
            $data = strval($img->encode('jpg'));
        }

        if ($img->width() / $img->height() > 1.4) {
            throw new \ErrorException('[CoverCache] Not accepting images with w/h ratio > 1.4');
        }

        $this->setMetadata($data, $img);

        \Log::debug('[CachedImage] Storing image as ' . $img->width() . ' x ' . $img->height() . ', ' . strlen($data) . ' bytes');
        if (!$this->filesystem->write($this->cacheKey, $data, $this->fsConfig)) {
            throw new \ErrorException('[CoverCache] Failed to upload thumb to S3');
        }

        \Log::debug('[CachedImage] Wrote cached version as ' . $this->cacheKey);

        return $this;
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
    public function thumb($maxHeight)
    {
        return \CoverCache::putBlob(file_get_contents($this->cacheKey), $maxHeight);
    }
}
