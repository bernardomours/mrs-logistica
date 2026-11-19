<?php

namespace App\Services;

use App\Support\SemanaIso;
use Carbon\CarbonInterface;

/**
 * "Visão do motorista" da semana (RN-21). Só tem o que o motorista pode ver: o frete e a comissão das PRÓPRIAS
 * viagens, os abastecimentos dele e a situação do acerto. Não existe aqui líquido da empresa, manutenção, tarifa
 * nem dado de outro motorista: o que não está neste objeto não chega à tela.
 */
final readonly class ResumoDoPortal
{
    /**
     * @param  array<int, array{data: CarbonInterface, origem: string, destino: string, status: string, badge: string, concluida: bool, frete: string, comissao: string}>  $viagens
     * @param  array<int, array{data: CarbonInterface, posto: ?string, litros: string, preco: string, valor: string, km_inicial: ?string, odometro: string, km: ?string, media: ?string, tanque_cheio: bool}>  $abastecimentos
     */
    public function __construct(
        public SemanaIso $semana,
        /** "em_andamento", "fechado" ou "pago". */
        public string $situacao,
        public ?CarbonInterface $fechadoEm,
        public ?CarbonInterface $pagoEm,
        public int $qtdViagens,
        public string $frete,
        public string $comissao,
        public string $abastecimento,
        public string $litros,
        public string $km,
        public ?string $mediaSimples,
        /** Comissão de semanas já fechadas que ainda não foram pagas (todas as semanas). */
        public string $pendenteDePagamento,
        public array $viagens,
        public array $abastecimentos,
    ) {}
}
