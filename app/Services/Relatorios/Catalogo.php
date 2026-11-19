<?php

namespace App\Services\Relatorios;

use App\Models\User;
use Illuminate\Support\Collection;

/** Lista dos relatórios e a regra de quem pode vê-los (`relatorios.ver` + as permissões financeiras de cada um). */
class Catalogo
{
    /** @var array<int, class-string<Relatorio>> */
    private const RELATORIOS = [
        ConsumoPorVeiculo::class,
        FaturamentoPorRota::class,
        ComissoesPorMotorista::class,
        CustoDeManutencao::class,
        ReceitasEDespesas::class,
        Inconsistencias::class,
    ];

    /** @return Collection<string, Relatorio> indexados pela chave da URL */
    public function todos(): Collection
    {
        return collect(self::RELATORIOS)->mapWithKeys(fn (string $classe) => [$classe::chave() => app($classe)]);
    }

    public function porChave(string $chave): ?Relatorio
    {
        return $this->todos()->get($chave);
    }

    public function podeVer(User $usuario, Relatorio $relatorio): bool
    {
        return $usuario->can('relatorios.ver')
            && collect($relatorio->permissoes())->every(fn (string $permissao) => $usuario->can($permissao));
    }

    /** @return Collection<string, Relatorio> */
    public function visiveisPara(User $usuario): Collection
    {
        return $this->todos()->filter(fn (Relatorio $r) => $this->podeVer($usuario, $r));
    }
}
