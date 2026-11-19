<div>
    <x-ui.page-header
        eyebrow="MRS / Cadastros"
        title="Filiais"
        subtitle="Unidades da empresa. Cada filial tem seus motoristas, caminhões e lançamentos."
    >
        <x-slot:actions>
            @can('create', \App\Models\Filial::class)
                <a href="{{ route('filiais.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Nova filial
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Filtros --}}
    <x-ui.card title="Filtros" description="Busque por nome, código ou cidade." class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_auto] lg:items-end">
            <x-ui.field label="Busca" name="busca">
                <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Nome, código ou cidade" class="campo">
            </x-ui.field>

            <x-ui.field label="Situação" name="status">
                <select wire:model.live="status" class="campo">
                    <option value="todos">Todas</option>
                    <option value="ativos">Ativas</option>
                    <option value="inativos">Inativas</option>
                </select>
            </x-ui.field>

            <button type="button" wire:click="limparFiltros" @disabled(! $this->temFiltros) class="btn btn-secundario">
                Limpar filtros
            </button>
        </div>
    </x-ui.card>

    {{-- Listagem --}}
    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,status,ordenar,limparFiltros">
        @if ($filiais->isEmpty())
            <div class="px-6 py-14 text-center">
                <x-ui.icon name="building" class="mx-auto size-10 text-gray-300" />
                <p class="mt-3 text-sm font-medium text-tinta">Nenhuma filial encontrada</p>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($this->temFiltros) Ajuste ou limpe os filtros para ver mais resultados.
                    @else Cadastre a primeira filial para começar. @endif
                </p>
            </div>
        @else
            {{-- Tabela (telas médias e maiores) --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            @foreach (['nome' => 'Filial', 'codigo' => 'Código', 'cidade' => 'Cidade/UF', 'ativo' => 'Situação'] as $coluna => $rotulo)
                                <th scope="col" @if ($this->ordenarPor === $coluna || ($this->ordenarPor === '' && $coluna === 'nome')) aria-sort="{{ $this->direcao === 'desc' ? 'descending' : 'ascending' }}" @endif>
                                    <button type="button" wire:click="ordenar('{{ $coluna }}')" class="inline-flex items-center gap-1 uppercase hover:text-roxo-700">
                                        {{ $rotulo }} <x-ui.icon name="sort" class="size-3.5 opacity-60" />
                                    </button>
                                </th>
                            @endforeach
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filiais as $filial)
                            <tr wire:key="filial-{{ $filial->id }}">
                                <td class="font-medium text-tinta">{{ $filial->nome }}</td>
                                <td><x-ui.badge tipo="destaque">{{ $filial->codigo }}</x-ui.badge></td>
                                <td class="text-gray-600">{{ collect([$filial->cidade, $filial->uf])->filter()->implode('/') ?: '—' }}</td>
                                <td>
                                    <x-ui.badge :tipo="$filial->ativo ? 'sucesso' : 'neutro'">{{ $filial->ativo ? 'Ativa' : 'Inativa' }}</x-ui.badge>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @can('update', $filial)
                                        <a href="{{ route('filiais.edit', $filial) }}" wire:navigate class="btn btn-link">
                                            <x-ui.icon name="pencil" class="size-4" /> Editar
                                        </a>
                                    @endcan
                                    @can('desativar', $filial)
                                        <button type="button" wire:click="alternarAtivo({{ $filial->id }})"
                                                wire:confirm="{{ $filial->ativo ? 'Desativar a filial '.$filial->nome.'?' : 'Reativar a filial '.$filial->nome.'?' }}"
                                                class="btn btn-link {{ $filial->ativo ? 'text-red-700 hover:bg-red-50' : '' }}">
                                            <x-ui.icon name="power" class="size-4" /> {{ $filial->ativo ? 'Desativar' : 'Reativar' }}
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Cartões (celular) --}}
            <ul class="divide-y divide-borda md:hidden">
                @foreach ($filiais as $filial)
                    <li wire:key="filial-card-{{ $filial->id }}" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-tinta">{{ $filial->nome }}</p>
                                <p class="text-sm text-gray-500">{{ collect([$filial->cidade, $filial->uf])->filter()->implode('/') ?: 'Cidade não informada' }}</p>
                            </div>
                            <x-ui.badge :tipo="$filial->ativo ? 'sucesso' : 'neutro'">{{ $filial->ativo ? 'Ativa' : 'Inativa' }}</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge tipo="destaque">{{ $filial->codigo }}</x-ui.badge>
                            @can('update', $filial)
                                <a href="{{ route('filiais.edit', $filial) }}" wire:navigate class="btn btn-secundario ml-auto">Editar</a>
                            @endcan
                            @can('desativar', $filial)
                                <button type="button" wire:click="alternarAtivo({{ $filial->id }})"
                                        wire:confirm="{{ $filial->ativo ? 'Desativar a filial '.$filial->nome.'?' : 'Reativar a filial '.$filial->nome.'?' }}"
                                        class="btn {{ $filial->ativo ? 'btn-perigo' : 'btn-secundario' }}">
                                    {{ $filial->ativo ? 'Desativar' : 'Reativar' }}
                                </button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($filiais->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $filiais->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
