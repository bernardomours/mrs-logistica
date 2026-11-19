{{-- Seletor de período do portal, no mesmo estilo do fechamento semanal: ◀ período ▶ e "ir para o atual". As ações são métodos do componente Livewire. --}}
@props(['rotulo', 'detalhe', 'anterior', 'proximo' => null, 'atual' => null, 'atualRotulo' => 'Ir para a semana atual'])

<div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-borda bg-white p-3 shadow-cartao sm:p-4">
    <div class="flex w-full items-center justify-between gap-2 sm:w-auto sm:justify-start">
        <button type="button" wire:click="{{ $anterior }}" class="btn btn-secundario min-h-11 min-w-11 px-0" aria-label="Período anterior">
            <x-ui.icon name="chevron-left" class="size-4" />
        </button>
        <div class="min-w-0 flex-1 text-center sm:min-w-52 sm:flex-none">
            <p class="text-xs font-medium tracking-wide text-gray-500 uppercase">{{ $rotulo }}</p>
            <p class="text-sm font-semibold text-tinta">{{ $detalhe }}</p>
        </div>
        <button type="button" @if ($proximo) wire:click="{{ $proximo }}" @else disabled @endif class="btn btn-secundario min-h-11 min-w-11 px-0" aria-label="Próximo período">
            <x-ui.icon name="chevron-right" class="size-4" />
        </button>
    </div>

    @if ($atual)
        <button type="button" wire:click="{{ $atual }}" class="btn btn-link min-h-11">{{ $atualRotulo }}</button>
    @endif
</div>
