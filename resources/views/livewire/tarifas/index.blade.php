<div>
    <x-ui.page-header eyebrow="MRS / Cadastros" title="Tarifas de rota" subtitle="Valor de frete sugerido para cada rota. Na viagem o valor continua editável.">
        <x-slot:actions>
            @can('create', \App\Models\Local::class)
                <button type="button" wire:click="$dispatch('abrir-novo-local')" class="btn btn-secundario">
                    <x-ui.icon name="pin" class="size-4" /> Novo local
                </button>
            @endcan
            @can('importar', \App\Models\TarifaRota::class)
                <a href="{{ route('tarifas.importar') }}" wire:navigate class="btn btn-secundario">
                    <x-ui.icon name="upload" class="size-4" /> Importar planilha
                </a>
            @endcan
            @can('create', \App\Models\TarifaRota::class)
                <a href="{{ route('tarifas.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Nova tarifa
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filtros placeholder="Origem ou destino" descricao="Busque pelo nome da origem ou do destino." :tem-filtros="$this->temFiltros">
        <x-slot:filtro>
            <x-ui.field label="Vigência" name="vigencia">
                <select wire:model.live="vigencia" class="campo">
                    <option value="todas">Todas</option>
                    <option value="vigentes">Vigentes hoje</option>
                    <option value="encerradas">Encerradas ou futuras</option>
                </select>
            </x-ui.field>
        </x-slot:filtro>
    </x-ui.filtros>

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,vigencia,ordenar,limparFiltros">
        @if ($tarifas->isEmpty())
            <x-ui.vazio icone="tag" titulo="Nenhuma tarifa encontrada" :filtrado="$this->temFiltros" dica="Cadastre a primeira tarifa para sugerir o frete nas viagens." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Rota</th>
                            <x-ui.th-ordenavel coluna="valor_frete" :atual="$ordenarPor" :direcao="$direcao" class="text-right">Frete</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="vigencia_inicio" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Vigência</x-ui.th-ordenavel>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tarifas as $tarifa)
                            <tr wire:key="tarifa-{{ $tarifa->id }}">
                                <td class="font-medium text-tinta">{{ $tarifa->origem->nome }} <span class="text-gray-400">→</span> {{ $tarifa->destino->nome }}</td>
                                <td class="text-right font-medium whitespace-nowrap">R$ {{ \App\Support\Numero::formatar($tarifa->valor_frete) }}</td>
                                <td class="whitespace-nowrap">
                                    <x-ui.badge :tipo="$tarifa->estaVigente() ? 'sucesso' : 'neutro'">
                                        {{ $tarifa->vigencia_inicio->format('d/m/Y') }} – {{ $tarifa->vigencia_fim?->format('d/m/Y') ?? 'sem fim' }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @can('update', $tarifa)
                                        <a href="{{ route('tarifas.edit', $tarifa) }}" wire:navigate class="btn btn-link"><x-ui.icon name="pencil" class="size-4" /> Editar</a>
                                    @endcan
                                    @can('delete', $tarifa)
                                        <button type="button" wire:click="excluir({{ $tarifa->id }})" wire:confirm="Excluir esta tarifa? Viagens já lançadas não são afetadas." class="btn btn-link text-red-700 hover:bg-red-50">
                                            <x-ui.icon name="x" class="size-4" /> Excluir
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($tarifas as $tarifa)
                    <li wire:key="tarifa-card-{{ $tarifa->id }}" class="space-y-3 p-4">
                        <p class="font-medium text-tinta">{{ $tarifa->origem->nome }} <span class="text-gray-400">→</span> {{ $tarifa->destino->nome }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($tarifa->valor_frete) }}</span>
                            <x-ui.badge :tipo="$tarifa->estaVigente() ? 'sucesso' : 'neutro'">
                                {{ $tarifa->vigencia_inicio->format('d/m/Y') }} – {{ $tarifa->vigencia_fim?->format('d/m/Y') ?? 'sem fim' }}
                            </x-ui.badge>
                            <div class="ml-auto flex gap-2">
                                @can('update', $tarifa)
                                    <a href="{{ route('tarifas.edit', $tarifa) }}" wire:navigate class="btn btn-secundario">Editar</a>
                                @endcan
                                @can('delete', $tarifa)
                                    <button type="button" wire:click="excluir({{ $tarifa->id }})" wire:confirm="Excluir esta tarifa? Viagens já lançadas não são afetadas." class="btn btn-perigo">Excluir</button>
                                @endcan
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($tarifas->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $tarifas->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    <livewire:tarifas.novo-local />
</div>
