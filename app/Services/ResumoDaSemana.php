<?php

namespace App\Services;

use App\Models\Abastecimento;
use App\Models\FechamentoSemanal;
use App\Models\Manutencao;
use App\Models\Motorista;
use App\Models\Viagem;
use App\Support\SemanaIso;
use Illuminate\Support\Collection;

/**
 * Resultado do `ResumoSemanal`: as linhas da semana e os totais calculados ao vivo (valores são `string` decimal).
 * `fechamento` guarda o snapshot quando a semana já foi fechada; as telas mostram o snapshot nesse caso.
 */
final readonly class ResumoDaSemana
{
    /**
     * @param  Collection<int, Viagem>  $viagens  todas as da semana (qualquer status)
     * @param  Collection<int, Abastecimento>  $abastecimentos
     * @param  Collection<int, Manutencao>  $manutencoes  informativas (RN-20): não entram no líquido
     */
    public function __construct(
        public Motorista $motorista,
        public SemanaIso $semana,
        public Collection $viagens,
        public Collection $abastecimentos,
        public int $qtdViagens,
        public int $viagensPendentes,
        public string $faturamento,
        public string $comissao,
        public string $valorAPagar,
        public string $abastecimento,
        public string $liquido,
        public string $litros,
        public string $km,
        public ?string $mediaSimples,
        public ?string $mediaPonderada,
        public Collection $manutencoes,
        public string $totalManutencao,
        public ?FechamentoSemanal $fechamento,
    ) {}

    /**
     * Totais para exibir: semana fechada mostra o snapshot gravado; em aberto, o cálculo ao vivo.
     *
     * @return array{viagens: int, faturamento: string, comissao: string, valor_a_pagar: string, abastecimento: string, liquido: string, litros: string, km: string, media_simples: ?string, media_ponderada: ?string}
     */
    public function totais(): array
    {
        $f = $this->fechamento;

        return [
            'viagens' => $f?->qtd_viagens ?? $this->qtdViagens,
            'faturamento' => (string) ($f?->total_faturamento ?? $this->faturamento),
            'comissao' => (string) ($f?->total_comissao ?? $this->comissao),
            'valor_a_pagar' => (string) ($f?->valor_a_pagar ?? $this->valorAPagar),
            'abastecimento' => (string) ($f?->total_abastecimento ?? $this->abastecimento),
            'liquido' => (string) ($f?->total_liquido ?? $this->liquido),
            'litros' => (string) ($f?->total_litros ?? $this->litros),
            'km' => (string) ($f?->total_km ?? $this->km),
            'media_simples' => $f ? $f->media_km_l_simples : $this->mediaSimples,
            'media_ponderada' => $f ? $f->media_km_l_ponderada : $this->mediaPonderada,
        ];
    }

    public function estaFechada(): bool
    {
        return $this->fechamento !== null;
    }

    public function semLancamentos(): bool
    {
        return $this->viagens->isEmpty() && $this->abastecimentos->isEmpty();
    }

    /** "em_andamento", "fechado" ou "pago". */
    public function situacao(): string
    {
        return $this->fechamento?->status->value ?? 'em_andamento';
    }
}
