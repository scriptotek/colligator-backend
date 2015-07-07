<?php

namespace Colligator;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config as FlysystemConfig;

class CachedImage
{
    public $sourceUrl;
    public $maxHeight;
    protected $_metadata;

    public function __construct($url, $maxHeight = 0, AdapterInterface $filesystem = null)
    {
        $this->sourceUrl = $url;
        $this->maxHeight = intval($maxHeight);
        $this->filesystem = $filesystem ?: \Storage::disk('s3')->getAdapter();
        $maxAge = 3153600; // 30 days
        $this->fsConfig = new FlysystemConfig([
            'CacheControl' => 'max-age=' . $maxAge . ', public',
        ]);
    }

    public function getMetadata()
    {
        if (is_null($this->_metadata)) {
            \Log::debug('Get metadata from remote for ' . $this->sourceUrl);
            $data = $this->filesystem->read($this->basename());
            $contents = strval($data['contents']);
            $img = \Image::make($contents);
            $this->setMetadata($contents, $img);
        }

        return $this->_metadata;
    }

    protected function setMetadata($file, $img)
    {
        $this->_metadata = [
            'size' => strlen($file),
            'width' => $img->width(),
            'height' => $img->height(),
            'mime' => $img->mime(),
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

    public function basename()
    {
        return sprintf(
            '%s',
            sha1($this->sourceUrl . $this->maxHeight)
        );
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
     * @return CachedImage
     *
     * @throws \ErrorException
     */
    public function store()
    {
        $data = $this->download();
        if (!$data) {
            throw new \ErrorException('[CoverCache] Failed to download ' . $this->sourceUrl);
        }

        $img = \Image::make($data);
        if ($this->maxHeight && $img->height() > $this->maxHeight) {
            \Log::debug('Resizing from ' . $img->height() . ' to ' . $this->maxHeight);
            $img->heighten($this->maxHeight);
            $data = strval($img->encode('jpg'));
        }

        $this->setMetadata($data, $img);

        \Log::debug('Storing image as ' . $img->width() . ' x ' . $img->height() . ', ' . strlen($data) . ' bytes');
        if (!$this->filesystem->write($this->basename(), $data, $this->fsConfig)) {
            throw new \ErrorException('[CoverCache] Failed to upload to S3: ' . $this->sourceUrl);
        }

        \Log::debug('Wrote cached version of ' . $this->sourceUrl . ' as ' . $this->basename());

        return $this;
    }

    /**
     * Return a representation with height no more than $maxHeight.
     *
     * @param string $maxHeight
     *
     * @return CachedImage
     *
     * @throws \ErrorException
     */
    public function thumb($maxHeight)
    {
        return \CoverCache::put($this->sourceUrl, $maxHeight);
    }
}
