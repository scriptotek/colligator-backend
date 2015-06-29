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
        // Generate dummy data
        $doc = factory(Document::class)->create();

        $exampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg';
        $this->post('/documents/1/covers', ['url' => $exampleUrl])
            ->seeJSON(['result' => 'ok'])
            ->seeJson(['url' => $exampleUrl]);
    }

    public function testIndex()
    {
        // Generate dummy data
        $doc = factory(Document::class)->create();
        factory(Cover::class, 5)
            ->make()
            ->each(function($cover) use ($doc) {
                $doc->covers()->save($cover);
            });
            // RUN without CACHING!

        $urls = [];
        foreach ($doc->covers as $c) {
            $urls[] = $c->url;
        }

        $this->get('/documents/1/covers')
            ->seeJSON(['url' => $urls[0]]);
    }

}
