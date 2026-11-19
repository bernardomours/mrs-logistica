@php
    use App\Enums\ViagemStatus;
    use App\Support\Numero;

    $dinheiro = fn ($v) => 'R$ '.Numero::formatar($v);
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Fechamento semanal</title>
    <style>
        @page { margin: 28px 32px 40px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1c1f3a; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 11px; margin: 14px 0 5px 0; color: #1b1f3b; }
        .sub { color: #666a85; font-size: 9px; margin-top: 3px; }
        .topo { border-bottom: 2px solid #6d4bd8; padding-bottom: 8px; margin-bottom: 4px; }
        .selo { font-size: 9px; font-weight: bold; padding: 2px 8px; border: 1px solid #6d4bd8; color: #5a3cb8; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #1b1f3b; color: #fff; font-size: 8px; text-align: left; padding: 4px 5px; }
        td { padding: 3px 5px; border-bottom: 1px solid #e5e7f0; }
        .d { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .total td { font-weight: bold; background: #f5f3ff; border-top: 1px solid #6d4bd8; border-bottom: none; }
        .vazio { color: #8a8da6; font-style: italic; padding: 6px 5px; }
        .resumo { margin-top: 14px; width: 62%; }
        .resumo td { border: 1px solid #cdd0e6; padding: 6px 8px; }
        .resumo .rotulo { background: #f5f3ff; font-size: 8px; color: #5a3cb8; font-weight: bold; }
        .resumo .valor { font-size: 12px; font-weight: bold; text-align: right; }
        .aviso { margin-top: 8px; color: #92400e; font-size: 8.5px; }
        .rodape { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8px; color: #8a8da6; border-top: 1px solid #e5e7f0; padding-top: 4px; }
        .quebra { page-break-after: always; }
    </style>
</head>
<body>
    <div class="rodape">MRS Logística · emitido em {{ $emitidoEm->format('d/m/Y H:i') }} por {{ $emitidoPor }}</div>

    @foreach ($resumos as $resumo)
        @php
            $semana = $resumo->semana;
            $totais = $resumo->totais();
            $fechamento = $resumo->fechamento;
            $concluidas = $resumo->viagens->filter(fn ($v) => $v->status === ViagemStatus::Concluida);
            $situacao = $fechamento
                ? ($fechamento->estaPago() ? 'PAGA em '.$fechamento->pago_em->format('d/m/Y') : 'FECHADA')
                : 'EM ANDAMENTO';
        @endphp

        <section @class(['quebra' => ! $loop->last])>
            <div class="topo">
                <table>
                    <tr>
                        <td style="border: none; padding: 0 12px 0 0; width: 62px;"><img src="{{ $logo }}" alt="MRS Logística" style="height: 46px;"></td>
                        <td style="border: none; padding: 0;">
                            <h1>Fechamento semanal — {{ $resumo->motorista->nome }}</h1>
                            <p class="sub">
                                Semana {{ $semana->semana }}/{{ $semana->ano }} · {{ $semana->inicio->format('d/m/Y') }} a {{ $semana->fim->format('d/m/Y') }}
                                · Filial {{ $resumo->motorista->filial->codigo }} · Comissão {{ Numero::formatar($resumo->motorista->comissao_percentual) }}%
                            </p>
                        </td>
                        <td class="d" style="border: none; padding: 0; vertical-align: top;"><span class="selo">{{ $situacao }}</span></td>
                    </tr>
                </table>
            </div>

            <h2>Viagens</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 9%;">Data</th>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th style="width: 11%;">Veículo</th>
                        <th class="d" style="width: 14%;">Faturamento</th>
                        <th class="d" style="width: 14%;">Comissão</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($concluidas as $viagem)
                        <tr>
                            <td>{{ $viagem->data_viagem->format('d/m/Y') }}</td>
                            <td>{{ $viagem->origem->nome }}</td>
                            <td>{{ $viagem->destino->nome }}</td>
                            <td>{{ $viagem->veiculo->placa_formatada }}</td>
                            <td class="d">{{ $dinheiro($viagem->valor_frete) }}</td>
                            <td class="d">{{ $dinheiro($viagem->comissao_valor) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="vazio">Nenhuma viagem concluída nesta semana.</td></tr>
                    @endforelse
                    <tr class="total">
                        <td colspan="4">TOTAL ({{ $totais['viagens'] }} {{ $totais['viagens'] === 1 ? 'viagem' : 'viagens' }})</td>
                        <td class="d">{{ $dinheiro($totais['faturamento']) }}</td>
                        <td class="d">{{ $dinheiro($totais['comissao']) }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($resumo->viagensPendentes > 0)
                <p class="aviso">{{ $resumo->viagensPendentes }} {{ $resumo->viagensPendentes === 1 ? 'viagem planejada ou em trânsito não está' : 'viagens planejadas ou em trânsito não estão' }} incluída(s) nos totais.</p>
            @endif

            <h2>Abastecimentos</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 8%;">Data</th>
                        <th>Posto</th>
                        <th style="width: 9%;">Veículo</th>
                        <th class="d">Litros</th>
                        <th class="d">Preço/L</th>
                        <th class="d">Valor</th>
                        <th class="d">Km inicial</th>
                        <th class="d">Km final</th>
                        <th class="d">Km total</th>
                        <th class="d">Média km/l</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resumo->abastecimentos as $abastecimento)
                        <tr>
                            <td>{{ $abastecimento->data_abastecimento->format('d/m/Y') }}</td>
                            <td>{{ $abastecimento->posto?->nome ?? '—' }}</td>
                            <td>{{ $abastecimento->veiculo->placa_formatada }}</td>
                            <td class="d">{{ Numero::formatar($abastecimento->litros, 3) }}</td>
                            <td class="d">{{ Numero::formatar($abastecimento->preco_litro, 3) }}</td>
                            <td class="d">{{ $dinheiro($abastecimento->valor_total) }}</td>
                            <td class="d">{{ $abastecimento->km_anterior !== null ? Numero::formatar($abastecimento->km_anterior, 1) : '—' }}</td>
                            <td class="d">{{ Numero::formatar($abastecimento->odometro_km, 1) }}</td>
                            <td class="d">{{ $abastecimento->km_rodados !== null ? Numero::formatar($abastecimento->km_rodados, 1) : '—' }}</td>
                            <td class="d">{{ $abastecimento->media_km_l !== null ? Numero::formatar($abastecimento->media_km_l, 2) : '—' }}{{ $abastecimento->tanque_cheio ? '' : ' (parcial)' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="vazio">Nenhum abastecimento nesta semana.</td></tr>
                    @endforelse
                    <tr class="total">
                        <td colspan="3">TOTAL</td>
                        <td class="d">{{ Numero::formatar($totais['litros'], 3) }}</td>
                        <td></td>
                        <td class="d">{{ $dinheiro($totais['abastecimento']) }}</td>
                        <td></td>
                        <td></td>
                        <td class="d">{{ Numero::formatar($totais['km'], 1) }}</td>
                        <td class="d">{{ $totais['media_simples'] !== null ? Numero::formatar($totais['media_simples'], 2) : '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="resumo">
                <tr>
                    <td class="rotulo">FATURAMENTO</td>
                    <td class="rotulo">COMISSÃO MOT</td>
                    <td class="rotulo">ABASTECIMENTO</td>
                    <td class="rotulo">TOTAL (LÍQUIDO)</td>
                    <td class="rotulo">MÉDIA KM/L</td>
                </tr>
                <tr>
                    <td class="valor">{{ $dinheiro($totais['faturamento']) }}</td>
                    <td class="valor">{{ $dinheiro($totais['comissao']) }}</td>
                    <td class="valor">{{ $dinheiro($totais['abastecimento']) }}</td>
                    <td class="valor">{{ $dinheiro($totais['liquido']) }}</td>
                    <td class="valor">{{ $totais['media_simples'] !== null ? Numero::formatar($totais['media_simples'], 2) : '—' }}</td>
                </tr>
            </table>
            <p class="sub" style="margin-top: 6px;">Valor a pagar ao motorista: <strong>{{ $dinheiro($totais['valor_a_pagar']) }}</strong>
                @if (! $fechamento) · semana ainda em andamento: valores parciais, sujeitos a alteração. @endif
            </p>
        </section>
    @endforeach
</body>
</html>
