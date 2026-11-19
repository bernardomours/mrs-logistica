@props(['tipo' => 'neutro'])

@php
    $classes = match ($tipo) {
        'sucesso' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'aviso' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'erro' => 'bg-red-50 text-red-700 ring-red-600/15',
        'destaque' => 'bg-roxo-50 text-roxo-700 ring-roxo-600/15',
        default => 'bg-gray-100 text-gray-600 ring-gray-500/10',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', $classes]) }}>{{ $slot }}</span>
