<?php

namespace Tests;

use Colligator\Document;
use Colligator\Search\SearchableDocument;
use Colligator\Subject;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SearchableDocumentTest extends BrowserKitTestCase
{
    // Rollback after each test
    // use DatabaseTransactions;
    use DatabaseMigrations;  // TODO: Use DatabaseTransactions instead, but then we need to use mysql and migrate before running tests

    public function testToArray()
    {
        // Generate dummy data
        $doc = factory(Document::class)->create();
        factory(Subject::class, 5)
            ->make()
            ->each(function ($subject) use ($doc) {
                $doc->subjects()->save($subject);
            });

        $doc = Document::with('subjects')->first();
        $doc->description = ['text' => 'Bla bla bla', 'source' => 'Selfmade'];

        $sdoc = $this->app->makeWith(SearchableDocument::class, ['doc' => $doc]);
        $pl = $sdoc->toArray();

        $this->assertSame($doc->id, $pl['id']);

        $this->assertSame($doc->bibsys_id, $pl['bibsys_id']);
        $this->assertSame($doc->bibliographic['title'], $pl['title']);

        $this->assertArrayHasKey('noubomn', $pl['subjects']);
        $this->assertCount(count($doc->bibliographic['isbns']), $pl['isbns']);

        // Original array should not be modified
        $this->assertArrayNotHasKey('real', $doc->bibliographic);

        $this->assertSame('Bla bla bla', $pl['description']['text']);
    }
}
