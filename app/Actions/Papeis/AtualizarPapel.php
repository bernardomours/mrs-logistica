<?php

namespace App\Actions\Papeis;

use App\Models\User;
use App\Support\PapeisDoSistema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Atualiza as permissões de um papel (e o nome, se for personalizado). Administrador e motorista são protegidos;
 * papéis padrão mantêm o nome (o sistema depende dele).
 */
class AtualizarPapel
{
    use ValidaPermissoesDoPapel;

    /**
     * @param  array<int, string>  $permissoes
     */
    public function handle(User $ator, Role $papel, string $nome, array $permissoes): Role
    {
        if (! $ator->can('papeis.gerenciar') || PapeisDoSistema::travado($papel->name)) {
            throw new AuthorizationException('Este papel não pode ser alterado.');
        }

        $nomeFinal = PapeisDoSistema::ehDoSistema($papel->name) ? $papel->name : $this->nomeValido($nome, $papel);
        $novas = $this->permissoesValidas($permissoes);
        $atuais = $papel->permissions()->pluck('name')->all();

        $adicionadas = array_values(array_diff($novas, $atuais));
        $removidas = array_values(array_diff($atuais, $novas));
        $this->garantirQueAtorPossui($ator, array_merge($adicionadas, $removidas));

        return DB::transaction(function () use ($ator, $papel, $nomeFinal, $novas, $adicionadas, $removidas) {
            $nomeAntes = $papel->name;

            $papel->name = $nomeFinal;
            $papel->save();
            $papel->syncPermissions($novas);

            if ($nomeAntes !== $nomeFinal || $adicionadas !== [] || $removidas !== []) {
                activity('papeis')->performedOn($papel)->causedBy($ator)
                    ->withProperties(['nome' => ['de' => $nomeAntes, 'para' => $nomeFinal], 'adicionadas' => $adicionadas, 'removidas' => $removidas])
                    ->log('Papel atualizado');
            }

            return $papel;
        });
    }
}
