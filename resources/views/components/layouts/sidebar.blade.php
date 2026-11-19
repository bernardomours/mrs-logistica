@props(['iniciais'])

@php
    $usuario = auth()->user();
    $papel = $usuario->getRoleNames()->first();
    $papeis = [
        'admin' => 'Administrador', 'gestor' => 'Gestor', 'gerente_filial' => 'Gerente de filial',
        'financeiro' => 'Financeiro', 'operacional' => 'Operacional', 'consulta' => 'Consulta', 'motorista' => 'Motorista',
    ];

    // Filtra itens: rota existente + permissão.
    $grupos = collect(config('navegacao.grupos'))->map(function ($grupo) use ($usuario) {
        $grupo['itens'] = collect($grupo['itens'])->filter(fn ($i) => Route::has($i['rota']) && ($i['permissao'] === null || $usuario->can($i['permissao'])))->values();

        return $grupo;
    })->filter(fn ($g) => $g['itens']->isNotEmpty());
@endphp

{{-- Marca --}}
<div class="flex items-center justify-between px-5 pt-6 pb-5">
    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
        <x-ui.logo altura="h-14" :escuro="true" />
        <span class="leading-tight">
            <span class="block text-[13px] font-semibold text-white">MRS Logística</span>
            <span class="block text-xs text-marinho-200">Gestão de frota</span>
        </span>
    </a>
    <button type="button" class="rounded-lg p-1.5 text-marinho-300 hover:bg-marinho-800 lg:hidden" x-on:click="menuAberto = false" aria-label="Fechar menu">
        <x-ui.icon name="x" class="size-5" />
    </button>
</div>

{{-- Filial ativa --}}
<div class="px-4 pb-4">
    <livewire:layout.seletor-de-filial />
</div>

{{-- Navegação --}}
<nav class="flex-1 space-y-5 overflow-y-auto px-4 pb-4 [scrollbar-color:theme(colors.marinho-700)_transparent] [scrollbar-width:thin]">
    @foreach ($grupos as $grupo)
        <div>
            @if ($grupo['rotulo'])
                <p class="mb-2 px-3 text-[10px] font-semibold tracking-[0.2em] text-marinho-300/80 uppercase">{{ $grupo['rotulo'] }}</p>
            @endif
            <ul class="space-y-1">
                @foreach ($grupo['itens'] as $item)
                    @php($ativo = request()->routeIs(explode('.', $item['rota'])[0].'*'))
                    <li>
                        <a href="{{ route($item['rota']) }}" wire:navigate
                           @class([
                               'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition',
                               'bg-roxo-500 font-medium text-white shadow-sm' => $ativo,
                               'text-marinho-200 hover:bg-marinho-800 hover:text-white' => ! $ativo,
                           ])
                           @if ($ativo) aria-current="page" @endif>
                            <x-ui.icon :name="$item['icone']" class="size-[18px] shrink-0" />
                            <span class="truncate">{{ $item['rotulo'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>

{{-- Usuário --}}
<div class="border-t border-marinho-800 p-4">
    <div class="flex items-center gap-3">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-marinho-700 text-xs font-semibold text-white">{{ $iniciais }}</span>
        <div class="min-w-0 flex-1 leading-tight">
            <p class="truncate text-sm font-medium text-white">{{ $usuario->name }}</p>
            <p class="truncate text-xs text-marinho-300">{{ $papeis[$papel] ?? 'Sem papel' }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg p-2 text-marinho-300 hover:bg-marinho-800 hover:text-white" title="Sair" aria-label="Sair">
                <x-ui.icon name="logout" class="size-[18px]" />
            </button>
        </form>
    </div>
</div>
