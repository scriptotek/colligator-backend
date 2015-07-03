<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Colligator\Document;
use Colligator\Cover;

class DocumentsControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function testPostingSmallCoverShouldNotCauseThumbnailGeneration()
    {
        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';

        \Es::shouldReceive('index')->times(1);

        $mock = \Mockery::mock('Colligator\CachedImage');
        $mock->shouldReceive('width')->once()->andReturn(300);
        $mock->shouldReceive('height')->twice()->andReturn(500);
        $mock->shouldReceive('mime')->once()->andReturn('image/jpeg');
        $mock->shouldReceive('basename')->once()->andReturn('random');
        $mock->shouldReceive('thumb')->times(0);

        \CoverCache::shouldReceive('url')->andReturn('http://example.com/random');
        \CoverCache::shouldReceive('put')->once()->andReturn($mock);

        // Generate dummy data
        $doc = factory(Document::class)->create();

        $this->post('/api/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url' => $exampleUrl]);
    }

    public function testPostigLargeCoverShouldCauseThumbnailGeneration()
    {
        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';

        \Es::shouldReceive('index')->times(1);

        $mock2 = \Mockery::mock('Colligator\CachedImage');
        $mock2->shouldReceive('width')->once()->andReturn(600);
        $mock2->shouldReceive('height')->once()->andReturn(1200);
        $mock2->shouldReceive('mime')->times(0);
        $mock2->shouldReceive('basename')->once()->andReturn('random2');

        $mock = \Mockery::mock('Colligator\CachedImage');
        $mock->shouldReceive('width')->once()->andReturn(600);
        $mock->shouldReceive('height')->twice()->andReturn(1200);
        $mock->shouldReceive('mime')->once()->andReturn('image/jpeg');
        $mock->shouldReceive('basename')->once()->andReturn('random');
        $mock->shouldReceive('thumb')->once()->andReturn($mock2);

        \CoverCache::shouldReceive('url')->andReturn('http://example.com/random');
        \CoverCache::shouldReceive('put')->once()->andReturn($mock);

        // Generate dummy data
        $doc = factory(Document::class)->create();

        $this->post('/api/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url' => $exampleUrl]);
    }

    public function testPostingTheSameCoverTwiceShouldNotCauseTwoCachingRequests()
    {
        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';
        \Es::shouldReceive('index')->times(2);
        $mock = \Mockery::mock('Colligator\CachedImage');
        $mock->shouldReceive('width')->once()->andReturn(300);
        $mock->shouldReceive('height')->twice()->andReturn(500);
        $mock->shouldReceive('mime')->once()->andReturn('image/jpeg');
        $mock->shouldReceive('basename')->once()->andReturn('random');
        $mock->shouldReceive('thumb')->times(0);
        \CoverCache::shouldReceive('url')->andReturn('http://example.com/random');
        \CoverCache::shouldReceive('put')->once()->andReturn($mock);
        $doc = factory(Document::class)->create();

        $this->post('/api/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url' => $exampleUrl]);

        $this->post('/api/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url' => $exampleUrl]);
    }

    public function testPostCoverInvalidRequest()
    {
        \Es::shouldReceive('index')->times(0);
        \CoverCache::shouldReceive('store')->times(0);

        // Generate dummy data
        factory(Document::class)->create();

        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';
        $response = $this->post('/api/documents/1/cover', ['urls' => $exampleUrl], ['Accept' => 'application/json']);
        $response->assertResponseStatus(422);
        $response->seeJSON(['url' => ['The url field is required.']]);
    }

    public function testShowCover()
    {
        \Es::shouldReceive('index')->times(0);

        // Generate dummy data
        $doc = factory(Document::class)->create();
        $cover = factory(Cover::class)->make();
        $doc->cover()->save($cover);

        $this->get('/api/documents/1/cover')
            ->seeJSON(['url' => $cover->url]);
    }

    public function testPostDescription()
    {
        $faker = \Faker\Factory::create();
        \Es::shouldReceive('index')->times(1);

        // Generate dummy data
        factory(Document::class)->create();
        $postData = [
            'text' => $faker->text,
            'source' => $faker->sentence(),
            'source_url' => $faker->url,
        ];

        $this->post('/api/documents/1/description', $postData)
            ->assertResponseOk();

        $doc = Document::find(1);
        $this->assertSame($postData['text'], $doc->description['text']);
        $this->assertSame($postData['source'], $doc->description['source']);
        $this->assertSame($postData['source_url'], $doc->description['source_url']);
    }

    public function testPostDescriptionInvalidRequest()
    {
        \Es::shouldReceive('index')->times(0);

        // Generate dummy data
        factory(Document::class)->create();

        $response = $this->post('/api/documents/1/description', ['text' => 'Some description'], ['Accept' => 'application/json']);
        $response->assertResponseStatus(422);
        $response->seeJSON(['source' => ['The source field is required.']]);
    }

}
