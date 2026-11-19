<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abastecimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->foreignId('motorista_id')->constrained('motoristas')->restrictOnDelete();
            $table->foreignId('posto_id')->nullable()->constrained('postos')->restrictOnDelete();
            $table->date('data_abastecimento');
            $table->decimal('litros', 9, 3);
            $table->decimal('preco_litro', 8, 3);
            $table->decimal('valor_total', 12, 2);
            $table->decimal('odometro_km', 10, 1);
            $table->decimal('km_anterior', 10, 1)->nullable();
            $table->decimal('km_rodados', 10, 1)->nullable();
            $table->decimal('media_km_l', 8, 4)->nullable();
            $table->boolean('tanque_cheio')->default(true);
            $table->string('combustivel', 20)->default('diesel');
            $table->string('nota_fiscal', 50)->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['veiculo_id', 'odometro_km']);
            $table->index(['motorista_id', 'data_abastecimento']);
            $table->index(['veiculo_id', 'data_abastecimento']);
            $table->index(['filial_id', 'data_abastecimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abastecimentos');
    }
};
