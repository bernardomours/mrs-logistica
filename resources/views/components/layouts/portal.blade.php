@props(['title' => null])

@php
    $usuario = auth()->user();
    $motorista = app(\App\Services\PerfilDoMotorista::class)->motoristaDoUsuario($usuario);
    $filial = $motorista?->filial;
    $itens = [
        ['rota' => 'portal.dashboard', 'rotulo' => 'Dashboard', 'icone' => 'grid'],
        ['rota' => 'portal.abastecimentos', 'rotulo' => 'Abastecimentos', 'icone' => 'fuel'],
        ['rota' => 'portal.dados', 'rotulo' => 'Dados pessoais', 'icone' => 'user'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1b1f3b">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-fundo text-tinta antialiased"
      x-data="{
          aberto: false,
          recolhido: false,
          init() { try { this.recolhido = localStorage.getItem('portal-menu-recolhido') === '1'; } catch (e) {} },
          alternar() {
              if (window.matchMedia('(min-width: 1024px)').matches) {
                  this.recolhido = ! this.recolhido;
                  try { localStorage.setItem('portal-menu-recolhido', this.recolhido ? '1' : '0'); } catch (e) {}
              } else {
                  this.aberto = ! this.aberto;
              }
          },
      }"
      x-on:keydown.escape.window="aberto = false">
    {{-- Fundo escuro do menu no celular --}}
    <div x-cloak x-show="aberto" x-transition.opacity class="fixed inset-0 z-30 bg-marinho-950/60 lg:hidden" x-on:click="aberto = false"></div>

    <aside id="menu-portal"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-marinho-900 text-marinho-200 transition-transform duration-200"
           :class="(aberto ? 'translate-x-0' : '-translate-x-full') + ' ' + (recolhido ? 'lg:-translate-x-full' : 'lg:translate-x-0')"
           aria-label="Menu do motorista">
        <div class="flex items-center justify-between px-5 pt-6 pb-5">
            <a href="{{ route('portal.dashboard') }}" wire:navigate class="flex items-center gap-3">
                <x-ui.logo altura="h-14" :escuro="true" />
                <span class="leading-tight">
                    <span class="block text-[13px] font-semibold text-white">MRS Logística</span>
                    <span class="block text-xs text-marinho-200">Área do motorista</span>
                </span>
            </a>
            <button type="button" class="rounded-lg p-1.5 text-marinho-300 hover:bg-marinho-800" x-on:click="alternar()" aria-label="Fechar menu">
                <x-ui.icon name="x" class="size-5" />
            </button>
        </div>

        {{-- Filial / transportadora onde o motorista trabalha --}}
        @if ($filial)
            <div class="mx-4 mb-4 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <p class="text-[10px] font-semibold tracking-[0.2em] text-marinho-300/80 uppercase">Filial</p>
                <p class="mt-1 text-sm font-semibold text-white">{{ $filial->nome }}</p>
                @if ($filial->cidade || $filial->uf)
                    <p class="text-xs text-marinho-200">{{ collect([$filial->cidade, $filial->uf])->filter()->implode('/') }}</p>
                @endif
            </div>
        @endif

        <nav class="flex-1 space-y-1 overflow-y-auto px-4 pb-4">
            @foreach ($itens as $item)
                @php($ativo = request()->routeIs($item['rota']))
                <a href="{{ route($item['rota']) }}" wire:navigate x-on:click="aberto = false"
                   @class([
                       'flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition',
                       'bg-roxo-500 font-medium text-white shadow-sm' => $ativo,
                       'text-marinho-200 hover:bg-marinho-800 hover:text-white' => ! $ativo,
                   ])
                   @if ($ativo) aria-current="page" @endif>
                    <x-ui.icon :name="$item['icone']" class="size-[18px] shrink-0" />
                    <span class="truncate">{{ $item['rotulo'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="space-y-1 border-t border-white/10 px-4 py-4">
            <p class="truncate px-3 pb-1 text-xs text-marinho-300">{{ $usuario->name }}</p>
            <a href="{{ route('senha.trocar') }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-marinho-200 hover:bg-marinho-800 hover:text-white">
                <x-ui.icon name="lock" class="size-[18px] shrink-0" /> Trocar senha
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-marinho-200 hover:bg-marinho-800 hover:text-white">
                    <x-ui.icon name="logout" class="size-[18px] shrink-0" /> Sair
                </button>
            </form>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col transition-[padding] duration-200" :class="recolhido ? 'lg:pl-0' : 'lg:pl-64'">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-borda bg-white px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="-ml-1 grid size-11 place-items-center rounded-lg text-gray-600 hover:bg-gray-100" x-on:click="alternar()"
                        aria-label="Abrir ou fechar o menu" aria-controls="menu-portal" x-bind:aria-expanded="(aberto || (! recolhido && window.matchMedia('(min-width: 1024px)').matches)).toString()">
                    <x-ui.icon name="menu" />
                </button>
                <h1 class="truncate text-base font-semibold text-tinta">{{ $title ?? 'Painel do motorista' }}</h1>
            </div>
            @if ($filial)
                <span class="hidden truncate text-xs text-gray-500 sm:inline">{{ $filial->nome }}</span>
            @endif
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-5 pb-12 sm:px-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toasts />
</body>
</html>
