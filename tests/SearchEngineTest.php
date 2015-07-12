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
            ->each(function ($subject) use ($doc) {
                $doc->subjects()->save($subject);
            });

        $doc = Document::with('subjects')->first();
        $doc->description = ['text' => 'Bla bla bla', 'source' => 'Selfmade'];

        $pl = $se->indexDocumentPayload($doc);

        $this->assertSame($doc->id, $pl['id']);

        $this->assertSame($doc->bibsys_id, $pl['bibsys_id']);
        $this->assertSame($doc->bibliographic['title'], $pl['title']);

        $this->assertArrayHasKey('noubomn', $pl['subjects']);
        $this->assertCount(count($doc->bibliographic['isbns']), $pl['isbns']);

        // Original array should not be modified
        $this->assertArrayNotHasKey('real', $doc->bibliographic);

        $this->assertSame('Bla bla bla', $pl['description']['text']);
    }

    public function testSubjectUsageCache()
    {
        // Generate dummy data
        $docs = factory(Document::class, 5)->create();

        $sub1 = factory(Subject::class)->create();
        $sub2 = factory(Subject::class)->create();
        $sub3 = factory(Subject::class)->create();

        $docs[0]->subjects()->save($sub1);
        $docs[1]->subjects()->save($sub1);
        $docs[2]->subjects()->save($sub1);
        $docs[0]->subjects()->save($sub2);

        $se = app('Colligator\SearchEngine');
        $se->addToSubjectUsageCache(range(1,5));
        $this->assertSame(3, $se->getSubjectUsageCount($sub1->id));
        $this->assertSame(1, $se->getSubjectUsageCount($sub2->id));
        $this->assertSame(0, $se->getSubjectUsageCount($sub3->id));
    }
}
