<?php

namespace App\Policies;

class VeiculoPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'veiculos';
    }
}
