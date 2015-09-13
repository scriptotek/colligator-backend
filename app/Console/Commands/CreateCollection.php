<?php

namespace Colligator\Console\Commands;

use Colligator\Collection;
use Illuminate\Console\Command;

class CreateCollection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'colligator:create-collection
                            {name  : Name of the collection}
                            {label : A descriptionve label}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new collection.';

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
