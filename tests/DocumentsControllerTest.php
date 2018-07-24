<?php

namespace Tests;

use Colligator\Cover;
use Colligator\Document;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class DocumentsControllerTest extends BrowserKitTestCase
{
    use DatabaseMigrations;
    use WithoutMiddleware;

    public function esMock()
    {
        $this->mock = \Mockery::mock('Elasticsearch\Client');
        \App::instance('Elasticsearch\Client', $this->mock);

        return $this->mock;
    }

    public function testPostingSmallCoverShouldNotCauseThumbnailGeneration()
    {
        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';

        $this->esMock()->shouldReceive('index')
            ->once()
            ->with(\Mockery::on(function ($doc) use ($exampleUrl) {
                $this->assertSame($exampleUrl, array_get($doc, 'body.cover.url'));

                return true;
            }));

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

        $this->post('/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeStatusCode(200)
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url'    => $exampleUrl]);
    }

    public function testPostigLargeCoverShouldCauseThumbnailGeneration()
    {
        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';

        $this->esMock()->shouldReceive('index')->once();

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

        $this->post('/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url'    => $exampleUrl]);
    }

    public function testPostingTheSameCoverTwiceShouldNotCauseTwoCachingRequests()
    {
        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';

        $this->esMock()->shouldReceive('index')->times(2);

        $mock = \Mockery::mock('Colligator\CachedImage');
        $mock->shouldReceive('width')->once()->andReturn(300);
        $mock->shouldReceive('height')->twice()->andReturn(500);
        $mock->shouldReceive('mime')->once()->andReturn('image/jpeg');
        $mock->shouldReceive('basename')->once()->andReturn('random');
        $mock->shouldReceive('thumb')->times(0);
        \CoverCache::shouldReceive('url')->andReturn('http://example.com/random');
        \CoverCache::shouldReceive('put')->once()->andReturn($mock);
        $doc = factory(Document::class)->create();

        $this->post('/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url'    => $exampleUrl]);

        $this->post('/documents/1/cover', ['url' => $exampleUrl], ['Accept' => 'application/json'])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url'    => $exampleUrl]);
    }

    public function testPostCoverInvalidRequest()
    {
        $this->esMock()->shouldReceive('index')->never();

        \CoverCache::shouldReceive('store')->times(0);

        // Generate dummy data
        factory(Document::class)->create();

        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';
        $response = $this->post('/documents/1/cover', ['urls' => $exampleUrl], ['Accept' => 'application/json']);
        $response->assertResponseStatus(422);
        $response->seeJSON(['url' => ['The url field is required.']]);
    }

    public function testShowCover()
    {
        $this->esMock()->shouldReceive('index')->never();

        // Generate dummy data
        $doc = factory(Document::class)->create();
        $cover = factory(Cover::class)->make();
        $doc->cover()->save($cover);

        $this->get('/documents/1/cover')
            ->seeJSON(['url' => $cover->url]);
    }

    public function testPostDescription()
    {
        $faker = \Faker\Factory::create();

        $this->esMock()->shouldReceive('index')->once();

        // Generate dummy data
        factory(Document::class)->create();
        $postData = [
            'text'       => $faker->text,
            'source'     => $faker->sentence(),
            'source_url' => $faker->url,
        ];

        $this->post('/documents/1/description', $postData)
            ->assertResponseOk();

        $doc = Document::find(1);
        $this->assertSame($postData['text'], $doc->description['text']);
        $this->assertSame($postData['source'], $doc->description['source']);
        $this->assertSame($postData['source_url'], $doc->description['source_url']);
    }

    public function testPostDescriptionInvalidRequest()
    {
        $this->esMock()->shouldReceive('index')->never();

        // Generate dummy data
        factory(Document::class)->create();

        $response = $this->post('/documents/1/description', ['text' => 'Some description'], ['Accept' => 'application/json']);
        $response->assertResponseStatus(422);
        $response->seeJSON(['source' => ['The source field is required.']]);
    }
}
