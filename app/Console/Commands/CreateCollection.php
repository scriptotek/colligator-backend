<?php

namespace Colligator\Console\Commands;

use Colligator\Collection;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class CreateCollection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:create-collection {name} {label}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new collection.';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'Name of the collection'),
            array('label', InputArgument::REQUIRED, 'A descriptionve label'),
        );
    }

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $label = $this->argument('label');
        $c = Collection::firstOrNew(['name' => $name, 'label' => $label]);
        if ($c->isDirty()) {
            $this->info("Created collection '$name'.");
            $c->save();
        } else {
            $this->info("Collection '$name' already exists.");
        }
    }
}
