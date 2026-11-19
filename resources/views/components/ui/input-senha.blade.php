{{-- Campo de senha com botão de mostrar/ocultar. Os atributos (wire:model, autocomplete, autofocus…) vão para o <input>. Uso: <x-ui.input-senha wire:model="password" :erro="$errors->has('password')" /> --}}
@props(['erro' => false])

<div class="relative" x-data="{ visivel: false }">
    <input x-bind:type="visivel ? 'text' : 'password'" type="password" {{ $attributes->class(['campo pr-12', 'campo-erro' => $erro]) }}>
    <button type="button" x-on:click="visivel = ! visivel" x-bind:aria-pressed="visivel.toString()"
            x-bind:aria-label="visivel ? 'Ocultar senha' : 'Mostrar senha'" x-bind:title="visivel ? 'Ocultar senha' : 'Mostrar senha'"
            class="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-lg text-gray-400 hover:text-roxo-700 focus:text-roxo-700 focus:outline-none">
        <span x-show="! visivel"><x-ui.icon name="eye" class="size-5" /></span>
        <span x-cloak x-show="visivel"><x-ui.icon name="eye-off" class="size-5" /></span>
    </button>
</div>
