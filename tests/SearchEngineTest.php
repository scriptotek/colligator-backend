<?php

use Colligator\Document;
use Colligator\Subject;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SearchEngineTest extends TestCase
{
    // Rollback after each test
    // use DatabaseTransactions;
    use DatabaseMigrations;  // TODO: Use DatabaseTransactions instead, but then we need to use mysql and migrate before running tests

    public function testIndexDocumentPayload()
    {
        $se = app('Colligator\SearchEngine');

        // Generate dummy data
        $doc = factory(Document::class)->create();
        factory(Subject::class, 5)
            ->make()
            ->each(function($subject) use ($doc) {
                $doc->subjects()->save($subject);
            });

        $pl = $se->indexDocumentPayload($doc);

        $this->assertSame($doc->id, $pl['id']);

        $this->assertSame($doc->bibsys_id, $pl['bibsys_id']);
        $this->assertSame($doc->bibliographic['title'], $pl['title']);

        $this->assertCount(5, $pl['real']);
        $this->assertCount(count($doc->bibliographic['isbns']), $pl['isbns']);

        // Original array should not be modified
        $this->assertArrayNotHasKey('real', $doc->bibliographic);

    }
}
