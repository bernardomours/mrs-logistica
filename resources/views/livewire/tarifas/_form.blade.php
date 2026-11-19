{{-- Campos compartilhados entre Create e Edit. Espera $this->form (TarifaForm) e $locais. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Origem" name="form.origem_local_id" :required="true">
        <select wire:model="form.origem_local_id" @class(['campo', 'campo-erro' => $errors->has('form.origem_local_id')])>
            <option value="">Selecione</option>
            @foreach ($locais as $local)
                <option value="{{ $local->id }}">{{ $local->nome }}</option>
            @endforeach
        </select>
        @can('create', \App\Models\Local::class)
            <button type="button" wire:click="$dispatch('abrir-novo-local', { alvo: 'origem' })" class="mt-1.5 text-xs font-medium text-roxo-700 hover:underline">+ Cadastrar novo local</button>
        @endcan
    </x-ui.field>

    <x-ui.field label="Destino" name="form.destino_local_id" :required="true">
        <select wire:model="form.destino_local_id" @class(['campo', 'campo-erro' => $errors->has('form.destino_local_id')])>
            <option value="">Selecione</option>
            @foreach ($locais as $local)
                <option value="{{ $local->id }}">{{ $local->nome }}</option>
            @endforeach
        </select>
        @can('create', \App\Models\Local::class)
            <button type="button" wire:click="$dispatch('abrir-novo-local', { alvo: 'destino' })" class="mt-1.5 text-xs font-medium text-roxo-700 hover:underline">+ Cadastrar novo local</button>
        @endcan
    </x-ui.field>

    <x-ui.field label="Valor do frete (R$)" name="form.valor_frete" :required="true" hint="Valor por viagem. Ex.: 4.491,69.">
        <input type="text" wire:model="form.valor_frete" inputmode="decimal" placeholder="0,00" @class(['campo', 'campo-erro' => $errors->has('form.valor_frete')])>
    </x-ui.field>

    <x-ui.field label="Início da vigência" name="form.vigencia_inicio" :required="true">
        <input type="date" wire:model="form.vigencia_inicio" @class(['campo', 'campo-erro' => $errors->has('form.vigencia_inicio')])>
    </x-ui.field>

    <x-ui.field label="Fim da vigência" name="form.vigencia_fim" hint="Deixe em branco se a tarifa não tem data de término.">
        <input type="date" wire:model="form.vigencia_fim" @class(['campo', 'campo-erro' => $errors->has('form.vigencia_fim')])>
    </x-ui.field>

    <x-ui.field label="Observações" name="form.observacoes" class="sm:col-span-2">
        <textarea wire:model="form.observacoes" rows="3" maxlength="2000" class="campo"></textarea>
    </x-ui.field>
</div>
