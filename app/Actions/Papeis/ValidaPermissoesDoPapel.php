<?php

namespace App\Actions\Papeis;

use App\Models\User;
use App\Support\CatalogoDePermissoes;
use App\Support\PapeisDoSistema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Regras comuns de nome e permissões ao criar/atualizar papéis. */
trait ValidaPermissoesDoPapel
{
    protected function nomeValido(string $nome, ?Role $atual = null): string
    {
        $nome = trim(preg_replace('/\s+/', ' ', $nome));

        if ($nome === '' || mb_strlen($nome) > 60 || ! preg_match('/^[\pL\pN _-]+$/u', $nome)) {
            throw ValidationException::withMessages(['nome' => 'Use de 1 a 60 letras, números, espaços, sublinhado ou hífen.']);
        }

        $reservado = collect(PapeisDoSistema::padrao())
            ->contains(fn (string $padrao) => mb_strtolower($padrao) === mb_strtolower($nome) || mb_strtolower(PapeisDoSistema::rotulo($padrao)) === mb_strtolower($nome));

        $duplicado = Role::query()->where('guard_name', 'web')->get(['id', 'name'])
            ->contains(fn (Role $papel) => mb_strtolower($papel->name) === mb_strtolower($nome) && $papel->isNot($atual));

        if (($reservado && $atual?->name !== $nome) || $duplicado) {
            throw ValidationException::withMessages(['nome' => 'Já existe um papel com este nome.']);
        }

        return $nome;
    }

    /**
     * @param  array<int, string>  $permissoes
     * @return array<int, string>
     */
    protected function permissoesValidas(array $permissoes): array
    {
        $permissoes = array_values(array_unique($permissoes));

        if (array_diff($permissoes, array_keys(CatalogoDePermissoes::todas())) !== []) {
            throw ValidationException::withMessages(['permissoes' => 'Há permissões inválidas na seleção.']);
        }

        return $permissoes;
    }

    /**
     * Quem edita papéis só adiciona ou remove permissões que ele mesmo possui.
     *
     * @param  array<int, string>  $permissoes
     */
    protected function garantirQueAtorPossui(User $ator, array $permissoes): void
    {
        foreach ($permissoes as $permissao) {
            if (! $ator->can($permissao)) {
                throw ValidationException::withMessages([
                    'permissoes' => 'Você não pode conceder nem retirar permissões que você mesmo não tem ('.CatalogoDePermissoes::rotulo($permissao).').',
                ]);
            }
        }
    }
}
