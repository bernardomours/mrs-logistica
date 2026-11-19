@props(['title' => null])

@php
    $usuario = auth()->user();
    $iniciais = collect(explode(' ', trim($usuario->name)))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');

    // Breadcrumb a partir do nome da rota: "filiais.edit" => Início › Filiais › Editar
    $rota = request()->route()?->getName() ?? '';
    $partes = explode('.', $rota);
    $modulo = config('navegacao.modulos.'.($partes[0] ?? ''), null);
    $acao = config('navegacao.acoes.'.($partes[1] ?? ''), null);
    $migalhas = array_values(array_filter([
        ['rotulo' => 'Início', 'url' => route('dashboard')],
        $modulo && ($partes[0] ?? '') !== 'dashboard'
            ? ['rotulo' => $modulo, 'url' => Route::has(($partes[0]).'.index') && $acao ? route($partes[0].'.index') : null]
            : null,
        $acao ? ['rotulo' => $acao, 'url' => null] : null,
    ]));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-fundo text-tinta antialiased" x-data="{ menuAberto: false }" x-on:keydown.escape.window="menuAberto = false">
    {{-- Fundo escuro do menu no celular --}}
    <div x-cloak x-show="menuAberto" x-transition.opacity class="fixed inset-0 z-30 bg-marinho-950/60 lg:hidden" x-on:click="menuAberto = false"></div>

    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-marinho-900 text-marinho-200 transition-transform duration-200 lg:translate-x-0 print:hidden"
        :class="{ 'translate-x-0': menuAberto }"
        aria-label="Menu principal"
    >
        <x-layouts.sidebar :iniciais="$iniciais" />
    </aside>

    <div class="flex min-h-screen flex-col lg:pl-64 print:pl-0">
        <header class="sticky top-0 z-20 flex h-16 print:hidden items-center justify-between gap-3 border-b border-borda bg-white px-4 sm:px-6 lg:h-[72px] lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="-ml-1 rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:hidden" x-on:click="menuAberto = true" aria-label="Abrir menu">
                    <x-ui.icon name="menu" />
                </button>

                <nav aria-label="Breadcrumb" class="min-w-0 truncate text-xs text-gray-500">
                    @foreach ($migalhas as $migalha)
                        @if (! $loop->first)
                            <span class="mx-2 text-gray-300">›</span>
                        @endif
                        @if ($migalha['url'] && ! $loop->last)
                            <a href="{{ $migalha['url'] }}" wire:navigate class="hover:text-roxo-700">{{ $migalha['rotulo'] }}</a>
                        @else
                            <span @class(['font-medium text-tinta' => $loop->last])>{{ $migalha['rotulo'] }}</span>
                        @endif
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-3 sm:gap-4">
                <x-ui.sino />
                <span class="grid size-9 place-items-center rounded-full bg-roxo-100 text-xs font-semibold text-roxo-700" title="{{ $usuario->name }}">{{ $iniciais }}</span>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toasts />
</body>
</html>
