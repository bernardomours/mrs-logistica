{{-- Campos compartilhados entre Create e Edit. Espera $this->form (MotoristaForm) e $filiais. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Nome completo" name="form.nome" :required="true" class="sm:col-span-2">
        <input type="text" wire:model="form.nome" maxlength="150" autofocus @class(['campo', 'campo-erro' => $errors->has('form.nome')])>
    </x-ui.field>

    <x-ui.field label="CPF" name="form.cpf" :required="true" hint="Com ou sem pontuação.">
        <input type="text" wire:model="form.cpf" inputmode="numeric" maxlength="14" @class(['campo', 'campo-erro' => $errors->has('form.cpf')])>
    </x-ui.field>

    <x-ui.field label="Filial" name="form.filial_id" :required="true">
        <select wire:model="form.filial_id" @class(['campo', 'campo-erro' => $errors->has('form.filial_id')])>
            <option value="">Selecione</option>
            @foreach ($filiais as $filial)
                <option value="{{ $filial->id }}">{{ $filial->nome }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Porcentagem da comissão" name="form.comissao_percentual" :required="true" hint="Percentual sobre o frete bruto de cada viagem. Ex.: 6 ou 6,5.">
        <div class="relative">
            <input type="text" wire:model="form.comissao_percentual" inputmode="decimal" placeholder="0,00" @class(['campo pr-9', 'campo-erro' => $errors->has('form.comissao_percentual')])>
            <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-gray-400">%</span>
        </div>
    </x-ui.field>

    <x-ui.field label="Telefone" name="form.telefone">
        <input type="text" wire:model="form.telefone" inputmode="tel" maxlength="20" @class(['campo', 'campo-erro' => $errors->has('form.telefone')])>
    </x-ui.field>

    <x-ui.field label="Data de admissão" name="form.data_admissao">
        <input type="date" wire:model="form.data_admissao" @class(['campo', 'campo-erro' => $errors->has('form.data_admissao')])>
    </x-ui.field>

    <x-ui.field label="Número da CNH" name="form.cnh_numero">
        <input type="text" wire:model="form.cnh_numero" maxlength="20" @class(['campo', 'campo-erro' => $errors->has('form.cnh_numero')])>
    </x-ui.field>

    <x-ui.field label="Categoria da CNH" name="form.cnh_categoria">
        <select wire:model="form.cnh_categoria" @class(['campo', 'campo-erro' => $errors->has('form.cnh_categoria')])>
            <option value="">Selecione</option>
            @foreach (['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'] as $categoria)
                <option value="{{ $categoria }}">{{ $categoria }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Validade da CNH" name="form.cnh_validade">
        <input type="date" wire:model="form.cnh_validade" @class(['campo', 'campo-erro' => $errors->has('form.cnh_validade')])>
    </x-ui.field>

    <div class="flex items-end">
        <label class="flex items-center gap-2 pb-2 text-sm text-tinta">
            <input type="checkbox" wire:model="form.ativo" class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
            Motorista ativo
        </label>
    </div>

    <x-ui.field label="Observações" name="form.observacoes" class="sm:col-span-2">
        <textarea wire:model="form.observacoes" rows="3" maxlength="2000" class="campo"></textarea>
    </x-ui.field>
</div>
