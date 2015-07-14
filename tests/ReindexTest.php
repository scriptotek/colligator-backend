<?php

use Colligator\Console\Commands\Reindex;
use Colligator\Document;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Helpers\ConsoleCommandTestHelper;

class ReindexTest extends TestCase
{
    use DatabaseMigrations;
    use ConsoleCommandTestHelper;

    protected $baseVersion;
    protected $newVersion;
    protected $doc;
    protected $docIndexMock;

    protected function setupMock()
    {
        $faker = Faker::create();
        $this->baseVersion = $faker->randomNumber();
        $this->newVersion = $this->baseVersion + 1;

        $this->docIndexMock = Mockery::mock('Colligator\Search\DocumentsIndex');
        App::instance('Colligator\Search\DocumentsIndex', $this->docIndexMock);

        $this->docIndexMock->shouldReceive('getCurrentVersion')
            ->andReturn($this->baseVersion);
        $this->docIndexMock->shouldReceive('createVersion')
            ->with($this->newVersion)
            ->once();
        $this->docIndexMock->shouldReceive('buildCompleteUsageCache')
            ->once();

        $this->docIndexMock->shouldReceive('activateVersion')
            ->with($this->newVersion)
            ->once();
        $this->docIndexMock->shouldReceive('dropVersion')
            ->with($this->baseVersion)
            ->once();
    }

    protected function setupOneDocument()
    {
        $doc = factory(Document::class)->create();

        $this->docIndexMock->shouldReceive('index')
            ->once()
            ->with(\Mockery::on(function ($arg1) use ($doc) {
                $this->assertInstanceOf('Colligator\Document', $arg1);
                $this->assertSame($doc->id, $arg1->id);
                return true;
            }), $this->newVersion);
    }

    public function testOutputWhenNewVersionDoesntExist()
    {
        $this->setupMock();
        $this->setupOneDocument();

        $this->docIndexMock->shouldReceive('versionExists')
            ->with($this->newVersion)
            ->andReturn(false);

        $tester = $this->runConsoleCommand(new Reindex);

        $this->assertContains('Rebuilding the Elasticsearch index', $tester->getDisplay());
        $this->assertContains('Old version: ' . $this->baseVersion, $tester->getDisplay());
        $this->assertContains('new version: ' . $this->newVersion, $tester->getDisplay());
    }

    public function testOutputWhenNewVersionExists()
    {
        $this->setupMock();
        $this->setupOneDocument();

        $this->docIndexMock->shouldReceive('versionExists')
            ->with($this->newVersion)
            ->andReturn(true);

        $this->docIndexMock->shouldReceive('dropVersion')
            ->with($this->newVersion)
            ->once();

        $tester = $this->runConsoleCommand(new Reindex);

        $this->assertContains('New version already existed', $tester->getDisplay());
    }
}
