{{-- Painel "Filtros" padrão das listagens: busca + situação (ou slot próprio) + limpar. Requer a trait WithFiltros. --}}
@props(['placeholder' => 'Buscar', 'descricao' => 'Refine a lista com os filtros abaixo.', 'temFiltros' => false])

<x-ui.card title="Filtros" :description="$descricao" class="mb-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_auto] lg:items-end">
        <x-ui.field label="Busca" name="busca">
            <input type="search" wire:model.live.debounce.300ms="busca" placeholder="{{ $placeholder }}" class="campo">
        </x-ui.field>

        @if (isset($filtro))
            {{ $filtro }}
        @else
            <x-ui.field label="Situação" name="status">
                <select wire:model.live="status" class="campo">
                    <option value="todos">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </x-ui.field>
        @endif

        <button type="button" wire:click="limparFiltros" @disabled(! $temFiltros) class="btn btn-secundario">Limpar filtros</button>
    </div>
</x-ui.card>
