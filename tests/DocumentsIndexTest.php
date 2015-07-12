<?php

use Colligator\Document;
use Colligator\Genre;
use Colligator\Search\DocumentsIndex;
use Colligator\Subject;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DocumentsIndexTest extends TestCase
{
    // Rollback after each test
    // use DatabaseTransactions;
    use DatabaseMigrations;  // TODO: Use DatabaseTransactions instead, but then we need to use mysql and migrate before running tests

    /**
     * @return DocumentsIndex
     */
    public function getDocumentsIndex()
    {
        return app('Colligator\Search\DocumentsIndex');
    }

    public function testUsageCache()
    {
        // Generate dummy data
        $docs = factory(Document::class, 5)->create();

        $sub1 = factory(Subject::class)->create();
        $sub2 = factory(Subject::class)->create();
        $sub3 = factory(Subject::class)->create();

        $gen1= factory(Genre::class)->create();
        $gen2 = factory(Genre::class)->create();

        $docs[0]->subjects()->save($sub1);
        $docs[1]->subjects()->save($sub1);
        $docs[2]->subjects()->save($sub1);
        $docs[0]->subjects()->save($sub2);

        $docs[0]->genres()->save($gen1);
        $docs[1]->genres()->save($gen1);
        $docs[1]->genres()->save($gen2);

        $docIndex = $this->getDocumentsIndex();
        $docIndex->addToUsageCache(range(1, 5), 'subject');
        $this->assertSame(3, $docIndex->getUsageCount($sub1->id, 'subject'));
        $this->assertSame(1, $docIndex->getUsageCount($sub2->id, 'subject'));
        $this->assertSame(0, $docIndex->getUsageCount($sub3->id, 'subject'));

        $this->assertSame(2, $docIndex->getUsageCount($gen1->id, 'genre'));
        $this->assertSame(1, $docIndex->getUsageCount($gen2->id, 'genre'));
    }
}
