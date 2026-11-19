<div>
    <x-ui.page-header eyebrow="MRS / Cadastros" title="Postos" subtitle="Postos de combustível usados nos abastecimentos. Compartilhados entre as filiais.">
        <x-slot:actions>
            @can('create', \App\Models\Posto::class)
                <a href="{{ route('postos.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Novo posto
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filtros placeholder="Nome, cidade ou CNPJ" descricao="Busque por nome, cidade ou CNPJ." :tem-filtros="$this->temFiltros" />

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,status,ordenar,limparFiltros">
        @if ($postos->isEmpty())
            <x-ui.vazio icone="droplet" titulo="Nenhum posto encontrado" :filtrado="$this->temFiltros" dica="Cadastre o primeiro posto para começar." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <x-ui.th-ordenavel coluna="nome" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Posto</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="cidade" :atual="$ordenarPor" :direcao="$direcao">Cidade/UF</x-ui.th-ordenavel>
                            <th scope="col">CNPJ</th>
                            <x-ui.th-ordenavel coluna="ativo" :atual="$ordenarPor" :direcao="$direcao">Situação</x-ui.th-ordenavel>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($postos as $posto)
                            <tr wire:key="posto-{{ $posto->id }}">
                                <td class="font-medium text-tinta">{{ $posto->nome }}</td>
                                <td class="text-gray-600">{{ collect([$posto->cidade, $posto->uf])->filter()->implode('/') ?: '—' }}</td>
                                <td class="whitespace-nowrap text-gray-600">{{ $posto->cnpj ? \App\Support\Texto::cnpj($posto->cnpj) : '—' }}</td>
                                <td><x-ui.badge :tipo="$posto->ativo ? 'sucesso' : 'neutro'">{{ $posto->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge></td>
                                <td class="text-right whitespace-nowrap">
                                    <x-ui.acoes-linha :editar="route('postos.edit', $posto)" :pode-editar="auth()->user()->can('update', $posto)"
                                        :pode-desativar="auth()->user()->can('desativar', $posto)" :ativo="$posto->ativo" :id="$posto->id" :nome="'o posto '.$posto->nome" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($postos as $posto)
                    <li wire:key="posto-card-{{ $posto->id }}" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-tinta">{{ $posto->nome }}</p>
                                <p class="text-sm text-gray-500">{{ collect([$posto->cidade, $posto->uf])->filter()->implode('/') ?: 'Cidade não informada' }}</p>
                            </div>
                            <x-ui.badge :tipo="$posto->ativo ? 'sucesso' : 'neutro'">{{ $posto->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge>
                        </div>
                        <div class="flex justify-end gap-2">
                            <x-ui.acoes-linha :compacto="true" :editar="route('postos.edit', $posto)" :pode-editar="auth()->user()->can('update', $posto)"
                                :pode-desativar="auth()->user()->can('desativar', $posto)" :ativo="$posto->ativo" :id="$posto->id" :nome="'o posto '.$posto->nome" />
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($postos->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $postos->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
