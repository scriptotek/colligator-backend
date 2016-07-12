<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterIds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `documents` MODIFY COLUMN `bibsys_id` VARCHAR(50)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // We cannot really go back, since trimming IDs will cause key constraints
        // DB::statement('ALTER TABLE `documents` MODIFY COLUMN `bibsys_id` VARCHAR(12)');
    }
}
