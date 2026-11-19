<?php

namespace App\Livewire\Concerns;

use App\Models\Motorista;
use App\Support\SemanaIso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Base das telas do portal do motorista (RN-21). O motorista é SEMPRE o vinculado ao usuário logado
 * (`motoristas.user_id`): nenhum identificador vem da URL, então não há como ver dados de outro.
 * Semana e mês nunca passam do atual (o motorista só vê o que já aconteceu).
 */
trait ComoPortalDoMotorista
{
    private const SEMANAS_NO_SELETOR = 26;

    private const MESES_NO_SELETOR = 12;

    protected function autorizarPortal(): void
    {
        abort_unless(Auth::user()->can('portal.acessar'), 403);

        $this->motorista();
    }

    protected function motorista(): Motorista
    {
        return Motorista::withoutGlobalScopes()->with('filial')->where('user_id', Auth::id())->first()
            ?? abort(403, 'Seu usuário não está vinculado a um motorista.');
    }

    /** Semana da chave ISO ("2026-W42"); vazia, inválida ou futura vira a semana atual. */
    protected function semanaEscolhida(string $chave): SemanaIso
    {
        $atual = SemanaIso::atual();
        $escolhida = SemanaIso::daChave($chave) ?? $atual;

        return $escolhida->inicio->greaterThan($atual->inicio) ? $atual : $escolhida;
    }

    /** Primeiro dia do mês de "2026-11"; vazio, inválido ou futuro vira o mês atual. */
    protected function mesEscolhido(string $valor): CarbonImmutable
    {
        $atual = CarbonImmutable::today()->startOfMonth();

        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $valor, $m)) {
            return $atual;
        }

        $escolhido = CarbonImmutable::create((int) $m[1], (int) $m[2], 1)->startOfDay();

        return $escolhido->greaterThan($atual) ? $atual : $escolhido;
    }

    /** @return Collection<int, array{chave: string, rotulo: string}> as últimas semanas, mais a escolhida se for mais antiga. */
    protected function opcoesDeSemana(SemanaIso $escolhida): Collection
    {
        $atual = SemanaIso::atual();

        $semanas = collect(range(0, self::SEMANAS_NO_SELETOR - 1))->map(function (int $i) use ($atual) {
            $s = SemanaIso::deData($atual->inicio->subWeeks($i));

            return ['chave' => $s->chave(), 'rotulo' => 'Semana '.$s->semana.' · '.$s->inicio->format('d/m').' a '.$s->fim->format('d/m').($i === 0 ? ' (atual)' : '')];
        });

        if (! $semanas->contains('chave', $escolhida->chave())) {
            $semanas->push(['chave' => $escolhida->chave(), 'rotulo' => 'Semana '.$escolhida->semana.' · '.$escolhida->inicio->format('d/m/Y').' a '.$escolhida->fim->format('d/m/Y')]);
        }

        return $semanas;
    }

    /** @return Collection<int, array{chave: string, rotulo: string}> os últimos meses, do atual para trás. */
    protected function opcoesDeMes(CarbonImmutable $escolhido): Collection
    {
        $atual = CarbonImmutable::today()->startOfMonth();

        $meses = collect(range(0, self::MESES_NO_SELETOR - 1))->map(fn (int $i) => $atual->subMonthsNoOverflow($i))
            ->map(fn (CarbonImmutable $m) => ['chave' => $m->format('Y-m'), 'rotulo' => ucfirst($m->translatedFormat('F/Y'))]);

        if (! $meses->contains('chave', $escolhido->format('Y-m'))) {
            $meses->push(['chave' => $escolhido->format('Y-m'), 'rotulo' => ucfirst($escolhido->translatedFormat('F/Y'))]);
        }

        return $meses;
    }
}
