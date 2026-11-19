<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fechamento semanal do motorista (CLAUDE.md §5.2, RN-11). Sem soft delete: reabrir apaga o snapshot
     * (o motivo vai para o log de auditoria).
     */
    public function up(): void
    {
        Schema::create('fechamentos_semanais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filial_id')->constrained('filiais')->restrictOnDelete();
            $table->foreignId('motorista_id')->constrained('motoristas')->restrictOnDelete();

            $table->unsignedSmallInteger('ano_iso');
            $table->unsignedTinyInteger('semana_iso');
            $table->date('data_inicio');
            $table->date('data_fim');

            $table->string('status', 20)->default('fechado');

            // Snapshot do resumo no momento do fechamento.
            $table->unsignedSmallInteger('qtd_viagens')->default(0);
            $table->decimal('total_faturamento', 12, 2)->default(0);
            $table->decimal('total_comissao', 12, 2)->default(0);
            $table->decimal('valor_a_pagar', 12, 2)->default(0);
            $table->decimal('total_abastecimento', 12, 2)->default(0);
            $table->decimal('total_liquido', 12, 2)->default(0);
            $table->decimal('total_litros', 11, 3)->default(0);
            $table->decimal('total_km', 12, 1)->default(0);
            $table->decimal('media_km_l_simples', 8, 4)->nullable();
            $table->decimal('media_km_l_ponderada', 8, 4)->nullable();

            $table->foreignId('fechado_por')->constrained('users')->restrictOnDelete();
            $table->dateTime('fechado_em');
            $table->foreignId('pago_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('pago_em')->nullable();
            $table->text('observacoes')->nullable();

            $table->timestamps();

            $table->unique(['motorista_id', 'ano_iso', 'semana_iso']);
            $table->index(['filial_id', 'data_inicio']);
            $table->index(['status', 'data_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamentos_semanais');
    }
};
