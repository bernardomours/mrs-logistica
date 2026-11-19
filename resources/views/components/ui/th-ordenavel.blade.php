{{-- Cabeçalho de coluna ordenável. Requer a trait WithOrdenacao no componente. --}}
@props(['coluna', 'atual', 'direcao' => 'asc', 'padrao' => false])

@php($ativa = $atual === $coluna || ($atual === '' && $padrao))

<th scope="col" @if ($ativa) aria-sort="{{ $direcao === 'desc' ? 'descending' : 'ascending' }}" @endif {{ $attributes }}>
    <button type="button" wire:click="ordenar('{{ $coluna }}')" class="inline-flex items-center gap-1 uppercase hover:text-roxo-700">
        {{ $slot }} <x-ui.icon name="sort" @class(['size-3.5', 'opacity-60' => ! $ativa, 'text-roxo-600' => $ativa]) />
    </button>
</th>
