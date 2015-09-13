<?php

use Colligator\Collection;
use Colligator\Console\Commands\CreateCollection;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Helpers\ConsoleCommandTestHelper;

class CreateCollectionCommandTest extends TestCase
{
    use DatabaseMigrations;
    use ConsoleCommandTestHelper;

    protected $name;
    protected $label;

    public function setUp()
    {
        parent::setUp();
        $faker = Faker::create();
        $this->name = $faker->word;
        $this->label = $faker->sentence(3);
    }

    public function testCreateCollectionThatDoesntExist()
    {
        $tester = $this->runConsoleCommand(new CreateCollection(), ['name' => $this->name, 'label' => $this->label]);

        $this->assertContains("Created collection '$this->name'.", $tester->getDisplay());
    }

    public function testCreateCollectionThatAlreadyExists()
    {
        Collection::create(['name' => $this->name, 'label' => $this->label]);

        $tester = $this->runConsoleCommand(new CreateCollection(), ['name' => $this->name, 'label' => $this->label]);

        $this->assertContains("Collection '$this->name' already exists.", $tester->getDisplay());
    }
}
