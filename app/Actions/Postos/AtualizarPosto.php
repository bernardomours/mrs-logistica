<?php

namespace App\Actions\Postos;

use App\Models\Posto;

class AtualizarPosto
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(Posto $posto, array $dados): Posto
    {
        $posto->update($dados);

        return $posto;
    }
}
