<?php

namespace Tests;

use Carbon\Carbon;
use Colligator\Marc21Importer;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Scriptotek\SimpleMarcParser\BibliographicRecord;

class ImportMarc21RecordTest extends BrowserKitTestCase
{
    use DatabaseMigrations;

    public function testImport()
    {
        $bib = new BibliographicRecord();
        $bib->id = 'abc123';
        $bib->created = Carbon::createFromDate(1985, 3, 25);

        $mock = \Mockery::mock('Colligator\Document');
        $this->app->instance('Colligator\Document', $mock);
        $mock->shouldReceive('save')->andReturn(false);

        // TODO: Mocken funker ikke
        // $importer = new Marc21Importer;
        // $importer->import($bib->toArray());
        // $this->assertSame('1985-03-25T00:00:00', $mockDoc->bibliographic['created']);
    }
}
