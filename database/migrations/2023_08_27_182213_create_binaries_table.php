<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('binaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('hash');
            $table->string('path'); // contains the name
            $table->string('name');
            $table->integer('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->unique(['path', 'name']);
        });

        DB::statement('ALTER TABLE binaries ADD content LONGBLOB AFTER `name`;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('binaries');
    }
};
