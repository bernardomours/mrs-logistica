{{-- Sino de notificações da barra superior (no celular o balão ocupa a largura da tela; a partir de sm fica preso ao sino). Abre um balão; sem itens mostra "Nenhuma notificação". Cada item: ['titulo' => ..., 'texto' => ..., 'quando' => ..., 'url' => ...]. --}}
@props(['itens' => []])

<div class="sm:relative" x-data="{ aberto: false }" x-on:keydown.escape.window="aberto = false" x-on:click.outside="aberto = false">
    <button type="button" x-on:click="aberto = ! aberto" x-bind:aria-expanded="aberto.toString()" aria-haspopup="dialog" aria-controls="balao-notificacoes"
            class="relative grid size-10 place-items-center rounded-full text-gray-500 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none" aria-label="Notificações">
        <x-ui.icon name="bell" class="size-5" />
        @if (count($itens) > 0)
            <span class="absolute top-2 right-2 size-2 rounded-full bg-red-500 ring-2 ring-white" aria-hidden="true"></span>
        @endif
    </button>

    <div id="balao-notificacoes" x-cloak x-show="aberto" x-transition.origin.top.right.duration.150ms role="dialog" aria-label="Notificações"
         class="fixed inset-x-4 top-[4.25rem] z-40 overflow-hidden rounded-xl border border-borda bg-white shadow-xl sm:absolute sm:inset-x-auto sm:top-full sm:right-0 sm:mt-2 sm:w-80">
        <div class="flex items-center justify-between border-b border-borda px-4 py-3">
            <p class="text-sm font-semibold text-tinta">Notificações</p>
            @if (count($itens) > 0)
                <span class="rounded-full bg-roxo-50 px-2 py-0.5 text-xs font-medium text-roxo-700">{{ count($itens) }}</span>
            @endif
        </div>

        @if (count($itens) === 0)
            <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                <span class="grid size-10 place-items-center rounded-full bg-gray-100 text-gray-400"><x-ui.icon name="bell" class="size-5" /></span>
                <p class="text-sm text-gray-500">Nenhuma notificação</p>
            </div>
        @else
            <ul class="max-h-80 divide-y divide-borda overflow-y-auto">
                @foreach ($itens as $item)
                    <li>
                        <a href="{{ $item['url'] ?? '#' }}" class="block px-4 py-3 hover:bg-gray-50">
                            <p class="text-sm font-medium text-tinta">{{ $item['titulo'] }}</p>
                            @isset($item['texto'])<p class="mt-0.5 text-xs text-gray-500">{{ $item['texto'] }}</p>@endisset
                            @isset($item['quando'])<p class="mt-1 text-[11px] text-gray-400">{{ $item['quando'] }}</p>@endisset
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
