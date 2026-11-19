<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filial_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'filial_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filial_user');
    }
};
