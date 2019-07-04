<?php

namespace Colligator;

use Colligator\Exceptions\CannotFetchCover;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManager;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config as FlysystemConfig;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class CoverCache
{
    protected $filesystem;
    protected $imageManager;
    protected $fsConfig;
    protected $http;
    protected $requestFactory;

    public function __construct(
        AdapterInterface $filesystem,
        ImageManager $imageManager,
        FlysystemConfig $fsConfig,
        ClientInterface $http,
        RequestFactoryInterface $requestFactory
    )
    {
        $this->filesystem = $filesystem;
        $this->imageManager = $imageManager;
        $this->fsConfig = $fsConfig;
        $this->http = $http;
        $this->requestFactory = $requestFactory;
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
     * @throws CannotFetchCover
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
     * @throws CannotFetchCover
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
     * @throws ClientExceptionInterface
     */
    protected function download($sourceUrl)
    {
        $request = $this->requestFactory->createRequest('GET', $sourceUrl);

        return (string) $this->http->sendRequest($request)->getBody();
    }

    /**
     * Store a file in cache.
     *
     * @param string $sourceUrl
     * @param string $data
     * @param int $maxHeight
     * @return CachedImage
     * @throws CannotFetchCover
     */
    protected function store($sourceUrl = null, $data = null, $maxHeight = 0)
    {
        if (is_null($data)) {
            try {
                $data = $this->download($sourceUrl);
            } catch (ClientExceptionInterface $exception) {
                throw new CannotFetchCover("[CoverCache] Failed to download {$sourceUrl}: {$exception->getMessage()}");
            }
            if (!$data) {
                throw new CannotFetchCover("[CoverCache] Failed to download {$sourceUrl}");
            }
        }

        $cacheKey = sha1($data);

        try {
            $img = $this->imageManager->make($data);
        } catch (NotReadableException $e) {
            throw new CannotFetchCover("[CoverCache] Not a valid image file: {$sourceUrl}");
        }
        if ($maxHeight && $img->height() > $maxHeight) {
            \Log::debug('[CoverCache] Resizing from ' . $img->height() . ' to ' . $maxHeight);
            $img->heighten($maxHeight);
            $data = strval($img->encode('jpg'));
        }

        if ($img->width() / $img->height() > 1.4) {
            throw new CannotFetchCover('[CoverCache] Not accepting images with w/h ratio > 1.4');
        }

        \Log::debug('[CoverCache] Storing image as ' . $img->width() . ' x ' . $img->height() . ', ' . strlen($data) . ' bytes');
        if (!$this->filesystem->write($cacheKey, $data, $this->fsConfig)) {
            throw new CannotFetchCover('[CoverCache] Failed to upload thumb to S3');
        }

        \Log::debug('[CoverCache] Wrote cached version as ' . $cacheKey);

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
     * @return CachedImage
     * @throws CannotFetchCover
     */
    public function thumb($cacheKey, $maxHeight)
    {
        $data = $this->filesystem->read($cacheKey);
        $blob = strval($data['contents']);
        \Log::debug('[CoverCache] Read ' .$cacheKey . ': ' . strlen($blob) . ' bytes');

        return $this->putBlob($blob, $maxHeight);
    }
}
