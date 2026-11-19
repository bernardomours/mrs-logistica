<?php

namespace App\Services\Relatorios;

use App\Models\Abastecimento;
use App\Models\Filial;
use App\Models\Manutencao;
use App\Models\Viagem;
use App\Support\Dinheiro;
use App\Support\Numero;
use App\Support\PeriodoRelatorio;
use Illuminate\Support\Collection;

/**
 * Receitas x despesas do período, uma linha por filial (as que o usuário acessa).
 * Receita = frete das viagens concluídas. Despesas = abastecimentos + manutenções + comissões dos motoristas.
 * Resultado = receitas − despesas. Diferente do líquido semanal do motorista (RN-05), aqui a manutenção
 * também é despesa, e vale a data de cada lançamento (a manutenção sem motorista também entra).
 */
class ReceitasEDespesas implements ComCartoes, ComDetalhes, Relatorio
{
    /** Máximo de linhas em cada tabela de detalhe na tela (os totais sempre contam tudo). */
    private const LIMITE_DETALHE = 500;

    public static function chave(): string
    {
        return 'receitas-e-despesas';
    }

    public function titulo(): string
    {
        return 'Receitas x Despesas';
    }

    public function descricao(): string
    {
        return 'Entradas de fretes contra abastecimentos, manutenções e comissões dos motoristas, com o resultado por filial.';
    }

