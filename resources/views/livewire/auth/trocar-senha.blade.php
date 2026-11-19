<div>
    <h1 class="text-xl font-bold text-tinta">{{ $obrigatoria ? 'Defina uma nova senha' : 'Trocar senha' }}</h1>
    <p class="mt-1 text-sm text-gray-500">
        @if ($obrigatoria)
            Por segurança, troque a senha provisória antes de continuar.
        @else
            Use ao menos 8 caracteres, com letras e números.
        @endif
    </p>

    <form wire:submit="salvar" class="mt-6 space-y-4" novalidate>
        <x-ui.field label="Senha atual" name="senha_atual">
            <x-ui.input-senha wire:model="senha_atual" autocomplete="current-password" autofocus :erro="$errors->has('senha_atual')" />
        </x-ui.field>

        <x-ui.field label="Nova senha" name="nova_senha" hint="Mínimo de 8 caracteres, com letras e números.">
            <x-ui.input-senha wire:model="nova_senha" autocomplete="new-password" :erro="$errors->has('nova_senha')" />
        </x-ui.field>

        <x-ui.field label="Confirmar nova senha" name="nova_senha_confirmation">
            <x-ui.input-senha wire:model="nova_senha_confirmation" autocomplete="new-password" />
        </x-ui.field>

        <button type="submit" class="btn btn-primario w-full" wire:loading.attr="disabled" wire:target="salvar">
            Salvar nova senha
        </button>
    </form>

    @unless ($obrigatoria)
        <p class="mt-4 text-center text-sm"><a href="{{ route('dashboard') }}" wire:navigate class="text-roxo-700 hover:underline">Cancelar</a></p>
    @endunless
</div>
