<?php

namespace Tests;

use Colligator\CoverCache;
use Intervention\Image\ImageManager;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Config as FlysystemConfig;

class CoverCacheTest extends BrowserKitTestCase
{
    public function testGetMetadata()
    {
        $faker = new \Faker\Generator();
        $faker->addProvider(new \Faker\Provider\Image($faker));

        $fakeImage = $faker->image;

        $filesystem = \Mockery::mock(AwsS3Adapter::class);
        $filesystem->shouldReceive('read')->andReturn([
            'contents' => file_get_contents($fakeImage),
        ]);

        $filesystem->shouldReceive('write')->andReturn(true);

        $url = 'http://example.com/test.JPG';

        $cache = new CoverCache(
            $filesystem,
            new ImageManager(),
            new FlysystemConfig()
        );
        $image = $cache->putBlob(file_get_contents($fakeImage));

        list($width, $height) = getimagesize($fakeImage);

        $this->assertEquals('image/jpeg', $image->mime());
        $this->assertSame(filesize($fakeImage), $image->size());
        $this->assertSame($width, $image->width());
        $this->assertSame($height, $image->height());
        // This is generated when storing. $this->assertSame(sha1($url . $c->maxHeight), $c->cacheKey);
    }
}
