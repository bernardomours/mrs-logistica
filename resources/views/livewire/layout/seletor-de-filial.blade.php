<div class="rounded-2xl border border-marinho-700 bg-marinho-800/70 p-3">
    <div class="flex items-center gap-3">
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-marinho-700 text-marinho-200">
            <x-ui.icon name="building" class="size-5" />
        </span>
        <div class="min-w-0 flex-1 leading-tight">
            <p class="truncate text-sm font-medium text-white">
                @if ($atual)
                    {{ $atual->nome }}
                @elseif ($disponiveis->isEmpty())
                    Sem filial
                @elseif ($podeVerTodas)
                    Todas as filiais
                @else
                    {{ $disponiveis->first()->nome }}
                @endif
            </p>
            <p class="truncate text-xs text-marinho-300">Filial ativa</p>
        </div>
    </div>

    @if ($disponiveis->count() > 1 || ($podeVerTodas && $disponiveis->isNotEmpty()))
        <label for="seletor-filial" class="sr-only">Selecionar filial</label>
        <select id="seletor-filial" wire:model.live="selecionada"
                class="mt-3 w-full rounded-lg border border-marinho-700 bg-marinho-900 px-3 py-2 text-sm text-white focus:border-roxo-500 focus:ring-2 focus:ring-roxo-600/40 focus:outline-none">
            @if ($podeVerTodas)
                <option value="todas">Todas as filiais</option>
            @endif
            @foreach ($disponiveis as $filial)
                <option value="{{ $filial->id }}">{{ $filial->nome }}</option>
            @endforeach
        </select>
    @endif
</div>
