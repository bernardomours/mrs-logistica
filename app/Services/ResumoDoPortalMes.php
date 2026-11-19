<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * "Visão do motorista" do mês (RN-21): mesma regra do `ResumoDoPortal`, somando as viagens e os abastecimentos
 * cuja data cai no mês. A comissão é repartida pela situação da semana de cada viagem (recebida, a receber ou
 * em andamento). Também só contém o que o motorista pode ver.
 */
final readonly class ResumoDoPortalMes
{
    /**
     * @param  array<int, array{data: CarbonInterface, origem: string, destino: string, status: string, badge: string, concluida: bool, frete: string, comissao: string}>  $viagens
     * @param  array<int, array{data: CarbonInterface, posto: ?string, litros: string, preco: string, valor: string, km_inicial: ?string, odometro: string, km: ?string, media: ?string, tanque_cheio: bool}>  $abastecimentos
     * @param  array<int, array{chave: string, rotulo: string, qtd: int, frete: string, comissao: string, situacao: string}>  $semanas
     */
    public function __construct(
        public CarbonImmutable $mes,
        public int $qtdViagens,
        public string $frete,
        public string $comissao,
        /** Comissão das viagens de semanas já pagas. */
        public string $comissaoRecebida,
        /** Comissão das viagens de semanas fechadas, aguardando pagamento. */
        public string $comissaoAReceber,
        /** Comissão das viagens de semanas ainda abertas (valor parcial). */
        public string $comissaoEmAndamento,
        public string $abastecimento,
        public string $litros,
        public string $km,
        public ?string $mediaSimples,
        public array $viagens,
        public array $abastecimentos,
        public array $semanas,
    ) {}
}
