<?php

use Colligator\Collection;
use Colligator\Document;
use Colligator\Genre;
use Colligator\Http\Requests\SearchDocumentsRequest;
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

    public function testSimpleSubjectQueryString()
    {
        $request = new SearchDocumentsRequest(['subject' => 'Naturvitenskap']);
        $docIndex = $this->getDocumentsIndex();

        $this->assertSame('(subjects.noubomn.prefLabel:"Naturvitenskap" OR subjects.NOTrBIB.prefLabel:"Naturvitenskap" OR genres.noubomn.prefLabel:"Naturvitenskap")', $docIndex->queryStringFromRequest($request));
    }

    public function testComplexSubjectQueryString()
    {
        $request = new SearchDocumentsRequest(['subject' => 'Naturvitenskap : Filosofi']);
        $docIndex = $this->getDocumentsIndex();

        $this->assertSame('(subjects.noubomn.prefLabel:"Naturvitenskap \\: Filosofi" OR subjects.NOTrBIB.prefLabel:"Naturvitenskap \\: Filosofi" OR genres.noubomn.prefLabel:"Naturvitenskap \\: Filosofi")', $docIndex->queryStringFromRequest($request));
    }

    public function testCompoundQueryString()
    {
        $request = new SearchDocumentsRequest(['subject' => 'Naturvitenskap : Filosofi', 'q' => '_exists_:subjects.lcsh']);
        $docIndex = $this->getDocumentsIndex();

        $this->assertSame('_exists_:subjects.lcsh AND (subjects.noubomn.prefLabel:"Naturvitenskap \\: Filosofi" OR subjects.NOTrBIB.prefLabel:"Naturvitenskap \\: Filosofi" OR genres.noubomn.prefLabel:"Naturvitenskap \\: Filosofi")', $docIndex->queryStringFromRequest($request));
    }

    public function testCollectionQueryString()
    {
        $collections = factory(Collection::class, 5)->create();
        $request1 = new SearchDocumentsRequest(['collection' => '1']);
        $request2 = new SearchDocumentsRequest(['collection' => '3']);
        $docIndex = $this->getDocumentsIndex();

        $this->assertSame('collections:"' . $collections[0]->name . '"', $docIndex->queryStringFromRequest($request1));
        $this->assertSame('collections:"' . $collections[2]->name . '"', $docIndex->queryStringFromRequest($request2));
    }

    public function testInvalidCollectionQueryString()
    {
        $request1 = new SearchDocumentsRequest(['collection' => '1']);
        $docIndex = $this->getDocumentsIndex();

        $this->setExpectedException('Colligator\Exceptions\CollectionNotFoundException');
        $docIndex->queryStringFromRequest($request1);
    }
}