    public function permissoes(): array
    {
        return ['viagens.ver_valores', 'comissoes.ver', 'manutencoes.ver_valores'];
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'filial', 'rotulo' => 'Filial', 'tipo' => 'texto'],
            ['chave' => 'viagens', 'rotulo' => 'Viagens', 'tipo' => 'inteiro', 'somar' => true],
            ['chave' => 'receitas', 'rotulo' => 'Receitas · Fretes (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'abastecimentos', 'rotulo' => 'Despesa · Abastecimentos (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'manutencoes', 'rotulo' => 'Despesa · Manutenções (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'comissoes', 'rotulo' => 'Despesa · Comissões (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'despesas', 'rotulo' => 'Total de despesas (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'resultado', 'rotulo' => 'Resultado (R$)', 'tipo' => 'dinheiro', 'somar' => true],
        ];
    }

    /**
     * Dois cartões grandes (total de receitas e de despesas) e quatro menores com o detalhe:
     * frete, comissões dos motoristas, abastecimentos e manutenções (as três últimas com a parte de cada uma nas despesas).
     */
    public function cartoes(Collection $linhas): array
    {
        $receitas = Dinheiro::total($linhas->pluck('receitas'));
        $despesas = Dinheiro::total($linhas->pluck('despesas'));
        $viagens = (int) $linhas->sum('viagens');

        $parte = function (string $chave) use ($linhas, $despesas): array {
            $valor = Dinheiro::total($linhas->pluck($chave));
            $pct = Dinheiro::dividir(Dinheiro::multiplicar($valor, '100', 4), $despesas, 1);

            return [
                'valor' => $valor,
                'detalhe' => $pct === null ? 'Sem despesas no período' : Numero::formatar($pct, 1).'% das despesas',
            ];
        };

        return [
            'principais' => [
                ['rotulo' => 'Total de receitas', 'valor' => $receitas, 'tom' => 'receita', 'detalhe' => 'Fretes das viagens concluídas'],
                ['rotulo' => 'Total de despesas', 'valor' => $despesas, 'tom' => 'despesa', 'detalhe' => 'Abastecimentos, manutenções e comissões'],
            ],
            'detalhes' => [
                ['rotulo' => 'Total de frete', 'valor' => $receitas, 'tom' => 'receita', 'detalhe' => $viagens.' '.($viagens === 1 ? 'viagem concluída' : 'viagens concluídas')],
                ['rotulo' => 'Comissões de motoristas', 'tom' => 'despesa'] + $parte('comissoes'),
                ['rotulo' => 'Abastecimentos', 'tom' => 'despesa'] + $parte('abastecimentos'),
                ['rotulo' => 'Manutenções', 'tom' => 'despesa'] + $parte('manutencoes'),
            ],
        ];
    }

    /**
     * Lado a lado: as viagens concluídas (receitas) e, no outro lado, cada abastecimento, manutenção e comissão
     * (despesas), do mais recente para o mais antigo. Cada lista mostra até LIMITE_DETALHE linhas; os totais são os do resumo.
     */
    public function detalhes(PeriodoRelatorio $periodo, Collection $linhas): array
    {
        $variasFiliais = $linhas->count() > 1;
        $filial = $variasFiliais ? [['chave' => 'filial', 'rotulo' => 'Filial', 'tipo' => 'texto']] : [];
        $entre = [$periodo->de(), $periodo->ate()];

        $viagens = Viagem::query()->concluidas()->with(['origem:id,nome', 'destino:id,nome', 'motorista:id,nome', 'filial:id,codigo'])
            ->whereBetween('data_viagem', $entre)->orderByDesc('data_viagem')->orderByDesc('id')->limit(self::LIMITE_DETALHE + 1)->get()->toBase();

        $receitas = $viagens->map(fn (Viagem $v) => [
            'data' => $v->data_viagem,
            'filial' => $v->filial->codigo,
            'motorista' => $v->motorista->nome,
            'rota' => $v->origem->nome.' → '.$v->destino->nome,
            'valor' => (string) $v->valor_frete,
        ]);

        $despesas = Abastecimento::query()->with(['veiculo:id,placa', 'motorista:id,nome', 'posto:id,nome', 'filial:id,codigo'])
            ->whereBetween('data_abastecimento', $entre)->orderByDesc('data_abastecimento')->orderByDesc('id')->limit(self::LIMITE_DETALHE + 1)->get()->toBase()
            ->map(fn (Abastecimento $a) => [
                'data' => $a->data_abastecimento, 'filial' => $a->filial->codigo, 'tipo' => 'Abastecimento',
                'descricao' => trim(($a->posto?->nome ?? 'Posto não informado').' · '.Numero::formatar($a->litros, 3).' L · '.$a->veiculo->placa_formatada.' · '.$a->motorista->nome),
                'valor' => (string) $a->valor_total,
            ])
            ->merge(Manutencao::query()->with(['veiculo:id,placa', 'filial:id,codigo'])
                ->whereBetween('data_manutencao', $entre)->orderByDesc('data_manutencao')->orderByDesc('id')->limit(self::LIMITE_DETALHE + 1)->get()->toBase()
                ->map(fn (Manutencao $m) => [
                    'data' => $m->data_manutencao, 'filial' => $m->filial->codigo, 'tipo' => 'Manutenção',
                    'descricao' => $m->tipo->rotulo().' · '.$m->descricao.' · '.$m->veiculo->placa_formatada,
                    'valor' => (string) $m->valor_total,
                ]))
            ->merge($viagens->map(fn (Viagem $v) => [
                'data' => $v->data_viagem, 'filial' => $v->filial->codigo, 'tipo' => 'Comissão',
                'descricao' => $v->motorista->nome.' · '.Numero::formatar($v->comissao_percentual).'% de '.Numero::formatar($v->valor_frete).' · '.$v->origem->nome.' → '.$v->destino->nome,
                'valor' => (string) $v->comissao_valor,
            ]))
            ->sortByDesc(fn (array $d) => $d['data']->timestamp)->values();

        $quantidadeReceitas = (int) $linhas->sum('viagens');
        $quantidadeDespesas = $quantidadeReceitas + $this->contarDespesas($entre);

        return [
            [
                'titulo' => 'Receitas', 'tom' => 'receita', 'quantidade' => $quantidadeReceitas,
                'total' => Dinheiro::total($linhas->pluck('receitas')),
                'truncado' => $receitas->count() > self::LIMITE_DETALHE,
                'vazio' => 'Nenhuma viagem concluída no período.',
                'colunas' => [['chave' => 'data', 'rotulo' => 'Data', 'tipo' => 'data'], ...$filial,
                    ['chave' => 'motorista', 'rotulo' => 'Motorista', 'tipo' => 'texto'],
                    ['chave' => 'rota', 'rotulo' => 'Rota', 'tipo' => 'texto'],
                    ['chave' => 'valor', 'rotulo' => 'Frete (R$)', 'tipo' => 'dinheiro']],
                'linhas' => $receitas->take(self::LIMITE_DETALHE)->values(),
            ],
            [
                'titulo' => 'Despesas', 'tom' => 'despesa', 'quantidade' => $quantidadeDespesas,
                'total' => Dinheiro::total($linhas->pluck('despesas')),
                'truncado' => $despesas->count() > self::LIMITE_DETALHE,
                'vazio' => 'Nenhuma despesa no período.',
                'colunas' => [['chave' => 'data', 'rotulo' => 'Data', 'tipo' => 'data'], ...$filial,
                    ['chave' => 'tipo', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['chave' => 'descricao', 'rotulo' => 'Descrição', 'tipo' => 'texto'],
                    ['chave' => 'valor', 'rotulo' => 'Valor (R$)', 'tipo' => 'dinheiro']],
                'linhas' => $despesas->take(self::LIMITE_DETALHE)->values(),
            ],
        ];
    }

    /** Abastecimentos + manutenções do período (as comissões são uma por viagem concluída e entram à parte). */
    private function contarDespesas(array $entre): int
    {
        return Abastecimento::query()->whereBetween('data_abastecimento', $entre)->count()
            + Manutencao::query()->whereBetween('data_manutencao', $entre)->count();
    }

    public function linhas(PeriodoRelatorio $periodo): Collection
    {
        $viagens = Viagem::query()->concluidas()->whereBetween('data_viagem', [$periodo->de(), $periodo->ate()])
            ->selectRaw('filial_id, count(*) as qtd, sum(valor_frete) as frete, sum(comissao_valor) as comissao')
            ->groupBy('filial_id')->get()->keyBy('filial_id');

        $abastecimentos = Abastecimento::query()->whereBetween('data_abastecimento', [$periodo->de(), $periodo->ate()])
            ->selectRaw('filial_id, sum(valor_total) as valor')->groupBy('filial_id')->get()->keyBy('filial_id');

        $manutencoes = Manutencao::query()->whereBetween('data_manutencao', [$periodo->de(), $periodo->ate()])
            ->selectRaw('filial_id, sum(valor_total) as total')->groupBy('filial_id')->get()->keyBy('filial_id');

        $ids = $viagens->keys()->merge($abastecimentos->keys())->merge($manutencoes->keys())->unique();
        $nomes = Filial::query()->whereIn('id', $ids)->pluck('nome', 'id');

        return $ids->map(function ($id) use ($viagens, $abastecimentos, $manutencoes, $nomes) {
            $receitas = Dinheiro::total([(string) ($viagens->get($id)->frete ?? 0)]);
            $combustivel = Dinheiro::total([(string) ($abastecimentos->get($id)->valor ?? 0)]);
            $manutencao = Dinheiro::total([(string) ($manutencoes->get($id)->total ?? 0)]);
            $comissoes = Dinheiro::total([(string) ($viagens->get($id)->comissao ?? 0)]);
            $despesas = Dinheiro::total([$combustivel, $manutencao, $comissoes]);

            return [
                'filial' => $nomes[$id] ?? '—',
                'viagens' => (int) ($viagens->get($id)->qtd ?? 0),
                'receitas' => $receitas,
                'abastecimentos' => $combustivel,
                'manutencoes' => $manutencao,
                'comissoes' => $comissoes,
                'despesas' => $despesas,
                'resultado' => Dinheiro::subtrair($receitas, $despesas),
            ];
        })->sortBy('filial')->values();
    }
}
