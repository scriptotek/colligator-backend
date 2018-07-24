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
    protected $coverCache;

    public function __construct(CoverCache $coverCache, $cacheKey)
    {
        $this->coverCache = $coverCache;
        $this->cacheKey = $cacheKey;
    }


    public function getMetadata()
    {
        if (is_null($this->_metadata)) {
            $this->_metadata = $this->coverCache->getMetadata($this->cacheKey);
        }

        return $this->_metadata;
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
}
