<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('password');
            $table->string('cpf', 11)->nullable()->unique()->after('email');
            $table->boolean('deve_trocar_senha')->default(false)->after('ativo');
            $table->foreignId('filial_padrao_id')->nullable()->after('deve_trocar_senha')
                ->constrained('filiais')->nullOnDelete();
            $table->timestamp('ultimo_login_at')->nullable()->after('filial_padrao_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('filial_padrao_id');
            $table->dropColumn(['ativo', 'cpf', 'deve_trocar_senha', 'ultimo_login_at']);
        });
    }
};
