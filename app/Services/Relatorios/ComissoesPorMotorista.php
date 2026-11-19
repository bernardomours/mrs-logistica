<?php

namespace App\Services\Relatorios;

use App\Enums\FechamentoStatus;
use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Models\Viagem;
use App\Support\Dinheiro;
use App\Support\PeriodoRelatorio;
use Illuminate\Support\Collection;

/**
 * Comissão por motorista: o que foi gerado pelas viagens concluídas do período e, dos fechamentos cuja semana
 * começa no período, quanto já foi pago e quanto está fechado aguardando pagamento.
 */
class ComissoesPorMotorista implements Relatorio
{
    public static function chave(): string
    {
        return 'comissoes';
    }

    public function titulo(): string
    {
        return 'Comissões';
    }

    public function descricao(): string
    {
        return 'Comissão gerada por motorista e a situação do pagamento das semanas fechadas.';
    }

    public function permissoes(): array
    {
        return ['comissoes.ver'];
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'motorista', 'rotulo' => 'Motorista', 'tipo' => 'texto'],
            ['chave' => 'viagens', 'rotulo' => 'Viagens', 'tipo' => 'inteiro', 'somar' => true],
            ['chave' => 'frete', 'rotulo' => 'Frete (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'comissao', 'rotulo' => 'Comissão gerada (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'a_pagar', 'rotulo' => 'Fechada, a pagar (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'pago', 'rotulo' => 'Paga (R$)', 'tipo' => 'dinheiro', 'somar' => true],
        ];
    }

    public function linhas(PeriodoRelatorio $periodo): Collection
    {
        $viagens = Viagem::query()->concluidas()
            ->whereBetween('data_viagem', [$periodo->de(), $periodo->ate()])
            ->selectRaw('motorista_id, count(*) as qtd, sum(valor_frete) as frete, sum(comissao_valor) as comissao')
            ->groupBy('motorista_id')->get()->keyBy('motorista_id');

        $fechamentos = FechamentoSemanal::query()
            ->whereBetween('data_inicio', [$periodo->de(), $periodo->ate()])
            ->get(['motorista_id', 'status', 'valor_a_pagar'])->groupBy('motorista_id');

        $ids = $viagens->keys()->merge($fechamentos->keys())->unique();
        $nomes = Motorista::query()->whereIn('id', $ids)->pluck('nome', 'id');

        return $ids->map(function ($id) use ($viagens, $fechamentos, $nomes) {
            $v = $viagens->get($id);
            $f = $fechamentos->get($id, collect());
            $soma = fn (FechamentoStatus $status) => Dinheiro::total($f->filter(fn ($x) => $x->status === $status)->pluck('valor_a_pagar'));

            return [
                'motorista' => $nomes[$id] ?? '—',
                'viagens' => (int) ($v->qtd ?? 0),
                'frete' => Dinheiro::total([(string) ($v->frete ?? 0)]),
                'comissao' => Dinheiro::total([(string) ($v->comissao ?? 0)]),
                'a_pagar' => $soma(FechamentoStatus::Fechado),
                'pago' => $soma(FechamentoStatus::Pago),
            ];
        })->sortBy('motorista')->values();
    }
}
