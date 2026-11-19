<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social', 150);
            $table->string('nome_fantasia', 150)->nullable();
            $table->string('cnpj', 14)->nullable()->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('razao_social');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
