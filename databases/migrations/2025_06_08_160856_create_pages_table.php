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
        $table = config('translatable.tables.pages');
        Schema::create($table, function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('uri')
                ->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table = config('translatable.tables.pages');
        Schema::dropIfExists($table);
    }
};
