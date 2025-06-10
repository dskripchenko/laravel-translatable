<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContentBlocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table = config('translatable.tables.content_blocks');
        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->string('description')->index();
            $table->string('type')->default('text');
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table = config('translatable.tables.content_blocks');
        Schema::dropIfExists($table);
    }
}
