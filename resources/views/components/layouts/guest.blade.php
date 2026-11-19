@props(['title' => null])

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
<body class="min-h-screen bg-marinho-900 text-tinta antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <x-ui.logo altura="h-32" :escuro="true" />
                <span class="leading-tight">
                    <span class="block text-xs font-bold tracking-[0.18em] text-white uppercase">MRS Logística</span>
                    <span class="block text-sm text-marinho-200">Gestão de frota</span>
                </span>
            </div>

            <div class="rounded-2xl border border-borda bg-white p-6 shadow-xl sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-xs text-marinho-300">Ambiente privado · dados protegidos</p>
        </div>
    </main>
</body>
</html>
