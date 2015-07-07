<?php

use Colligator\CachedImage;

class CachedImageTest extends TestCase
{
    public function testBasicExample()
    {
        $faker = new \Faker\Generator();
        $faker->addProvider(new Faker\Provider\Image($faker));
        $mock = \Mockery::mock('League\Flysystem\AwsS3v3\AwsS3Adapter');
        $mock->shouldReceive('read')->andReturn([
            'contents' => file_get_contents($faker->image),
        ]);
        $c = new CachedImage('http://example.com/test.JPG', 0, $mock);

        $this->assertEquals('image/jpeg', $c->mime());
    }
}
