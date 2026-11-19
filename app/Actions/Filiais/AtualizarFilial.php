<?php

namespace App\Actions\Filiais;

use App\Models\Filial;
use Illuminate\Validation\ValidationException;

class AtualizarFilial
{
    /**
     * @param  array{nome: string, codigo: string, cnpj?: ?string, cidade?: ?string, uf?: ?string, telefone?: ?string, ativo?: bool}  $dados
     */
    public function handle(Filial $filial, array $dados): Filial
    {
        // RN-17: filial com motoristas/veículos ativos não pode ser desativada.
        if (($dados['ativo'] ?? $filial->ativo) === false && $filial->ativo && $filial->temVinculosAtivos()) {
            throw ValidationException::withMessages([
                'ativo' => 'Esta filial possui motoristas ou veículos ativos e não pode ser desativada.',
            ]);
        }

        $filial->update($dados);

        return $filial;
    }
}
