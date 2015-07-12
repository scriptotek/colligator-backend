<?php

use Colligator\Document;
use Colligator\Subject;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DocumentsIndexTest extends TestCase
{
    // Rollback after each test
    // use DatabaseTransactions;
    use DatabaseMigrations;  // TODO: Use DatabaseTransactions instead, but then we need to use mysql and migrate before running tests

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

        $se = app('Colligator\Search\DocumentsIndex');
        $se->addToSubjectUsageCache(range(1,5));
        $this->assertSame(3, $se->getSubjectUsageCount($sub1->id));
        $this->assertSame(1, $se->getSubjectUsageCount($sub2->id));
        $this->assertSame(0, $se->getSubjectUsageCount($sub3->id));
    }
}
