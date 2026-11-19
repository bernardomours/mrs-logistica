{{-- Campos compartilhados entre Create e Edit. Espera $this->form (FilialForm). --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Nome da filial" name="form.nome" :required="true" class="sm:col-span-2">
        <input type="text" wire:model="form.nome" maxlength="120" autofocus
               @class(['campo', 'campo-erro' => $errors->has('form.nome')])>
    </x-ui.field>

    <x-ui.field label="Código" name="form.codigo" :required="true" hint="Sigla curta, ex.: MOS.">
        <input type="text" wire:model="form.codigo" maxlength="10" style="text-transform: uppercase"
               @class(['campo', 'campo-erro' => $errors->has('form.codigo')])>
    </x-ui.field>

    <x-ui.field label="CNPJ" name="form.cnpj" hint="Opcional. Com ou sem pontuação.">
        <input type="text" wire:model="form.cnpj" inputmode="numeric" maxlength="18"
               @class(['campo', 'campo-erro' => $errors->has('form.cnpj')])>
    </x-ui.field>

    <x-ui.field label="Cidade" name="form.cidade">
        <input type="text" wire:model="form.cidade" maxlength="100"
               @class(['campo', 'campo-erro' => $errors->has('form.cidade')])>
    </x-ui.field>

    <x-ui.field label="UF" name="form.uf">
        <select wire:model="form.uf" @class(['campo', 'campo-erro' => $errors->has('form.uf')])>
            <option value="">Selecione</option>
            @foreach (\App\Support\Uf::TODAS as $sigla => $nome)
                <option value="{{ $sigla }}">{{ $sigla }} — {{ $nome }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Telefone" name="form.telefone">
        <input type="text" wire:model="form.telefone" inputmode="tel" maxlength="20"
               @class(['campo', 'campo-erro' => $errors->has('form.telefone')])>
    </x-ui.field>

    <div class="flex items-end">
        <label class="flex items-center gap-2 pb-2 text-sm text-tinta">
            <input type="checkbox" wire:model="form.ativo" class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
            Filial ativa
        </label>
    </div>
    @error('form.ativo')
        <p class="text-xs font-medium text-red-600 sm:col-span-2">{{ $message }}</p>
    @enderror
</div>
