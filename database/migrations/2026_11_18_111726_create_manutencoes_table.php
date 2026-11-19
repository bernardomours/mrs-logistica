<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Manutenção de veículo (CLAUDE.md §5.2, RN-20). Não entra na cadeia de hodômetro nem no líquido semanal. */
    public function up(): void
    {
        Schema::create('manutencoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->restrictOnDelete();

            $table->date('data_manutencao');
            $table->string('tipo', 20);
            $table->text('descricao');
            $table->string('oficina')->nullable();
            $table->decimal('odometro_km', 10, 1)->nullable();
            $table->decimal('valor_pecas', 12, 2)->default(0);
            $table->decimal('valor_mao_obra', 12, 2)->default(0);
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->string('nota_fiscal', 50)->nullable();

            // Alerta de preventiva (RN-13)
            $table->decimal('proxima_km', 10, 1)->nullable();
            $table->date('proxima_data')->nullable();
            $table->text('observacoes')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['veiculo_id', 'data_manutencao']);
            $table->index(['filial_id', 'data_manutencao']);
            $table->index(['motorista_id', 'data_manutencao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manutencoes');
    }
};
