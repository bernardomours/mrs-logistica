<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->string('placa', 7)->unique();
            $table->string('tipo', 20);
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->unsignedSmallInteger('ano_fabricacao')->nullable();
            $table->string('renavam', 11)->nullable();
            $table->decimal('capacidade_tanque_l', 7, 2)->nullable();
            $table->decimal('odometro_inicial', 10, 1)->nullable();
            $table->decimal('consumo_referencia_km_l', 5, 2)->nullable();
            $table->foreignId('motorista_padrao_id')->nullable()->constrained('motoristas')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['filial_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};
