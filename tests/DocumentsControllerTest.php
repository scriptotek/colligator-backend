<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Colligator\Document;
use Colligator\Cover;

class DocumentsControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function testPostCover()
    {
        \Es::shouldReceive('index')->times(1);
        \CoverCache::shouldReceive('store')->once()->andReturn('some-path');
        \CoverCache::shouldReceive('has')->andReturn(false);
        \CoverCache::shouldReceive('url')->andReturn('some-url');
        \CoverCache::shouldReceive('getDimensions')->andReturn([0 => 200, 1 => 200, 'mime' => 'image/jpeg']);

        // Generate dummy data
        $doc = factory(Document::class)->create();

        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';
        $this->post('/api/documents/1/cover', ['url' => $exampleUrl])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url' => $exampleUrl]);
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

}
