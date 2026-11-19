<?php

namespace App\Services;

use App\Enums\FechamentoStatus;
use App\Enums\ViagemStatus;
use App\Models\Abastecimento;
use App\Models\FechamentoSemanal;
use App\Models\Manutencao;
use App\Models\Motorista;
use App\Models\Scopes\FilialScope;
use App\Models\Viagem;
use App\Support\Dinheiro;
use App\Support\SemanaIso;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Única implementação dos cálculos da semana de um motorista (D-15, RN-03 a RN-09): usada pela folha de
 * fechamento, pela exportação e pelo `FecharSemana`. Os lançamentos são buscados sem o escopo de filial porque
 * o acerto é do motorista; quem chama já verificou o acesso ao motorista (route binding / Policy).
 */
class ResumoSemanal
{
    public function paraMotorista(Motorista $motorista, SemanaIso $semana): ResumoDaSemana
    {
        $viagens = Viagem::withoutGlobalScope(FilialScope::class)
            ->with(['origem:id,nome', 'destino:id,nome', 'veiculo:id,placa'])
            ->where('motorista_id', $motorista->id)->daSemana($semana)
            ->orderBy('data_viagem')->orderBy('id')->get();

        $abastecimentos = Abastecimento::withoutGlobalScope(FilialScope::class)
            ->with(['posto:id,nome', 'veiculo:id,placa,capacidade_tanque_l'])
            ->where('motorista_id', $motorista->id)->daSemana($semana)
            ->orderBy('data_abastecimento')->orderBy('odometro_km')->orderBy('id')->get();

        // RN-20: manutenção é informativa. Entram as ligadas ao motorista e as dos veículos que ele usou na semana.
        $veiculosUsados = $viagens->pluck('veiculo_id')->merge($abastecimentos->pluck('veiculo_id'))->unique()->values();
        $manutencoes = Manutencao::withoutGlobalScope(FilialScope::class)
            ->with('veiculo:id,placa')
            ->whereBetween('data_manutencao', [$semana->inicio->toDateString(), $semana->fim->toDateString()])
            ->where(fn ($q) => $q->where('motorista_id', $motorista->id)->orWhereIn('veiculo_id', $veiculosUsados))
            ->orderBy('data_manutencao')->orderBy('id')->get();

        $concluidas = $viagens->filter(fn (Viagem $v) => $v->status === ViagemStatus::Concluida);

        $faturamento = Dinheiro::total($concluidas->pluck('valor_frete'));
        $comissao = Dinheiro::total($concluidas->pluck('comissao_valor'));
        $combustivel = Dinheiro::total($abastecimentos->pluck('valor_total'));

        // RN-09: só entram nas médias abastecimentos de tanque cheio com média calculada.
        $comMedia = $abastecimentos->filter(fn (Abastecimento $a) => $a->tanque_cheio && $a->media_km_l !== null && $a->km_rodados !== null);

        return new ResumoDaSemana(
            motorista: $motorista,
            semana: $semana,
            viagens: $viagens,
            abastecimentos: $abastecimentos,
            qtdViagens: $concluidas->count(),
            viagensPendentes: $viagens->filter(fn (Viagem $v) => in_array($v->status, [ViagemStatus::Planejada, ViagemStatus::EmTransito], true))->count(),
            faturamento: $faturamento,
            comissao: $comissao,
            valorAPagar: $comissao,
            abastecimento: $combustivel,
            liquido: Dinheiro::subtrair(Dinheiro::subtrair($faturamento, $comissao), $combustivel),
            litros: Dinheiro::total($abastecimentos->pluck('litros'), 3),
            km: Dinheiro::total($abastecimentos->whereNotNull('km_rodados')->pluck('km_rodados'), 1),
            mediaSimples: $this->mediaSimples($comMedia),
            mediaPonderada: $this->mediaPonderada($comMedia),
            manutencoes: $manutencoes,
            totalManutencao: Dinheiro::total($manutencoes->pluck('valor_total')),
            fechamento: FechamentoSemanal::withoutGlobalScope(FilialScope::class)
                ->with(['fechadoPor:id,name', 'pagoPor:id,name'])
                ->where('motorista_id', $motorista->id)->daSemana($semana)->first(),
        );
    }

    /**
     * Motoristas do escopo de filial do usuário com algum lançamento (viagem ou abastecimento) na semana, por nome.
     * É a mesma regra de "com movimento" da visão da semana (`Fechamentos\Index`).
     *
     * @return Collection<int, Motorista>
     */
    public function motoristasComMovimento(SemanaIso $semana): Collection
    {
        $intervalo = [$semana->inicio->toDateString(), $semana->fim->toDateString()];

        $ids = Viagem::withoutGlobalScope(FilialScope::class)->whereBetween('data_viagem', $intervalo)->distinct()->pluck('motorista_id')
            ->merge(Abastecimento::withoutGlobalScope(FilialScope::class)->whereBetween('data_abastecimento', $intervalo)->distinct()->pluck('motorista_id'))
            ->unique()->values();

        return Motorista::query()->with('filial:id,codigo')->whereIn('id', $ids)->orderBy('nome')->get();
    }

