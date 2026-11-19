<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->foreignId('motorista_id')->constrained('motoristas')->restrictOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->foreignId('origem_local_id')->constrained('locais')->restrictOnDelete();
            $table->foreignId('destino_local_id')->constrained('locais')->restrictOnDelete();
            $table->foreignId('tarifa_rota_id')->nullable()->constrained('tarifas_rota')->nullOnDelete();
            $table->date('data_viagem');
            $table->decimal('valor_frete', 12, 2);
            $table->decimal('comissao_percentual', 5, 2);
            $table->decimal('comissao_valor', 12, 2);
            $table->string('status', 20)->default('concluida');
            $table->string('numero_cte', 50)->nullable();
            $table->string('carga_descricao', 150)->nullable();
            $table->decimal('peso_kg', 10, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['motorista_id', 'data_viagem']);
            $table->index(['veiculo_id', 'data_viagem']);
            $table->index(['filial_id', 'data_viagem']);
            $table->index('data_viagem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viagens');
    }
};
