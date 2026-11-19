{{-- Rótulo + controle + erro. Uso: <x-ui.field label="Nome" name="form.nome" required> <input class="campo" wire:model="form.nome"> </x-ui.field> --}}
@props(['label', 'name', 'required' => false, 'hint' => null])

<div {{ $attributes }}>
    <label class="rotulo" @if ($for ?? false) for="{{ $for }}" @endif>
        {{ $label }}@if ($required)<span class="text-red-500" aria-hidden="true"> *</span>@endif
    </label>

    {{ $slot }}

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
