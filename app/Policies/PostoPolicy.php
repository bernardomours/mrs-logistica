<?php

namespace App\Policies;

class PostoPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'postos';
    }
}
