@php use App\Support\Numero; @endphp

<div>
    <x-ui.page-header
        eyebrow="MRS / Gestão de frota"
        title="Dashboard"
        subtitle="Acompanhe viagens, abastecimentos e fechamentos semanais por filial."
    />

    @unless ($completo)
        <x-ui.alert>Bem-vindo ao sistema. Use o menu ao lado para acessar as telas liberadas para o seu perfil.</x-ui.alert>
    @else
        {{-- Contexto: período --}}
        <div class="mb-6 grid gap-4 rounded-2xl border border-borda bg-white p-5 shadow-cartao sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.field label="Período" name="periodo">
                <select wire:model.live="periodo" class="campo">
                    @foreach ($atalhos as $chave => $rotulo)<option value="{{ $chave }}">{{ $rotulo }}</option>@endforeach
                </select>
            </x-ui.field>
            @if ($atalhoAtual === 'personalizado')
                <x-ui.field label="De" name="de"><input type="date" wire:model.live="de" class="campo"></x-ui.field>
                <x-ui.field label="Até" name="ate"><input type="date" wire:model.live="ate" class="campo"></x-ui.field>
            @endif
            <div class="flex items-end text-sm text-gray-500 sm:col-span-2 lg:col-span-1">
                <p>{{ $periodoEscolhido->rotulo() }}@if ($parcial) · <span class="text-amber-700">período em andamento</span>@endif</p>
            </div>
        </div>

        {{-- Alertas --}}
        @php
            $temAlerta = $preventivas->isNotEmpty() || ($semanasPendentes?->isNotEmpty() ?? false) || ($inconsistencias ?? 0) > 0;
        @endphp
        @if ($temAlerta)
            <div class="mb-6 space-y-2">
                @if ($semanasPendentes?->isNotEmpty())
                    <x-ui.alert tipo="aviso">
                        <strong>{{ $semanasPendentes->count() }}</strong> semana(s) de motorista com movimento ainda sem fechamento (últimas 8 semanas).
                        <a href="{{ route('fechamentos.index') }}" wire:navigate class="font-medium underline">Abrir fechamento semanal</a>
                    </x-ui.alert>
                @endif
                @foreach ($preventivas as $alerta)
                    <x-ui.alert :tipo="$alerta['situacao'] === 'vencida' ? 'erro' : 'aviso'" wire:key="prev-{{ $alerta['veiculo']->id }}">
                        <a href="{{ route('veiculos.show', $alerta['veiculo']) }}" wire:navigate class="font-semibold underline">{{ $alerta['veiculo']->placa_formatada }}</a>
                        — preventiva {{ $alerta['situacao'] === 'vencida' ? 'vencida' : 'se aproximando' }}: {{ implode(' ', $alerta['motivos']) }}
                    </x-ui.alert>
                @endforeach
                @if (($inconsistencias ?? 0) > 0)
                    <x-ui.alert tipo="aviso">
                        <strong>{{ $inconsistencias }}</strong> ponto(s) para conferir neste período.
                        <a href="{{ route('relatorios.show', ['relatorio' => 'inconsistencias', 'periodo' => $atalhoAtual, 'de' => $periodoEscolhido->de(), 'ate' => $periodoEscolhido->ate()]) }}" wire:navigate class="font-medium underline">Ver inconsistências</a>
                    </x-ui.alert>
                @endif
            </div>
        @endif

        {{-- KPIs --}}
        <div class="mb-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
            @can('viagens.ver_valores')
                <div class="rounded-2xl border border-borda bg-white p-5 shadow-cartao">
                    <p class="text-xs font-medium text-gray-500">Faturamento</p>
                    <p class="mt-3 text-xl font-semibold text-tinta sm:text-2xl">R$ {{ Numero::formatar($kpis['frete']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $kpis['viagens'] }} viagem(ns) concluída(s)</p>
                </div>
            @endcan
            @can('comissoes.ver')
                <div class="rounded-2xl border border-borda bg-white p-5 shadow-cartao">
                    <p class="text-xs font-medium text-gray-500">Comissões</p>
                    <p class="mt-3 text-xl font-semibold text-roxo-700 sm:text-2xl">R$ {{ Numero::formatar($kpis['comissao']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Devido aos motoristas</p>
                </div>
            @endcan
            <div class="rounded-2xl border border-borda bg-white p-5 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Combustível</p>
                <p class="mt-3 text-xl font-semibold text-tinta sm:text-2xl">R$ {{ Numero::formatar($kpis['abastecimento']) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ Numero::formatar($kpis['litros'], 1) }} litros</p>
            </div>
            @if (auth()->user()->can('viagens.ver_valores') && auth()->user()->can('comissoes.ver'))
                <div class="rounded-2xl border border-borda bg-white p-5 shadow-cartao" title="Faturamento − comissão − combustível. Manutenção não entra.">
                    <p class="text-xs font-medium text-gray-500">Líquido</p>
                    <p @class(['mt-3 text-xl font-semibold sm:text-2xl', 'text-emerald-700' => bccomp($kpis['liquido'], '0', 2) >= 0, 'text-red-700' => bccomp($kpis['liquido'], '0', 2) < 0])>R$ {{ Numero::formatar($kpis['liquido']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Faturamento − comissão − combustível</p>
                </div>
            @endif
            <div class="rounded-2xl border border-borda bg-white p-5 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Km rodados</p>
                <p class="mt-3 text-xl font-semibold text-tinta sm:text-2xl">{{ Numero::formatar($kpis['km'], 0) }} km</p>
            </div>
            <div class="rounded-2xl border border-borda bg-white p-5 shadow-cartao" title="Σ km ÷ Σ litros dos abastecimentos de tanque cheio.">
                <p class="text-xs font-medium text-gray-500">Média km/l</p>
                <p class="mt-3 text-xl font-semibold text-tinta sm:text-2xl">{{ $kpis['media'] !== null ? Numero::formatar($kpis['media'], 2) : '—' }}</p>
                <p class="mt-1 text-xs text-gray-500">Média ponderada do período</p>
            </div>
            @can('manutencoes.ver_valores')
                <div class="rounded-2xl border border-borda bg-white p-5 shadow-cartao" title="Informativo: não entra no líquido.">
                    <p class="text-xs font-medium text-gray-500">Manutenção</p>
                    <p class="mt-3 text-xl font-semibold text-tinta sm:text-2xl">R$ {{ Numero::formatar($kpis['manutencao']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Informativo</p>
                </div>
            @endcan
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @can('viagens.ver_valores')
                <x-ui.card title="Principais rotas" description="Maior faturamento no período.">
                    @if ($topRotas->isEmpty())
                        <p class="py-6 text-center text-sm text-gray-500">Sem viagens concluídas no período.</p>
                    @else
                        <ul class="divide-y divide-borda">
                            @foreach ($topRotas as $rota)
                                <li class="flex items-center justify-between gap-3 py-2.5 text-sm" wire:key="rota-{{ $loop->index }}">
                                    <span class="min-w-0"><span class="block truncate font-medium text-tinta">{{ $rota['rota'] }}</span><span class="text-xs text-gray-500">{{ $rota['viagens'] }} viagem(ns)</span></span>
                                    <span class="shrink-0 font-semibold text-tinta">R$ {{ Numero::formatar($rota['frete']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            @endcan

            @can('abastecimentos.ver')
                <x-ui.card title="Consumo por veículo" description="Veículos que mais abasteceram no período.">
                    @if ($consumo->isEmpty())
                        <p class="py-6 text-center text-sm text-gray-500">Sem abastecimentos no período.</p>
                    @else
                        <ul class="divide-y divide-borda">
                            @foreach ($consumo as $linha)
                                <li class="flex items-center justify-between gap-3 py-2.5 text-sm" wire:key="consumo-{{ $loop->index }}">
                                    <span class="min-w-0"><span class="block font-medium text-tinta">{{ $linha['veiculo'] }}</span><span class="text-xs text-gray-500">{{ Numero::formatar($linha['litros'], 1) }} L · R$ {{ Numero::formatar($linha['valor']) }}</span></span>
                                    <span class="shrink-0 font-semibold text-tinta">{{ $linha['media'] !== null ? Numero::formatar($linha['media'], 2).' km/l' : '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @can('relatorios.ver')
                        <a href="{{ route('relatorios.show', ['relatorio' => 'consumo-por-veiculo', 'periodo' => $atalhoAtual, 'de' => $periodoEscolhido->de(), 'ate' => $periodoEscolhido->ate()]) }}" wire:navigate class="btn btn-link mt-3">Ver relatório completo</a>
                    @endcan
                </x-ui.card>
            @endcan
        </div>
    @endunless
</div>
