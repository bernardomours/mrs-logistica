<div>
    <x-ui.page-header eyebrow="MRS / Operação" title="Abastecimentos" subtitle="Combustível por veículo, com km rodados e média de consumo entre um abastecimento e outro.">
        <x-slot:actions>
            @can('create', \App\Models\Abastecimento::class)
                <a href="{{ route('abastecimentos.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Novo abastecimento
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filtros placeholder="Placa, posto ou nota fiscal" descricao="Filtre por período, veículo, motorista ou posto." :tem-filtros="$this->temFiltros">
        <x-slot:filtro>
            <x-ui.field label="Posto" name="posto">
                <select wire:model.live="posto" class="campo">
                    <option value="">Todos</option>
                    @foreach ($postos as $p)<option value="{{ $p->id }}">{{ $p->nome }}</option>@endforeach
                </select>
            </x-ui.field>
        </x-slot:filtro>
    </x-ui.filtros>

    <div class="-mt-3 mb-6 grid gap-4 rounded-2xl border border-borda bg-white p-5 shadow-cartao sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="De" name="de"><input type="date" wire:model.live="de" class="campo"></x-ui.field>
        <x-ui.field label="Até" name="ate"><input type="date" wire:model.live="ate" class="campo"></x-ui.field>
        <x-ui.field label="Veículo" name="veiculo">
            <select wire:model.live="veiculo" class="campo">
                <option value="">Todos</option>
                @foreach ($veiculos as $v)<option value="{{ $v->id }}">{{ $v->placa }}</option>@endforeach
            </select>
        </x-ui.field>
        <x-ui.field label="Motorista" name="motorista">
            <select wire:model.live="motorista" class="campo">
                <option value="">Todos</option>
                @foreach ($motoristas as $m)<option value="{{ $m->id }}">{{ $m->nome }}</option>@endforeach
            </select>
        </x-ui.field>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Abastecimentos</p>
            <p class="mt-2 text-2xl font-semibold text-tinta">{{ $totais->quantidade }}</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Litros</p>
            <p class="mt-2 text-2xl font-semibold text-tinta">{{ \App\Support\Numero::formatar($totais->litros, 3) }} L</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Valor gasto</p>
            <p class="mt-2 text-2xl font-semibold text-roxo-700">R$ {{ \App\Support\Numero::formatar($totais->valor) }}</p>
        </div>
    </div>

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,posto,veiculo,motorista,de,ate,ordenar,limparFiltros">
        @if ($abastecimentos->isEmpty())
            <x-ui.vazio icone="fuel" titulo="Nenhum abastecimento encontrado" :filtrado="$this->temFiltros" dica="Lance o primeiro abastecimento para começar." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <x-ui.th-ordenavel coluna="data_abastecimento" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Data</x-ui.th-ordenavel>
                            <th scope="col">Veículo / motorista</th>
                            <th scope="col">Posto</th>
                            <x-ui.th-ordenavel coluna="litros" :atual="$ordenarPor" :direcao="$direcao" class="text-right">Litros × preço</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="valor_total" :atual="$ordenarPor" :direcao="$direcao" class="text-right">Valor</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="odometro_km" :atual="$ordenarPor" :direcao="$direcao" class="text-right">Km inicial → final</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="media_km_l" :atual="$ordenarPor" :direcao="$direcao" class="text-right">Média</x-ui.th-ordenavel>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($abastecimentos as $a)
                            <tr wire:key="abastecimento-{{ $a->id }}">
                                <td class="whitespace-nowrap">
                                    <p class="font-medium text-tinta">{{ $a->data_abastecimento->format('d/m/Y') }}</p>
                                    <p class="text-xs text-gray-500">Sem. {{ $a->semana()->semana }}</p>
                                </td>
                                <td class="text-gray-600"><span class="font-medium text-tinta">{{ $a->veiculo->placa_formatada }}</span><span class="block text-xs">{{ $a->motorista->nome }}</span></td>
                                <td class="text-gray-600">{{ $a->posto?->nome ?? '—' }}</td>
                                <td class="text-right whitespace-nowrap">{{ \App\Support\Numero::formatar($a->litros, 3) }} L<span class="block text-xs text-gray-500">R$ {{ \App\Support\Numero::formatar($a->preco_litro, 3) }}/L</span></td>
                                <td class="text-right font-medium whitespace-nowrap">R$ {{ \App\Support\Numero::formatar($a->valor_total) }}</td>
                                <td class="text-right whitespace-nowrap text-gray-600">
                                    {{ $a->km_anterior !== null ? \App\Support\Numero::formatar($a->km_anterior, 1) : '—' }} → {{ \App\Support\Numero::formatar($a->odometro_km, 1) }}
                                    @if ($a->km_rodados !== null)<span class="block text-xs text-gray-500">{{ \App\Support\Numero::formatar($a->km_rodados, 1) }} km</span>@endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($a->media_km_l !== null)
                                        {{ \App\Support\Numero::formatar($a->media_km_l, 2) }} km/l
                                    @else — @endif
                                    @foreach ($a->alertas() as $alerta)
                                        <x-ui.badge tipo="aviso" class="mt-1" title="{{ $alerta }}">Atenção</x-ui.badge>
                                    @endforeach
                                    @unless ($a->tanque_cheio)<span class="block text-xs text-gray-400">tanque parcial</span>@endunless
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @can('update', $a)
                                        <a href="{{ route('abastecimentos.edit', $a) }}" wire:navigate class="btn btn-link"><x-ui.icon name="pencil" class="size-4" /> Editar</a>
                                    @endcan
                                    @can('delete', $a)
                                        <button type="button" wire:click="excluir({{ $a->id }})" wire:confirm="Excluir este abastecimento? O km inicial do seguinte será recalculado." class="btn btn-link text-red-700 hover:bg-red-50">Excluir</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($abastecimentos as $a)
                    <li wire:key="abastecimento-card-{{ $a->id }}" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-tinta">{{ $a->veiculo->placa_formatada }} · {{ $a->data_abastecimento->format('d/m/Y') }}</p>
                                <p class="text-sm text-gray-500">{{ $a->motorista->nome }}{{ $a->posto ? ' · '.$a->posto->nome : '' }}</p>
                            </div>
                            <span class="text-base font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($a->valor_total) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                            <x-ui.badge>{{ \App\Support\Numero::formatar($a->litros, 3) }} L</x-ui.badge>
                            @if ($a->km_rodados !== null)<x-ui.badge>{{ \App\Support\Numero::formatar($a->km_rodados, 1) }} km</x-ui.badge>@endif
                            @if ($a->media_km_l !== null)<x-ui.badge tipo="destaque">{{ \App\Support\Numero::formatar($a->media_km_l, 2) }} km/l</x-ui.badge>@endif
                            @foreach ($a->alertas() as $alerta)<x-ui.badge tipo="aviso">{{ $alerta }}</x-ui.badge>@endforeach
                            <div class="ml-auto flex gap-2">
                                @can('update', $a)<a href="{{ route('abastecimentos.edit', $a) }}" wire:navigate class="btn btn-secundario">Editar</a>@endcan
                                @can('delete', $a)<button type="button" wire:click="excluir({{ $a->id }})" wire:confirm="Excluir este abastecimento?" class="btn btn-perigo">Excluir</button>@endcan
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($abastecimentos->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $abastecimentos->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