    /**
     * Visão do motorista (RN-21): reaproveita o mesmo cálculo da folha e entrega só o que ele pode ver.
     * Semana fechada ou paga mostra os valores gravados no fechamento; em aberto, o parcial.
     */
    public function paraPortal(Motorista $motorista, SemanaIso $semana): ResumoDoPortal
    {
        $resumo = $this->paraMotorista($motorista, $semana);
        $f = $resumo->fechamento;

        $viagens = $this->viagensDoPortal($resumo->viagens);

        $abastecimentos = $resumo->abastecimentos->map(fn (Abastecimento $a) => $this->linhaDeAbastecimentoDoPortal($a))->values()->all();

        $pendente = FechamentoSemanal::withoutGlobalScope(FilialScope::class)
            ->where('motorista_id', $motorista->id)->where('status', FechamentoStatus::Fechado->value)
            ->pluck('valor_a_pagar');

        return new ResumoDoPortal(
            semana: $semana,
            situacao: $resumo->situacao(),
            fechadoEm: $f?->fechado_em,
            pagoEm: $f?->pago_em,
            qtdViagens: $f?->qtd_viagens ?? $resumo->qtdViagens,
            frete: (string) ($f?->total_faturamento ?? $resumo->faturamento),
            comissao: (string) ($f?->valor_a_pagar ?? $resumo->valorAPagar),
            abastecimento: (string) ($f?->total_abastecimento ?? $resumo->abastecimento),
            litros: (string) ($f?->total_litros ?? $resumo->litros),
            km: (string) ($f?->total_km ?? $resumo->km),
            mediaSimples: $f ? ($f->media_km_l_simples !== null ? (string) $f->media_km_l_simples : null) : $resumo->mediaSimples,
            pendenteDePagamento: Dinheiro::total($pendente),
            viagens: $viagens,
            abastecimentos: $abastecimentos,
        );
    }

    /**
     * Visão do motorista no mês (RN-21): viagens e abastecimentos cuja data cai no mês de `$mes` (qualquer dia
     * do mês serve). A comissão é repartida pela situação da semana de cada viagem concluída.
     */
    public function paraPortalNoMes(Motorista $motorista, CarbonImmutable $mes): ResumoDoPortalMes
    {
        $inicio = $mes->startOfMonth();
        $fim = $mes->endOfMonth();
        $intervalo = [$inicio->toDateString(), $fim->toDateString()];

        $viagens = Viagem::withoutGlobalScope(FilialScope::class)
            ->with(['origem:id,nome', 'destino:id,nome'])
            ->where('motorista_id', $motorista->id)->whereBetween('data_viagem', $intervalo)
            ->orderBy('data_viagem')->orderBy('id')->get();

        $abastecimentos = Abastecimento::withoutGlobalScope(FilialScope::class)
            ->with('posto:id,nome')
            ->where('motorista_id', $motorista->id)->whereBetween('data_abastecimento', $intervalo)
            ->orderBy('data_abastecimento')->orderBy('odometro_km')->orderBy('id')->get();

        // Fechamentos das semanas que tocam o mês, para saber se cada viagem está em semana paga, fechada ou aberta.
        $fechamentos = FechamentoSemanal::withoutGlobalScope(FilialScope::class)
            ->where('motorista_id', $motorista->id)
            ->where('data_inicio', '<=', $intervalo[1])->where('data_fim', '>=', $intervalo[0])
            ->get()->keyBy(fn (FechamentoSemanal $f) => $f->ano_iso.'-'.$f->semana_iso);

        $concluidas = $viagens->filter(fn (Viagem $v) => $v->status === ViagemStatus::Concluida);
        $situacaoDaSemana = fn (SemanaIso $s): string => $fechamentos->get($s->ano.'-'.$s->semana)?->status->value ?? 'em_andamento';
        $comissaoDe = fn (string $situacao) => Dinheiro::total(
            $concluidas->filter(fn (Viagem $v) => $situacaoDaSemana(SemanaIso::deData($v->data_viagem)) === $situacao)->pluck('comissao_valor')
        );

        $semanas = $viagens->groupBy(fn (Viagem $v) => SemanaIso::deData($v->data_viagem)->chave())
            ->map(function (Collection $doGrupo, string $chave) use ($situacaoDaSemana) {
                $semana = SemanaIso::daChave($chave);
                $somadas = $doGrupo->filter(fn (Viagem $v) => $v->status === ViagemStatus::Concluida);

                return [
                    'chave' => $chave,
                    'rotulo' => 'Semana '.$semana->semana.' · '.$semana->inicio->format('d/m').' a '.$semana->fim->format('d/m'),
                    'qtd' => $somadas->count(),
                    'frete' => Dinheiro::total($somadas->pluck('valor_frete')),
                    'comissao' => Dinheiro::total($somadas->pluck('comissao_valor')),
                    'situacao' => $situacaoDaSemana($semana),
                ];
            })->sortKeys()->values()->all();

        $comMedia = $abastecimentos->filter(fn (Abastecimento $a) => $a->tanque_cheio && $a->media_km_l !== null && $a->km_rodados !== null);

        return new ResumoDoPortalMes(
            mes: $inicio,
            qtdViagens: $concluidas->count(),
            frete: Dinheiro::total($concluidas->pluck('valor_frete')),
            comissao: Dinheiro::total($concluidas->pluck('comissao_valor')),
            comissaoRecebida: $comissaoDe('pago'),
            comissaoAReceber: $comissaoDe('fechado'),
            comissaoEmAndamento: $comissaoDe('em_andamento'),
            abastecimento: Dinheiro::total($abastecimentos->pluck('valor_total')),
            litros: Dinheiro::total($abastecimentos->pluck('litros'), 3),
            km: Dinheiro::total($abastecimentos->whereNotNull('km_rodados')->pluck('km_rodados'), 1),
            mediaSimples: $this->mediaSimples($comMedia),
            viagens: $this->viagensDoPortal($viagens),
            abastecimentos: $abastecimentos->map(fn (Abastecimento $a) => $this->linhaDeAbastecimentoDoPortal($a))->values()->all(),
            semanas: $semanas,
        );
    }

