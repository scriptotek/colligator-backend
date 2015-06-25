<?php

use Colligator\Document;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DocumentTest extends TestCase
{

    // Rollback the database after each test
    // and migrate it before the next test
    use DatabaseMigrations;

    public function testSave()
    {
        $d = new Document;
        $d->bibsys_id = '123';
        $d->bibliographic = [];
        $d->holdings = [];
        $d->save();

        $d = Document::where('bibsys_id', '=', '123')->first();
        $this->assertEquals('123', $d->bibsys_id);
    }

}
