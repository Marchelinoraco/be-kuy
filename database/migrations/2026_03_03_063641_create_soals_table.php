<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soals', function (Blueprint $table) {
            $table->id();

            $table->text('question');

            $table->enum('category', ['TWK', 'TIU', 'TKP']);

            $table->string('sub_category')->nullable();

            $table->string('difficulty')->nullable();

            $table->json('options'); // penting untuk opsi jawaban

            $table->string('correct_answer')->nullable(); // kosong untuk TKP

            $table->text('explanation')->nullable();

            $table->enum('status', ['aktif', 'nonaktif'])
                  ->default('aktif');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soals');
    }
};