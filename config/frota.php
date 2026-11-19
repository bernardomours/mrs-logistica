<?php

/*
 * Parâmetros de negócio da frota (CLAUDE.md RN-06/RN-13). Valores iniciais; no futuro podem migrar para a
 * tabela `configuracoes` editável pela tela de Configurações.
 */
return [
    // Faixa de consumo plausível (km/l); fora dela o abastecimento recebe alerta.
    'media_km_l_min' => 1.0,
    'media_km_l_max' => 6.0,

    // Diferença aceita (em R$) entre litros × preço e o valor informado (RN-06).
    'tolerancia_valor_abastecimento' => '0.01',

    // Preço do litro que foge mais que isto (%) da média dos 5 abastecimentos anteriores do veículo vira alerta (RN-13).
    'preco_litro_desvio_pct' => 15,

    // Alerta de manutenção preventiva (RN-13): "próxima" quando faltam até estes dias / km.
    'preventiva_aviso_dias' => 15,
    'preventiva_aviso_km' => 1000,

    // Portal do motorista (RN-21): o acesso nasce com login = CPF e senha inicial = CPF. Ligue para exigir
    // que o motorista troque a senha no primeiro acesso (recomendado se o CPF for fácil de descobrir).
    'portal_forcar_troca_senha' => false,

    // Combustíveis aceitos.
    'combustiveis' => [
        'diesel' => 'Diesel',
        'diesel_s10' => 'Diesel S10',
        'diesel_s500' => 'Diesel S500',
        'gasolina' => 'Gasolina',
        'etanol' => 'Etanol',
        'gnv' => 'GNV',
        'arla' => 'Arla 32',
    ],
];
