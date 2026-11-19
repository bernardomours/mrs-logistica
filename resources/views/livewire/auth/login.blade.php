<div>
    <h1 class="text-xl font-bold text-tinta">Entrar</h1>
    <p class="mt-1 text-sm text-gray-500">Administrativo: E-mail | Motorista: CPF</p>

    <form wire:submit="entrar" class="mt-6 space-y-4" novalidate>
        <x-ui.field label="E-mail ou CPF" name="login">
            <input type="text" wire:model="login" autocomplete="username" autofocus
                   @class(['campo', 'campo-erro' => $errors->has('login')])>
        </x-ui.field>

        <x-ui.field label="Senha" name="password">
            <x-ui.input-senha wire:model="password" autocomplete="current-password" :erro="$errors->has('password')" />
        </x-ui.field>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" wire:model="lembrar" class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
            Manter conectado
        </label>

        <button type="submit" class="btn btn-primario w-full" wire:loading.attr="disabled" wire:target="entrar">
            <span wire:loading.remove wire:target="entrar">Entrar</span>
            <span wire:loading wire:target="entrar">Entrando…</span>
        </button>
    </form>
</div>
