@php
    use App\Support\Numero;

    $descricao = collect([$veiculo->tipo->rotulo(), collect([$veiculo->marca, $veiculo->modelo])->filter()->implode(' '), 'Filial '.$veiculo->filial->codigo])->filter()->implode(' · ');
@endphp

<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Veículos" :title="$veiculo->placa_formatada" :subtitle="$descricao">
        <x-slot:actions>
            <a href="{{ route('veiculos.index') }}" wire:navigate class="btn btn-secundario"><x-ui.icon name="chevron-left" class="size-4" /> Veículos</a>
            @can('create', \App\Models\Manutencao::class)
                <a href="{{ route('manutencoes.create', ['veiculo' => $veiculo->id]) }}" wire:navigate class="btn btn-primario"><x-ui.icon name="wrench" class="size-4" /> Registrar manutenção</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.manutencoes._alertas', ['alertas' => $alertas])

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Hodômetro atual</p>
            <p class="mt-2 text-xl font-semibold text-tinta sm:text-2xl">{{ $hodometro !== null ? Numero::formatar($hodometro, 1).' km' : '—' }}</p>
            <p class="text-xs text-gray-500">Maior leitura conhecida</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Motorista padrão</p>
            <p class="mt-2 text-lg font-semibold text-tinta">{{ $veiculo->motoristaPadrao?->nome ?? '—' }}</p>
            <p class="text-xs text-gray-500">{{ $veiculo->ativo ? 'Veículo ativo' : 'Veículo inativo' }}</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Combustível (total)</p>
            <p class="mt-2 text-xl font-semibold text-tinta sm:text-2xl">R$ {{ Numero::formatar($totalCombustivel) }}</p>
        </div>
        @can('manutencoes.ver_valores')
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Manutenção (total)</p>
                <p class="mt-2 text-xl font-semibold text-roxo-700 sm:text-2xl">R$ {{ Numero::formatar($totalManutencao) }}</p>
            </div>
        @endcan
    </div>

    <x-ui.card title="Linha do tempo" :description="'Abastecimentos e manutenções, do mais recente ao mais antigo (até '.$limite.' itens).'">
        @if ($linhaDoTempo->isEmpty())
            <p class="py-6 text-center text-sm text-gray-500">Este veículo ainda não tem abastecimentos nem manutenções.</p>
        @else
            <ol class="divide-y divide-borda">
                @foreach ($linhaDoTempo as $evento)
                    @php($item = $evento['item'])
                    <li wire:key="evento-{{ $evento['tipo'] }}-{{ $item->id }}" class="flex items-start gap-3 py-3">
                        <span @class(['mt-0.5 grid size-8 shrink-0 place-items-center rounded-full', 'bg-roxo-50 text-roxo-700' => $evento['tipo'] === 'manutencao', 'bg-gray-100 text-gray-600' => $evento['tipo'] === 'abastecimento'])>
                            <x-ui.icon :name="$evento['tipo'] === 'manutencao' ? 'wrench' : 'fuel'" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            @if ($evento['tipo'] === 'manutencao')
                                <p class="text-sm font-medium text-tinta">
                                    {{ $item->tipo->rotulo() }} <span class="font-normal text-gray-500">· {{ $item->data_manutencao->format('d/m/Y') }}</span>
                                </p>
                                <p class="text-sm text-gray-600">{{ $item->descricao }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $item->oficina ?: 'Oficina não informada' }}@if ($item->odometro_km !== null) · {{ Numero::formatar($item->odometro_km, 1) }} km @endif
                                    @if ($item->proxima_km !== null || $item->proxima_data) · próxima: {{ collect([$item->proxima_km !== null ? Numero::formatar($item->proxima_km, 1).' km' : null, $item->proxima_data?->format('d/m/Y')])->filter()->implode(' ou ') }} @endif
                                </p>
                            @else
                                <p class="text-sm font-medium text-tinta">
                                    Abastecimento <span class="font-normal text-gray-500">· {{ $item->data_abastecimento->format('d/m/Y') }}</span>
                                </p>
                                <p class="text-sm text-gray-600">{{ Numero::formatar($item->litros, 3) }} L · {{ Numero::formatar($item->odometro_km, 1) }} km{{ $item->media_km_l !== null ? ' · '.Numero::formatar($item->media_km_l, 2).' km/l' : '' }}</p>
                                <p class="text-xs text-gray-500">{{ $item->motorista->nome }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-sm font-semibold text-tinta">
                            @if ($evento['tipo'] === 'abastecimento')
                                R$ {{ Numero::formatar($item->valor_total) }}
                            @else
                                @can('manutencoes.ver_valores')R$ {{ Numero::formatar($item->valor_total) }}@endcan
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-ui.card>
</div>
