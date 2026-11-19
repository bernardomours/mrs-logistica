{{-- Campos compartilhados entre Create e Edit. Espera $this->form (PostoForm). --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Nome do posto" name="form.nome" :required="true" class="sm:col-span-2">
        <input type="text" wire:model="form.nome" maxlength="150" autofocus @class(['campo', 'campo-erro' => $errors->has('form.nome')])>
    </x-ui.field>

    <x-ui.field label="CNPJ" name="form.cnpj" hint="Opcional. Com ou sem pontuação.">
        <input type="text" wire:model="form.cnpj" inputmode="numeric" maxlength="18" @class(['campo', 'campo-erro' => $errors->has('form.cnpj')])>
    </x-ui.field>

    <x-ui.field label="Cidade" name="form.cidade">
        <input type="text" wire:model="form.cidade" maxlength="100" class="campo">
    </x-ui.field>

    <x-ui.field label="UF" name="form.uf">
        <select wire:model="form.uf" @class(['campo', 'campo-erro' => $errors->has('form.uf')])>
            <option value="">Selecione</option>
            @foreach (\App\Support\Uf::TODAS as $sigla => $nome)
                <option value="{{ $sigla }}">{{ $sigla }} — {{ $nome }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <div class="flex items-end">
        <label class="flex items-center gap-2 pb-2 text-sm text-tinta">
            <input type="checkbox" wire:model="form.ativo" class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
            Posto ativo
        </label>
    </div>
</div>
