<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddThumbDimToCoversTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('covers', function (Blueprint $table) {
            $table->integer('thumb_width')->nullable()->unsigned();
            $table->integer('thumb_height')->nullable()->unsigned();
            $table->string('thumb_key', 50)->nullable();
            $table->string('cache_key', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('covers', function (Blueprint $table) {
            $table->dropColumn('thumb_width');
        });
        Schema::table('covers', function (Blueprint $table) {
            $table->dropColumn('thumb_height');
        });
        Schema::table('covers', function (Blueprint $table) {
            $table->dropColumn('thumb_key');
        });
        Schema::table('covers', function (Blueprint $table) {
            $table->dropColumn('cache_key');
        });
    }
}
