<?php

namespace App\Actions\Filiais;

use App\Models\Filial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CriarFilial
{
    /**
     * @param  array{nome: string, codigo: string, cnpj?: ?string, cidade?: ?string, uf?: ?string, telefone?: ?string, ativo?: bool}  $dados
     */
    public function handle(array $dados, ?User $criador = null): Filial
    {
        return DB::transaction(function () use ($dados, $criador) {
            $filial = Filial::create($dados);

            // Quem cria a filial passa a acessá-la (quem vê todas já enxerga de qualquer forma).
            if ($criador !== null && ! $criador->veTodasAsFiliais()) {
                $criador->filiais()->syncWithoutDetaching([$filial->getKey()]);
            }

            return $filial;
        });
    }
}
