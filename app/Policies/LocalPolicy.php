<?php

namespace App\Policies;

class LocalPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'locais';
    }
}
