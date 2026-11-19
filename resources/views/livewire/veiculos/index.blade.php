<div>
    <x-ui.page-header eyebrow="MRS / Cadastros" title="Veículos" subtitle="Caminhões e demais veículos de cada filial.">
        <x-slot:actions>
            @can('create', \App\Models\Veiculo::class)
                <a href="{{ route('veiculos.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Novo veículo
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filtros placeholder="Placa, marca ou modelo" descricao="Busque por placa, marca ou modelo." :tem-filtros="$this->temFiltros" />

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,status,ordenar,limparFiltros">
        @if ($veiculos->isEmpty())
            <x-ui.vazio icone="truck" titulo="Nenhum veículo encontrado" :filtrado="$this->temFiltros" dica="Cadastre o primeiro veículo para começar." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <x-ui.th-ordenavel coluna="placa" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Placa</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="tipo" :atual="$ordenarPor" :direcao="$direcao">Tipo</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="marca" :atual="$ordenarPor" :direcao="$direcao">Marca / modelo</x-ui.th-ordenavel>
                            <th scope="col">Filial</th>
                            <th scope="col">Motorista padrão</th>
                            <x-ui.th-ordenavel coluna="ativo" :atual="$ordenarPor" :direcao="$direcao">Situação</x-ui.th-ordenavel>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($veiculos as $veiculo)
                            <tr wire:key="veiculo-{{ $veiculo->id }}">
                                <td class="font-medium whitespace-nowrap text-tinta"><a href="{{ route('veiculos.show', $veiculo) }}" wire:navigate class="hover:text-roxo-700 hover:underline" title="Abrir a ficha do veículo">{{ $veiculo->placa_formatada }}</a></td>
                                <td class="text-gray-600">{{ $veiculo->tipo->rotulo() }}</td>
                                <td class="text-gray-600">{{ collect([$veiculo->marca, $veiculo->modelo])->filter()->implode(' ') ?: '—' }}</td>
                                <td><x-ui.badge tipo="destaque">{{ $veiculo->filial->codigo }}</x-ui.badge></td>
                                <td class="text-gray-600">{{ $veiculo->motoristaPadrao?->nome ?? '—' }}</td>
                                <td><x-ui.badge :tipo="$veiculo->ativo ? 'sucesso' : 'neutro'">{{ $veiculo->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge></td>
                                <td class="text-right whitespace-nowrap">
                                    <x-ui.acoes-linha :editar="route('veiculos.edit', $veiculo)" :pode-editar="auth()->user()->can('update', $veiculo)"
                                        :pode-desativar="auth()->user()->can('desativar', $veiculo)" :ativo="$veiculo->ativo" :id="$veiculo->id" :nome="'o veículo '.$veiculo->placa_formatada" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($veiculos as $veiculo)
                    <li wire:key="veiculo-card-{{ $veiculo->id }}" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-tinta"><a href="{{ route('veiculos.show', $veiculo) }}" wire:navigate>{{ $veiculo->placa_formatada }}</a></p>
                                <p class="text-sm text-gray-500">{{ $veiculo->tipo->rotulo() }}{{ $veiculo->marca ? ' · '.$veiculo->marca : '' }}</p>
                            </div>
                            <x-ui.badge :tipo="$veiculo->ativo ? 'sucesso' : 'neutro'">{{ $veiculo->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge tipo="destaque">{{ $veiculo->filial->codigo }}</x-ui.badge>
                            <div class="ml-auto flex gap-2">
                                <x-ui.acoes-linha :compacto="true" :editar="route('veiculos.edit', $veiculo)" :pode-editar="auth()->user()->can('update', $veiculo)"
                                    :pode-desativar="auth()->user()->can('desativar', $veiculo)" :ativo="$veiculo->ativo" :id="$veiculo->id" :nome="'o veículo '.$veiculo->placa_formatada" />
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($veiculos->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $veiculos->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
