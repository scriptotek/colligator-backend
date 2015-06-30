<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Scriptotek\SimpleMarcParser\BibliographicRecord;
use Colligator\Jobs\ImportMarc21Record;

class ImportMarc21RecordTest extends TestCase
{

    use DatabaseMigrations;

    public function testImport()
    {
        $bib = new BibliographicRecord;
        $bib->id = "abc123";
        $bib->created = Carbon::createFromDate(1985, 3, 25);

        $mock = \Mockery::mock('Colligator\Document');
        $this->app->instance('Colligator\Document', $mock);
        $mock->shouldReceive('save')->andReturn(false);

        // TODO: Mocken funker ikke
        // $importer = new ImportMarc21Record;
        // $importer->import($bib->toArray());
        // $this->assertSame('1985-03-25T00:00:00', $mockDoc->bibliographic['created']);
    }

}