    /**
     * Viagens como o motorista as vê: canceladas ficam de fora; as demais mostram status e valores próprios.
     *
     * @param  Collection<int, Viagem>  $viagens
     * @return array<int, array{data: CarbonInterface, origem: string, destino: string, status: string, badge: string, concluida: bool, frete: string, comissao: string}>
     */
    private function viagensDoPortal(Collection $viagens): array
    {
        return $viagens->reject(fn (Viagem $v) => $v->status === ViagemStatus::Cancelada)
            ->map(fn (Viagem $v) => [
                'data' => $v->data_viagem,
                'origem' => $v->origem->nome,
                'destino' => $v->destino->nome,
                'status' => $v->status->rotulo(),
                'badge' => $v->status->tipoDeBadge(),
                'concluida' => $v->status->contaNosTotais(),
                'frete' => (string) $v->valor_frete,
                'comissao' => (string) $v->comissao_valor,
            ])->values()->all();
    }

    /** @return array{data: CarbonInterface, posto: ?string, litros: string, preco: string, valor: string, km_inicial: ?string, odometro: string, km: ?string, media: ?string, tanque_cheio: bool} */
    private function linhaDeAbastecimentoDoPortal(Abastecimento $a): array
    {
        return [
            'data' => $a->data_abastecimento,
            'posto' => $a->posto?->nome,
            'litros' => (string) $a->litros,
            'preco' => (string) $a->preco_litro,
            'valor' => (string) $a->valor_total,
            'km_inicial' => $a->km_anterior !== null ? (string) $a->km_anterior : null,
            'odometro' => (string) $a->odometro_km,
            'km' => $a->km_rodados !== null ? (string) $a->km_rodados : null,
            'media' => $a->media_km_l !== null ? (string) $a->media_km_l : null,
            'tanque_cheio' => (bool) $a->tanque_cheio,
        ];
    }

    /**
     * Média aritmética das médias de cada abastecimento — igual à planilha. Cada média sai de km ÷ litros sem
     * arredondar antes (D-08): 2,2353 e 2,4834 dão 2,3593 e não 2,3594.
     *
     * @param  Collection<int, Abastecimento>  $abastecimentos
     */
    private function mediaSimples($abastecimentos): ?string
    {
        if ($abastecimentos->isEmpty()) {
            return null;
        }

        $medias = $abastecimentos->map(fn (Abastecimento $a) => Dinheiro::dividir((string) $a->km_rodados, (string) $a->litros, 10));

        return Dinheiro::dividir(Dinheiro::total($medias, 10), (string) $abastecimentos->count(), 4);
    }

    /**
     * Média ponderada: Σ km ÷ Σ litros dos mesmos abastecimentos (indicador secundário, RN-09).
     *
     * @param  Collection<int, Abastecimento>  $abastecimentos
     */
    private function mediaPonderada($abastecimentos): ?string
    {
        if ($abastecimentos->isEmpty()) {
            return null;
        }

        return Dinheiro::dividir(
            Dinheiro::total($abastecimentos->pluck('km_rodados'), 1),
            Dinheiro::total($abastecimentos->pluck('litros'), 3),
            4,
        );
    }
}
