{{-- Campos compartilhados entre Create e Edit. Espera $this->form (VeiculoForm), $filiais, $motoristas e $tipos. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Placa" name="form.placa" :required="true" hint="ABC-1234 ou ABC1D23 (Mercosul).">
        <input type="text" wire:model="form.placa" maxlength="8" autofocus style="text-transform: uppercase" @class(['campo', 'campo-erro' => $errors->has('form.placa')])>
    </x-ui.field>

    <x-ui.field label="Tipo" name="form.tipo" :required="true">
        <select wire:model="form.tipo" @class(['campo', 'campo-erro' => $errors->has('form.tipo')])>
            <option value="">Selecione</option>
            @foreach ($tipos as $tipo)
                <option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Filial" name="form.filial_id" :required="true">
        <select wire:model.live="form.filial_id" @class(['campo', 'campo-erro' => $errors->has('form.filial_id')])>
            <option value="">Selecione</option>
            @foreach ($filiais as $filial)
                <option value="{{ $filial->id }}">{{ $filial->nome }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Motorista padrão" name="form.motorista_padrao_id" hint="Opcional. Só aparecem motoristas ativos da filial escolhida.">
        <select wire:model="form.motorista_padrao_id" @disabled($motoristas->isEmpty()) @class(['campo', 'campo-erro' => $errors->has('form.motorista_padrao_id')])>
            <option value="">Nenhum</option>
            @foreach ($motoristas as $motorista)
                <option value="{{ $motorista->id }}">{{ $motorista->nome }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Marca" name="form.marca">
        <input type="text" wire:model="form.marca" maxlength="60" class="campo">
    </x-ui.field>

    <x-ui.field label="Modelo" name="form.modelo">
        <input type="text" wire:model="form.modelo" maxlength="60" class="campo">
    </x-ui.field>

    <x-ui.field label="Ano de fabricação" name="form.ano_fabricacao">
        <input type="text" wire:model="form.ano_fabricacao" inputmode="numeric" maxlength="4" @class(['campo', 'campo-erro' => $errors->has('form.ano_fabricacao')])>
    </x-ui.field>

    <x-ui.field label="RENAVAM" name="form.renavam">
        <input type="text" wire:model="form.renavam" inputmode="numeric" maxlength="11" @class(['campo', 'campo-erro' => $errors->has('form.renavam')])>
    </x-ui.field>

    <x-ui.field label="Hodômetro inicial (km)" name="form.odometro_inicial" hint="Leitura do hodômetro quando o veículo entrou no sistema. Base do cálculo de km rodados e média.">
        <input type="text" wire:model="form.odometro_inicial" inputmode="decimal" placeholder="0,0" @class(['campo', 'campo-erro' => $errors->has('form.odometro_inicial')])>
    </x-ui.field>

    <x-ui.field label="Capacidade do tanque (L)" name="form.capacidade_tanque_l">
        <input type="text" wire:model="form.capacidade_tanque_l" inputmode="decimal" placeholder="0,00" @class(['campo', 'campo-erro' => $errors->has('form.capacidade_tanque_l')])>
    </x-ui.field>

    <x-ui.field label="Consumo de referência (km/l)" name="form.consumo_referencia_km_l" hint="Usado para alertar consumo fora do esperado.">
        <input type="text" wire:model="form.consumo_referencia_km_l" inputmode="decimal" placeholder="0,00" @class(['campo', 'campo-erro' => $errors->has('form.consumo_referencia_km_l')])>
    </x-ui.field>

    <div class="flex items-end">
        <label class="flex items-center gap-2 pb-2 text-sm text-tinta">
            <input type="checkbox" wire:model="form.ativo" class="size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
            Veículo ativo
        </label>
    </div>
</div>
