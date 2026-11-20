<?php

namespace App\Services;

use App\Models\Filial;
use App\Models\User;
use App\Support\PapeisDoSistema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Regras de quem pode gerenciar quem (tela de Usuários). Usado pela Policy, pelas Actions e pelas telas.
 *
 *  - Usuários de motorista (papel `motorista`) são geridos só pelo cadastro de motoristas.
 *  - Só administrador mexe em administrador; os demais só concedem papéis cujas permissões eles próprios têm.
 *  - Quem não vê todas as filiais só gerencia usuários das suas filiais.
 *  - Nunca sobra o sistema sem administrador ativo.
 */
class GestaoDeUsuarios
{
    public function __construct(private readonly FilialAtual $filiais) {}

    public function ehUsuarioDeMotorista(User $alvo): bool
    {
        return $alvo->hasRole(PapeisDoSistema::MOTORISTA)
            || DB::table('motoristas')->where('user_id', $alvo->getKey())->exists();
    }

    public function podeGerenciar(User $ator, User $alvo): bool
    {
        if ($this->ehUsuarioDeMotorista($alvo)) {
            return false;
        }

        if ($ator->hasRole(PapeisDoSistema::ADMIN)) {
            return true;
        }

        if ($alvo->hasRole(PapeisDoSistema::ADMIN)) {
            return false;
        }

        foreach ($alvo->roles as $papel) {
            if (! $this->podeAtribuir($ator, $papel)) {
                return false;
            }
        }

        $permitidas = $this->filiais->permitidas($ator);

        if ($permitidas !== null) {
            if ($alvo->veTodasAsFiliais()) {
                return false;
            }

            foreach ($alvo->filiais()->pluck('filiais.id') as $filialId) {
                if (! in_array((int) $filialId, $permitidas, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function podeAtribuir(User $ator, Role $papel): bool
    {
        if ($papel->name === PapeisDoSistema::MOTORISTA) {
            return false;
        }

        if ($ator->hasRole(PapeisDoSistema::ADMIN)) {
            return true;
        }

        if ($papel->name === PapeisDoSistema::ADMIN) {
            return false;
        }

        return $papel->permissions->every(fn ($permissao) => $ator->can($permissao->name));
    }

    /** @return Collection<int, Role> */
    public function papeisAtribuiveis(User $ator): Collection
    {
        return Role::query()->with('permissions')->orderBy('name')->get()
            ->filter(fn (Role $papel) => $this->podeAtribuir($ator, $papel))
            ->values();
    }

    /** @return Collection<int, Filial> */
    public function filiaisAtribuiveis(User $ator): Collection
    {
        $permitidas = $this->filiais->permitidas($ator);

        return Filial::query()->ativas()
            ->when($permitidas !== null, fn (Builder $q) => $q->whereKey($permitidas))
            ->orderBy('nome')
            ->get(['id', 'nome', 'codigo']);
    }

    /** Usuários que o ator enxerga na lista (sem os de motorista; limitado às suas filiais). */
    public function consultaVisivelPara(User $ator): Builder
    {
        $permitidas = $this->filiais->permitidas($ator);

        return User::query()
            ->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', PapeisDoSistema::MOTORISTA))
            ->when($permitidas !== null, fn (Builder $q) => $q->whereHas('filiais', fn (Builder $f) => $f->whereIn('filiais.id', $permitidas)));
    }

    /** Este é o único administrador ativo? Então não pode ser desativado, excluído nem rebaixado. */
    public function eOUltimoAdministradorAtivo(User $alvo): bool
    {
        if (! $alvo->ativo || ! $alvo->hasRole(PapeisDoSistema::ADMIN)) {
            return false;
        }

        return User::role(PapeisDoSistema::ADMIN)->where('ativo', true)->count() <= 1;
    }

    /** O usuário já fez algo no sistema? Então não pode ser excluído (só desativado). */
    public function temHistorico(User $alvo): bool
    {
        $id = $alvo->getKey();

        $vinculos = [
            'viagens' => ['created_by', 'updated_by'],
            'abastecimentos' => ['created_by', 'updated_by'],
            'manutencoes' => ['created_by', 'updated_by'],
            'fechamentos_semanais' => ['fechado_por', 'pago_por'],
        ];

        foreach ($vinculos as $tabela => $colunas) {
            $consulta = DB::table($tabela);

            foreach ($colunas as $indice => $coluna) {
                $indice === 0 ? $consulta->where($coluna, $id) : $consulta->orWhere($coluna, $id);
            }

            if ($consulta->exists()) {
                return true;
            }
        }

        return DB::table(config('activitylog.table_name', 'activity_log'))
            ->where('causer_type', $alvo->getMorphClass())
            ->where('causer_id', $id)
            ->exists();
    }

    /**
     * Confere papel e filiais escolhidos para um usuário.
     *
     * @param  array<int, int>  $filiaisIds
     *
     * @throws ValidationException
     */
    public function validarVinculos(User $ator, string $papel, array $filiaisIds, ?int $filialPadraoId, ?string $papelAtual = null): Role
    {
        $role = Role::query()->with('permissions')->where('name', $papel)->where('guard_name', 'web')->first();

        $mantemPapel = $papelAtual !== null && $papelAtual === $papel;

        if ($role === null || (! $mantemPapel && ! $this->podeAtribuir($ator, $role)) || $papel === PapeisDoSistema::MOTORISTA) {
            throw ValidationException::withMessages(['papel' => 'Você não pode atribuir este papel.']);
        }

        $permitidas = $this->filiais->permitidas($ator);

        foreach ($filiaisIds as $filialId) {
            if ($permitidas !== null && ! in_array($filialId, $permitidas, true)) {
                throw ValidationException::withMessages(['filiais' => 'Você não pode vincular uma das filiais escolhidas.']);
            }
        }

        if (Filial::query()->whereKey($filiaisIds)->count() !== count(array_unique($filiaisIds))) {
            throw ValidationException::withMessages(['filiais' => 'Uma das filiais escolhidas não existe.']);
        }

        if ($filialPadraoId !== null && ! in_array($filialPadraoId, $filiaisIds, true)) {
            throw ValidationException::withMessages(['filial_padrao_id' => 'A filial padrão precisa estar entre as filiais do usuário.']);
        }

        return $role;
    }

    /** Derruba as sessões abertas do usuário (somente com sessão em banco). */
    public function encerrarSessoes(User $alvo): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $alvo->getKey())->delete();
        }
    }
}
