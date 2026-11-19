<?php

namespace App\Actions\Postos;

use App\Models\Posto;

class CriarPosto
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(array $dados): Posto
    {
        return Posto::create($dados);
    }
}
