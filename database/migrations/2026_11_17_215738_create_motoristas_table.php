<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motoristas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->string('nome', 150);
            $table->string('cpf', 11)->unique();
            $table->string('cnh_numero', 20)->nullable();
            $table->string('cnh_categoria', 3)->nullable();
            $table->date('cnh_validade')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->decimal('comissao_percentual', 5, 2);
            $table->date('data_admissao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['filial_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motoristas');
    }
};
