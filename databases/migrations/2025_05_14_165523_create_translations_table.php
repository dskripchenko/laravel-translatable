<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $table = config('translatable.tables.translations');
        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('language_id')->index();
            $table->string('group', 128)->index();
            $table->string('key', 128)->index();
            $table->string('type')->default('default');
            $table->string('entity', 128)->default('');
            $table->string('entity_id', 64)->default('');
            $table->text('content');
            $table->index(['entity_id', 'entity']);
            $table->unique(['language_id', 'group', 'key', 'entity', 'entity_id']);
            $table->foreign('language_id')
                ->references('id')
                ->on(config('translatable.tables.languages'))
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $table = config('translatable.tables.translations');
        Schema::dropIfExists($table);
    }
};
