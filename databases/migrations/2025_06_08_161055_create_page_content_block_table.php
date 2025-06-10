<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table = config('translatable.tables.page_content_block');
        Schema::create($table, function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('page_id')
                ->index();

            $table->unsignedBigInteger('content_block_id')
                ->index();

            $table->unique(['page_id', 'content_block_id']);

            $table->foreign('page_id')
                ->references('id')
                ->on('pages')
                ->on(config('translatable.tables.pages'))
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('content_block_id')
                ->references('id')
                ->on(config('translatable.tables.content_blocks'))
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table = config('translatable.tables.page_content_block');
        Schema::dropIfExists($table);
    }
};
