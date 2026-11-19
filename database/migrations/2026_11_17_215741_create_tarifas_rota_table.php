<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_rota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('origem_local_id')->constrained('locais')->restrictOnDelete();
            $table->foreignId('destino_local_id')->constrained('locais')->restrictOnDelete();
            $table->decimal('valor_frete', 12, 2);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['origem_local_id', 'destino_local_id', 'vigencia_inicio'], 'tarifas_rota_busca_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_rota');
    }
};
