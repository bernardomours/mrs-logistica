<?php

namespace Tests;

use App\Models\Filial;
use App\Models\User;
use Database\Seeders\PapeisEPermissoesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Semeia papéis/permissões (precisa de banco migrado). */
    protected function semearPapeis(): void
    {
        $this->seed(PapeisEPermissoesSeeder::class);
    }

    /**
     * Cria um usuário ativo com o papel informado e acesso às filiais dadas.
     *
     * @param  array<int, Filial>  $filiais
     */
    protected function usuarioComPapel(string $papel, array $filiais = [], array $atributos = []): User
    {
        $this->semearPapeis();

        $usuario = User::factory()->create($atributos + ['ativo' => true, 'deve_trocar_senha' => false]);
        $usuario->assignRole($papel);

        if ($filiais !== []) {
            $usuario->filiais()->sync(array_map(fn (Filial $f) => $f->getKey(), $filiais));
        }

        return $usuario;
    }
}
