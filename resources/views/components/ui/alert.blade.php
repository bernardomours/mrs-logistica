{{-- Faixa informativa lilás (padrão do modelo visual). Tipos: info, aviso, erro. --}}
@props(['tipo' => 'info', 'icone' => null])

@php
    $classes = match ($tipo) {
        'aviso' => 'border-amber-200 bg-amber-50 text-amber-900',
        'erro' => 'border-red-200 bg-red-50 text-red-900',
        default => 'border-roxo-100 bg-roxo-50 text-tinta',
    };
    $icone ??= $tipo === 'info' ? 'info' : 'alert';
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-xl border px-4 py-3 text-sm', $classes]) }} role="status">
    <x-ui.icon :name="$icone" class="mt-0.5 size-4 shrink-0 opacity-70" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
