<?php

use Colligator\Document;
use Colligator\Jobs\IndexDocument;
use Colligator\Subject;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class IndexDocumentTest extends TestCase
{
    // Rollback the database after each test
    // and migrate it before the next test
    use DatabaseMigrations;

    public function testgenerateElasticSearchPayload()
    {
        // TODO: Sjekke ut hvordan man raskt genererer dummydata!
        $doc = new Document;
        $doc->bibsys_id = 'x';
        $doc->holdings = [];
        $doc->bibliographic = [
            'isbns' => ['isbn1', 'isbn2'],
        ];
        $doc->save();
        $subj = new Subject(['term' => 'TestTerm', 'vocabulary' => 'noubomn']);
        $doc->subjects()->save($subj);

        $job = new IndexDocument($doc->id);
        $pl = $job->generateElasticSearchPayload();
        $this->assertContains('isbn1', $pl['isbn']);
    }
}
