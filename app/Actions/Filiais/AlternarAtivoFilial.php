<?php

namespace App\Actions\Filiais;

use App\Models\Filial;
use Illuminate\Validation\ValidationException;

class AlternarAtivoFilial
{
    public function handle(Filial $filial): Filial
    {
        if ($filial->ativo && $filial->temVinculosAtivos()) {
            throw ValidationException::withMessages([
                'ativo' => 'Esta filial possui motoristas ou veículos ativos e não pode ser desativada.',
            ]);
        }

        $filial->update(['ativo' => ! $filial->ativo]);

        return $filial;
    }
}
