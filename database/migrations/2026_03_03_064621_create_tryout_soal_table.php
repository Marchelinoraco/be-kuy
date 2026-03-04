<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tryout_soal', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tryout_id')
                  ->constrained('tryouts')
                  ->cascadeOnDelete();

            $table->foreignId('soal_id')
                  ->constrained('soals')
                  ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tryout_soal');
    }
};