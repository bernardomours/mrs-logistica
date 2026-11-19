@php
    use App\Enums\FechamentoStatus;
    use App\Models\FechamentoSemanal;
    use App\Support\Numero;

    $f = $resumo->fechamento;
    $abertaParaFechar = $f === null;
@endphp

<div>
    <x-ui.page-header eyebrow="MRS / Operação" title="Folha da semana" :subtitle="$motorista->nome.' · '.$semanaIso->rotulo().' · Filial '.$motorista->filial->codigo">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <a href="{{ route('fechamentos.index', ['semana' => $semanaIso->chave()]) }}" wire:navigate class="btn btn-secundario">
                    <x-ui.icon name="chevron-left" class="size-4" /> Todas as folhas
                </a>
                @can('exportar', [FechamentoSemanal::class, $motorista])
                    <a href="{{ route('fechamentos.excel', [$motorista, $semanaIso->chave()]) }}" class="btn btn-secundario">
                        <x-ui.icon name="upload" class="size-4 rotate-180" /> Exportar Excel
                    </a>
                @endcan
                @can('exportar', [FechamentoSemanal::class, $motorista])
                    @unless ($resumo->semLancamentos())
                        <a href="{{ route('fechamentos.pdf', [$motorista, $semanaIso->chave()]) }}" class="btn btn-secundario">
                            <x-ui.icon name="upload" class="size-4 rotate-180" /> Baixar PDF
                        </a>
                    @endunless
                @endcan
                <button type="button" onclick="window.print()" class="btn btn-secundario">Imprimir</button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Contexto: motorista e semana --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 rounded-2xl border border-borda bg-white p-4 shadow-cartao print:hidden">
        <div class="w-full sm:w-72">
            <label class="rotulo" for="motorista-folha">Motorista</label>
            <select id="motorista-folha" class="campo" wire:change="trocarMotorista($event.target.value)">
                @foreach ($motoristas as $m)
                    <option value="{{ $m->id }}" @selected($m->id === $motorista->id)>{{ $m->nome }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('fechamentos.folha', [$motorista, $semanaIso->anterior()->chave()]) }}" wire:navigate class="btn btn-secundario" aria-label="Semana anterior">
                <x-ui.icon name="chevron-left" class="size-4" />
            </a>
            <div class="min-w-44 text-center">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Semana {{ $semanaIso->semana }}/{{ $semanaIso->ano }}</p>
                <p class="text-sm font-semibold text-tinta">{{ $semanaIso->inicio->format('d/m') }} a {{ $semanaIso->fim->format('d/m/Y') }}</p>
            </div>
            <a href="{{ route('fechamentos.folha', [$motorista, $semanaIso->proxima()->chave()]) }}" wire:navigate class="btn btn-secundario" aria-label="Próxima semana">
                <x-ui.icon name="chevron-right" class="size-4" />
            </a>
        </div>

        <div>
            @if ($f)
                <x-ui.badge :tipo="$f->status->tipoDeBadge()"><x-ui.icon name="lock" class="size-3" /> {{ $f->status->rotulo() }}</x-ui.badge>
            @else
                <x-ui.badge tipo="aviso">Em andamento (parcial)</x-ui.badge>
            @endif
        </div>
    </div>

    {{-- Avisos de estado --}}
    <div class="mb-6 space-y-3">
        @error('semana')<x-ui.alert tipo="erro">{{ $message }}</x-ui.alert>@enderror
        @error('pagoEm')<x-ui.alert tipo="erro">{{ $message }}</x-ui.alert>@enderror

        @if ($f)
            <x-ui.alert icone="lock">
                <strong>Semana fechada</strong> em {{ $f->fechado_em->format('d/m/Y H:i') }} por {{ $f->fechadoPor?->name ?? '—' }}.
                @if ($f->estaPago())
                    <strong>Paga</strong> em {{ $f->pago_em->format('d/m/Y') }} (registrado por {{ $f->pagoPor?->name ?? '—' }}).
                @endif
                Viagens e abastecimentos deste motorista nesta semana estão travados e os totais abaixo são os gravados no fechamento.
            </x-ui.alert>
        @elseif ($futura)
            <x-ui.alert tipo="aviso">Semana futura: ainda não pode ser fechada.</x-ui.alert>
        @elseif ($resumo->viagensPendentes > 0)
            <x-ui.alert tipo="aviso">Há {{ $resumo->viagensPendentes }} viagem(ns) planejada(s) ou em trânsito nesta semana. Conclua ou cancele antes de fechar.</x-ui.alert>
        @endif
    </div>

    {{-- Caixa-resumo --}}
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @can('viagens.ver_valores')
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Faturamento</p>
                <p class="mt-2 text-xl font-semibold text-tinta sm:text-2xl">R$ {{ Numero::formatar($totais['faturamento']) }}</p>
                <p class="text-xs text-gray-500">{{ $totais['viagens'] }} viagem(ns) concluída(s)</p>
            </div>
        @endcan
        @can('comissoes.ver')
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Comissão do motorista</p>
                <p class="mt-2 text-xl font-semibold text-roxo-700 sm:text-2xl">R$ {{ Numero::formatar($totais['comissao']) }}</p>
                <p class="text-xs text-gray-500">A pagar: R$ {{ Numero::formatar($totais['valor_a_pagar']) }}</p>
            </div>
        @endcan
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Abastecimento</p>
            <p class="mt-2 text-xl font-semibold text-tinta sm:text-2xl">R$ {{ Numero::formatar($totais['abastecimento']) }}</p>
            <p class="text-xs text-gray-500">{{ Numero::formatar($totais['litros'], 3) }} L · {{ Numero::formatar($totais['km'], 1) }} km</p>
        </div>
        @if (auth()->user()->can('viagens.ver_valores') && auth()->user()->can('comissoes.ver'))
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao" title="Faturamento − comissão − abastecimento. Manutenção não entra.">
                <p class="text-xs font-medium text-gray-500">Líquido da semana</p>
                <p @class(['mt-2 text-xl font-semibold sm:text-2xl', 'text-emerald-700' => bccomp($totais['liquido'], '0', 2) >= 0, 'text-red-700' => bccomp($totais['liquido'], '0', 2) < 0])>R$ {{ Numero::formatar($totais['liquido']) }}</p>
            </div>
        @endif
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao" title="Média simples das médias de cada abastecimento (igual à planilha).">
            <p class="text-xs font-medium text-gray-500">Média da semana</p>
            <p class="mt-2 text-xl font-semibold text-tinta sm:text-2xl">
                {{ $totais['media_simples'] !== null ? Numero::formatar($totais['media_simples'], 2).' km/l' : '—' }}
            </p>
            @if ($totais['media_ponderada'] !== null)
                <p class="text-xs text-gray-500">Ponderada: {{ Numero::formatar($totais['media_ponderada'], 2) }} km/l</p>
            @endif
        </div>
    </div>

    {{-- Viagens --}}
    <x-ui.card :flush="true" class="mb-6">
        <div class="flex items-center justify-between px-5 pt-5 sm:px-6">
            <h2 class="text-base font-semibold text-tinta">Viagens</h2>
            <span class="text-xs text-gray-500">{{ $resumo->viagens->count() }} lançada(s)</span>
        </div>

        @if ($resumo->viagens->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-gray-500 sm:px-6">Nenhuma viagem nesta semana.</p>
        @else
            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Rota</th>
                            <th scope="col">Veículo</th>
                            @can('viagens.ver_valores')<th scope="col" class="text-right">Faturamento</th>@endcan
                            @can('comissoes.ver')<th scope="col" class="text-right">Comissão</th>@endcan
                            <th scope="col">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumo->viagens as $viagem)
                            <tr wire:key="fv-{{ $viagem->id }}" @class(['opacity-50' => ! $viagem->status->contaNosTotais()])>
                                <td class="whitespace-nowrap">{{ $viagem->data_viagem->format('d/m/Y') }}</td>
                                <td class="text-tinta">{{ $viagem->origem->nome }} <span class="text-gray-400">→</span> {{ $viagem->destino->nome }}</td>
                                <td class="whitespace-nowrap text-gray-600">{{ $viagem->veiculo->placa_formatada }}</td>
                                @can('viagens.ver_valores')<td class="text-right whitespace-nowrap">R$ {{ Numero::formatar($viagem->valor_frete) }}</td>@endcan
                                @can('comissoes.ver')<td class="text-right whitespace-nowrap text-gray-600">R$ {{ Numero::formatar($viagem->comissao_valor) }}</td>@endcan
                                <td><x-ui.badge :tipo="$viagem->status->tipoDeBadge()">{{ $viagem->status->rotulo() }}</x-ui.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-roxo-50/60 font-semibold text-tinta">
                            <td colspan="3">TOTAL ({{ $totais['viagens'] }} concluída(s))</td>
                            @can('viagens.ver_valores')<td class="text-right whitespace-nowrap">R$ {{ Numero::formatar($totais['faturamento']) }}</td>@endcan
                            @can('comissoes.ver')<td class="text-right whitespace-nowrap">R$ {{ Numero::formatar($totais['comissao']) }}</td>@endcan
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <ul class="mt-4 divide-y divide-borda md:hidden">
                @foreach ($resumo->viagens as $viagem)
                    <li wire:key="fv-card-{{ $viagem->id }}" @class(['space-y-1 p-4', 'opacity-50' => ! $viagem->status->contaNosTotais()])>
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium text-tinta">{{ $viagem->origem->nome }} <span class="text-gray-400">→</span> {{ $viagem->destino->nome }}</p>
                            <x-ui.badge :tipo="$viagem->status->tipoDeBadge()">{{ $viagem->status->rotulo() }}</x-ui.badge>
                        </div>
                        <p class="text-sm text-gray-500">{{ $viagem->data_viagem->format('d/m/Y') }} · {{ $viagem->veiculo->placa_formatada }}</p>
                        <div class="flex flex-wrap gap-2 pt-1">
                            @can('viagens.ver_valores')<span class="text-base font-semibold text-tinta">R$ {{ Numero::formatar($viagem->valor_frete) }}</span>@endcan
                            @can('comissoes.ver')<x-ui.badge>Comissão R$ {{ Numero::formatar($viagem->comissao_valor) }}</x-ui.badge>@endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- Abastecimentos --}}
    <x-ui.card :flush="true" class="mb-6">
        <div class="flex items-center justify-between px-5 pt-5 sm:px-6">
            <h2 class="text-base font-semibold text-tinta">Abastecimentos</h2>
            <span class="text-xs text-gray-500">{{ $resumo->abastecimentos->count() }} lançado(s)</span>
        </div>

        @if ($resumo->abastecimentos->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-gray-500 sm:px-6">Nenhum abastecimento nesta semana.</p>
        @else
            <div class="mt-4 hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Posto / veículo</th>
                            <th scope="col" class="text-right">Litros</th>
                            <th scope="col" class="text-right">Preço/L</th>
                            <th scope="col" class="text-right">Valor</th>
                            <th scope="col" class="text-right">Km inicial</th>
                            <th scope="col" class="text-right">Km final</th>
                            <th scope="col" class="text-right">Km total</th>
                            <th scope="col" class="text-right">Média</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumo->abastecimentos as $abastecimento)
                            @php($alertas = $abastecimento->alertas())
                            <tr wire:key="fa-{{ $abastecimento->id }}">
                                <td class="whitespace-nowrap">{{ $abastecimento->data_abastecimento->format('d/m/Y') }}</td>
                                <td class="text-tinta">{{ $abastecimento->posto?->nome ?? '—' }}<span class="block text-xs text-gray-500">{{ $abastecimento->veiculo->placa_formatada }}</span></td>
                                <td class="text-right whitespace-nowrap">{{ Numero::formatar($abastecimento->litros, 3) }}</td>
                                <td class="text-right whitespace-nowrap text-gray-600">{{ Numero::formatar($abastecimento->preco_litro, 3) }}</td>
                                <td class="text-right whitespace-nowrap">R$ {{ Numero::formatar($abastecimento->valor_total) }}</td>
                                <td class="text-right whitespace-nowrap text-gray-600">{{ $abastecimento->km_anterior !== null ? Numero::formatar($abastecimento->km_anterior, 1) : '—' }}</td>
                                <td class="text-right whitespace-nowrap text-gray-600">{{ Numero::formatar($abastecimento->odometro_km, 1) }}</td>
                                <td class="text-right whitespace-nowrap text-gray-600">{{ $abastecimento->km_rodados !== null ? Numero::formatar($abastecimento->km_rodados, 1) : '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($abastecimento->media_km_l !== null)
                                        <span @class(['text-amber-700' => $alertas !== []]) title="{{ $alertas !== [] ? implode(' ', $alertas) : Numero::formatar($abastecimento->media_km_l, 4).' km/l' }}">{{ Numero::formatar($abastecimento->media_km_l, 2) }}</span>
                                    @else
                                        <span class="text-gray-400" title="Sem hodômetro de partida: média não calculada.">—</span>
                                    @endif
                                    @unless ($abastecimento->tanque_cheio)<span class="block text-xs text-gray-400">parcial</span>@endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-roxo-50/60 font-semibold text-tinta">
                            <td colspan="2">TOTAL</td>
                            <td class="text-right whitespace-nowrap">{{ Numero::formatar($totais['litros'], 3) }}</td>
                            <td></td>
                            <td class="text-right whitespace-nowrap">R$ {{ Numero::formatar($totais['abastecimento']) }}</td>
                            <td></td>
                            <td></td>
                            <td class="text-right whitespace-nowrap">{{ Numero::formatar($totais['km'], 1) }}</td>
                            <td class="text-right whitespace-nowrap" title="Média da semana (simples)">{{ $totais['media_simples'] !== null ? Numero::formatar($totais['media_simples'], 2) : '—' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <ul class="mt-4 divide-y divide-borda md:hidden">
                @foreach ($resumo->abastecimentos as $abastecimento)
                    <li wire:key="fa-card-{{ $abastecimento->id }}" class="space-y-1 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium text-tinta">{{ $abastecimento->posto?->nome ?? 'Posto não informado' }}</p>
                            <span class="font-semibold text-tinta">R$ {{ Numero::formatar($abastecimento->valor_total) }}</span>
                        </div>
                        <p class="text-sm text-gray-500">{{ $abastecimento->data_abastecimento->format('d/m/Y') }} · {{ $abastecimento->veiculo->placa_formatada }} · {{ Numero::formatar($abastecimento->litros, 3) }} L</p>
                        <p class="text-sm text-gray-500">
                            {{ $abastecimento->km_anterior !== null ? Numero::formatar($abastecimento->km_anterior, 1) : '—' }} → {{ Numero::formatar($abastecimento->odometro_km, 1) }} km
                            @if ($abastecimento->km_rodados !== null)({{ Numero::formatar($abastecimento->km_rodados, 1) }} km)@endif
                            · {{ $abastecimento->media_km_l !== null ? Numero::formatar($abastecimento->media_km_l, 2).' km/l' : 'sem média' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- Manutenção no período: informativa, não entra no líquido (RN-20) --}}
    @if ($resumo->manutencoes->isNotEmpty() && auth()->user()->can('manutencoes.ver'))
        <x-ui.card title="Manutenção no período" description="Informativo: serviços nos veículos usados nesta semana. Não entra no líquido." class="mb-6">
            <ul class="divide-y divide-borda">
                @foreach ($resumo->manutencoes as $manutencao)
                    <li wire:key="fm-{{ $manutencao->id }}" class="flex items-start justify-between gap-3 py-2.5 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-tinta">{{ $manutencao->veiculo->placa_formatada }} · {{ $manutencao->tipo->rotulo() }} <span class="font-normal text-gray-500">· {{ $manutencao->data_manutencao->format('d/m/Y') }}</span></p>
                            <p class="text-gray-600">{{ $manutencao->descricao }}</p>
                        </div>
                        @can('manutencoes.ver_valores')<span class="shrink-0 font-semibold text-tinta">R$ {{ Numero::formatar($manutencao->valor_total) }}</span>@endcan
                    </li>
                @endforeach
            </ul>
            @can('manutencoes.ver_valores')
                <p class="mt-3 flex justify-between border-t border-borda pt-3 text-sm font-semibold text-tinta"><span>Total de manutenção</span><span>R$ {{ Numero::formatar($resumo->totalManutencao) }}</span></p>
            @endcan
        </x-ui.card>
    @endif

    {{-- Ações do fechamento --}}
    <x-ui.card title="Fechamento" description="Fechar grava os totais desta semana e trava as viagens e abastecimentos do motorista nela." class="print:hidden">
        <div class="flex flex-wrap items-end gap-3">
            @if ($abertaParaFechar)
                @can('fechar', [FechamentoSemanal::class, $motorista])
                    <button type="button" wire:click="fechar" wire:loading.attr="disabled" wire:target="fechar" @disabled($futura)
                        wire:confirm="Fechar a semana {{ $semanaIso->semana }}/{{ $semanaIso->ano }} de {{ $motorista->nome }}? Os lançamentos dela ficam travados." class="btn btn-primario">
                        <x-ui.icon name="lock" class="size-4" /> Fechar semana
                    </button>
                @else
                    <p class="text-sm text-gray-500">Você não tem permissão para fechar semanas.</p>
                @endcan
            @endif

            @if ($f && ! $f->estaPago())
                @can('pagar', $f)
                    <div>
                        <label class="rotulo" for="pago-em">Data do pagamento</label>
                        <input id="pago-em" type="date" wire:model="pagoEm" max="{{ now()->toDateString() }}" class="campo">
                    </div>
                    <button type="button" wire:click="marcarPago" wire:loading.attr="disabled" wire:target="marcarPago"
                        wire:confirm="Registrar o pagamento de R$ {{ Numero::formatar($f->valor_a_pagar) }} a {{ $motorista->nome }}?" class="btn btn-primario">
                        <x-ui.icon name="check" class="size-4" /> Marcar como paga
                    </button>
                @endcan
            @endif

            @if ($f?->estaPago())
                @can('estornar', $f)
                    <button type="button" wire:click="pedirMotivo('estornar')" class="btn btn-secundario">Estornar pagamento</button>
                @endcan
            @endif

            @if ($f && ! $f->estaPago())
                @can('reabrir', $f)
                    <button type="button" wire:click="pedirMotivo('reabrir')" class="btn btn-perigo">Reabrir semana</button>
                @endcan
            @endif
        </div>

        @if ($acaoPendente)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <label class="rotulo" for="motivo">{{ $acaoPendente === 'reabrir' ? 'Motivo da reabertura' : 'Motivo do estorno' }}</label>
                <textarea id="motivo" wire:model="motivo" rows="2" maxlength="500" class="campo" placeholder="Explique o que será corrigido (fica registrado na auditoria)."></textarea>
                @error('motivo')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="confirmarMotivo" wire:loading.attr="disabled" wire:target="confirmarMotivo" class="btn btn-perigo">
                        {{ $acaoPendente === 'reabrir' ? 'Reabrir semana' : 'Estornar pagamento' }}
                    </button>
                    <button type="button" wire:click="cancelarMotivo" class="btn btn-secundario">Cancelar</button>
                </div>
            </div>
        @endif
    </x-ui.card>
</div>
