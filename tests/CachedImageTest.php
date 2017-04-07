<?php

use Colligator\CachedImage;

class CachedImageTest extends TestCase
{
    protected $fakeImage;
    protected $fsMock;
    protected $imMock;

    public function mock()
    {
        $faker = new \Faker\Generator();
        $faker->addProvider(new Faker\Provider\Image($faker));

        $this->fakeImage = $faker->image;

        $this->fsMock = \Mockery::mock('League\Flysystem\AwsS3v3\AwsS3Adapter');
        $this->fsMock->shouldReceive('read')->andReturn([
            'contents' => file_get_contents($this->fakeImage),
        ]);
    }

    public function testGetMetadata()
    {
        $this->mock();
        $url = 'http://example.com/test.JPG';
        $c = new CachedImage($url, 0, $this->fsMock);

        list($width, $height) = getimagesize($this->fakeImage);

        $this->assertEquals('image/jpeg', $c->mime());
        $this->assertSame(filesize($this->fakeImage), $c->size());
        $this->assertSame($width, $c->width());
        $this->assertSame($height, $c->height());
        $this->assertSame(sha1($url . $c->maxHeight), $c->cacheKey);
    }
}
