<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tryouts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('duration');
            $table->enum('status', ['draft', 'publish'])->default('draft');

            // Target komposisi
            $table->integer('twk_target')->default(0);
            $table->integer('tiu_target')->default(0);
            $table->integer('tkp_target')->default(0);

            // Passing grade
            $table->integer('twk_pg')->default(0);
            $table->integer('tiu_pg')->default(0);
            $table->integer('tkp_pg')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tryouts');
    }
};