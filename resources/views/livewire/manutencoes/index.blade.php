<div>
    <x-ui.page-header eyebrow="MRS / Operação" title="Manutenções" subtitle="Serviços feitos nos caminhões. São informativos: não entram no líquido semanal do motorista.">
        <x-slot:actions>
            @can('create', \App\Models\Manutencao::class)
                <a href="{{ route('manutencoes.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Nova manutenção
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.manutencoes._alertas', ['alertas' => $alertas])

    <x-ui.filtros placeholder="Placa, serviço, oficina ou nota fiscal" descricao="Filtre por período, veículo ou tipo." :tem-filtros="$this->temFiltros">
        <x-slot:filtro>
            <x-ui.field label="Tipo" name="status">
                <select wire:model.live="status" class="campo">
                    <option value="todos">Todos</option>
                    @foreach ($tipos as $tipo)<option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>@endforeach
                </select>
            </x-ui.field>
        </x-slot:filtro>
    </x-ui.filtros>

    <div class="-mt-3 mb-6 grid gap-4 rounded-2xl border border-borda bg-white p-5 shadow-cartao sm:grid-cols-3">
        <x-ui.field label="De" name="de"><input type="date" wire:model.live="de" class="campo"></x-ui.field>
        <x-ui.field label="Até" name="ate"><input type="date" wire:model.live="ate" class="campo"></x-ui.field>
        <x-ui.field label="Veículo" name="veiculo">
            <select wire:model.live="veiculo" class="campo">
                <option value="">Todos</option>
                @foreach ($veiculos as $v)<option value="{{ $v->id }}">{{ $v->placa }}</option>@endforeach
            </select>
        </x-ui.field>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Manutenções</p>
            <p class="mt-2 text-2xl font-semibold text-tinta">{{ $totais->quantidade }}</p>
        </div>
        @can('manutencoes.ver_valores')
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Peças</p>
                <p class="mt-2 text-2xl font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($totais->pecas) }}</p>
            </div>
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Mão de obra</p>
                <p class="mt-2 text-2xl font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($totais->mao_de_obra) }}</p>
            </div>
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Total gasto</p>
                <p class="mt-2 text-2xl font-semibold text-roxo-700">R$ {{ \App\Support\Numero::formatar($totais->total) }}</p>
            </div>
        @endcan
    </div>

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,status,veiculo,de,ate,ordenar,limparFiltros">
        @if ($manutencoes->isEmpty())
            <x-ui.vazio icone="wrench" titulo="Nenhuma manutenção encontrada" :filtrado="$this->temFiltros" dica="Registre a primeira manutenção para começar." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <x-ui.th-ordenavel coluna="data_manutencao" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Data</x-ui.th-ordenavel>
                            <th scope="col">Veículo</th>
                            <x-ui.th-ordenavel coluna="tipo" :atual="$ordenarPor" :direcao="$direcao">Tipo</x-ui.th-ordenavel>
                            <th scope="col">Serviço</th>
                            @can('manutencoes.ver_valores')
                                <x-ui.th-ordenavel coluna="valor_total" :atual="$ordenarPor" :direcao="$direcao" class="text-right">Total</x-ui.th-ordenavel>
                            @endcan
                            <th scope="col">Próxima</th>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($manutencoes as $m)
                            <tr wire:key="manutencao-{{ $m->id }}">
                                <td class="whitespace-nowrap font-medium text-tinta">{{ $m->data_manutencao->format('d/m/Y') }}</td>
                                <td class="text-gray-600"><span class="font-medium text-tinta">{{ $m->veiculo->placa_formatada }}</span>@if ($m->motorista)<span class="block text-xs">{{ $m->motorista->nome }}</span>@endif</td>
                                <td><x-ui.badge :tipo="$m->tipo->tipoDeBadge()">{{ $m->tipo->rotulo() }}</x-ui.badge></td>
                                <td class="max-w-xs text-gray-600"><span class="line-clamp-2">{{ $m->descricao }}</span>@if ($m->oficina)<span class="block text-xs text-gray-500">{{ $m->oficina }}</span>@endif</td>
                                @can('manutencoes.ver_valores')
                                    <td class="text-right whitespace-nowrap">R$ {{ \App\Support\Numero::formatar($m->valor_total) }}<span class="block text-xs text-gray-500">peças {{ \App\Support\Numero::formatar($m->valor_pecas) }} · m.o. {{ \App\Support\Numero::formatar($m->valor_mao_obra) }}</span></td>
                                @endcan
                                <td class="whitespace-nowrap text-xs text-gray-600">
                                    @if ($m->proxima_km !== null){{ \App\Support\Numero::formatar($m->proxima_km, 1) }} km<br>@endif
                                    @if ($m->proxima_data){{ $m->proxima_data->format('d/m/Y') }}@endif
                                    @if ($m->proxima_km === null && ! $m->proxima_data)—@endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @can('update', $m)
                                        <a href="{{ route('manutencoes.edit', $m) }}" wire:navigate class="btn btn-link"><x-ui.icon name="pencil" class="size-4" /> Editar</a>
                                    @endcan
                                    @can('delete', $m)
                                        <button type="button" wire:click="excluir({{ $m->id }})" wire:confirm="Excluir esta manutenção? A ação fica registrada na auditoria." class="btn btn-link text-red-700 hover:bg-red-50">Excluir</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($manutencoes as $m)
                    <li wire:key="manutencao-card-{{ $m->id }}" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-tinta">{{ $m->veiculo->placa_formatada }} · {{ $m->data_manutencao->format('d/m/Y') }}</p>
                                <p class="line-clamp-2 text-sm text-gray-500">{{ $m->descricao }}</p>
                            </div>
                            <x-ui.badge :tipo="$m->tipo->tipoDeBadge()">{{ $m->tipo->rotulo() }}</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @can('manutencoes.ver_valores')<span class="text-base font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($m->valor_total) }}</span>@endcan
                            <div class="ml-auto flex gap-2">
                                @can('update', $m)<a href="{{ route('manutencoes.edit', $m) }}" wire:navigate class="btn btn-secundario">Editar</a>@endcan
                                @can('delete', $m)<button type="button" wire:click="excluir({{ $m->id }})" wire:confirm="Excluir esta manutenção?" class="btn btn-perigo">Excluir</button>@endcan
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($manutencoes->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $manutencoes->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
