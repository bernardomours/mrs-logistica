<?php

namespace App\Actions\Locais;

use App\Models\Local;

class CriarLocal
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(array $dados): Local
    {
        return Local::create($dados);
    }
}
