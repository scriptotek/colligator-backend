<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Colligator\Document;
use Colligator\Cover;

class CoversControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function testPost()
    {
        \Es::shouldReceive('index')->times(1);
        \CoverCache::shouldReceive('store')->once()->andReturn('some-path');
        \CoverCache::shouldReceive('has')->andReturn(false);
        \CoverCache::shouldReceive('url')->andReturn('some-url');
        \CoverCache::shouldReceive('getDimensions')->andReturn([0 => 200, 1 => 200, 'mime' => 'image/jpeg']);

        // Generate dummy data
        $doc = factory(Document::class)->create();

        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';
        $this->post('/api/documents/1/covers', ['url' => $exampleUrl])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url' => $exampleUrl]);
    }

    public function testIndex()
    {
        \Es::shouldReceive('index')->times(0);

        // Generate dummy data
        $doc = factory(Document::class)->create();
        factory(Cover::class, 2)
            ->make()
            ->each(function($cover) use ($doc) {
                $doc->covers()->save($cover);
            });

        $urls = [];
        foreach ($doc->covers as $c) {
            $urls[] = $c->url;
        }

        $this->get('/api/documents/1/covers')
            ->seeJSON(['url' => $urls[0]])
            ->seeJSON(['url' => $urls[1]]);
    }

}
