{{-- Campos compartilhados entre Create e Edit. Espera $this->form (ManutencaoForm), $veiculos, $motoristas, $tipos, $total e $aviso. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Veículo" name="form.veiculo_id" :required="true">
        <select wire:model.live="form.veiculo_id" @class(['campo', 'campo-erro' => $errors->has('form.veiculo_id')])>
            <option value="">Selecione</option>
            @foreach ($veiculos as $veiculo)
                <option value="{{ $veiculo->id }}">{{ $veiculo->placa_formatada }} ({{ $veiculo->filial->codigo }})</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Motorista" name="form.motorista_id" hint="Opcional. Só aparecem motoristas da filial do veículo.">
        <select wire:model="form.motorista_id" @disabled($motoristas->isEmpty()) @class(['campo', 'campo-erro' => $errors->has('form.motorista_id')])>
            <option value="">{{ $motoristas->isEmpty() ? 'Escolha o veículo primeiro' : 'Não informado' }}</option>
            @foreach ($motoristas as $motorista)<option value="{{ $motorista->id }}">{{ $motorista->nome }}</option>@endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Data da manutenção" name="form.data_manutencao" :required="true">
        <input type="date" wire:model="form.data_manutencao" @class(['campo', 'campo-erro' => $errors->has('form.data_manutencao')])>
    </x-ui.field>

    <x-ui.field label="Tipo" name="form.tipo" :required="true">
        <select wire:model="form.tipo" @class(['campo', 'campo-erro' => $errors->has('form.tipo')])>
            @foreach ($tipos as $tipo)<option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>@endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="O que foi feito" name="form.descricao" :required="true" class="sm:col-span-2">
        <textarea wire:model="form.descricao" rows="3" maxlength="2000" placeholder="Ex.: troca de óleo, filtros e correia" @class(['campo', 'campo-erro' => $errors->has('form.descricao')])></textarea>
    </x-ui.field>

    <x-ui.field label="Oficina" name="form.oficina">
        <input type="text" wire:model="form.oficina" maxlength="150" class="campo">
    </x-ui.field>

    <x-ui.field label="Hodômetro (km)" name="form.odometro_km" hint="Só referência: não entra na cadeia de abastecimentos.">
        <input type="text" wire:model.live.debounce.500ms="form.odometro_km" inputmode="decimal" placeholder="0,0" @class(['campo', 'campo-erro' => $errors->has('form.odometro_km')])>
    </x-ui.field>

    @if ($aviso)
        <x-ui.alert tipo="aviso" class="sm:col-span-2">{{ $aviso }}</x-ui.alert>
    @endif

    <div class="rounded-xl border border-borda bg-roxo-50/50 p-4 sm:col-span-2">
        <p class="mb-3 text-sm font-medium text-tinta">Valores</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Peças (R$)" name="form.valor_pecas">
                <input type="text" wire:model.live.debounce.400ms="form.valor_pecas" inputmode="decimal" placeholder="0,00" @class(['campo', 'campo-erro' => $errors->has('form.valor_pecas')])>
            </x-ui.field>
            <x-ui.field label="Mão de obra (R$)" name="form.valor_mao_obra">
                <input type="text" wire:model.live.debounce.400ms="form.valor_mao_obra" inputmode="decimal" placeholder="0,00" @class(['campo', 'campo-erro' => $errors->has('form.valor_mao_obra')])>
            </x-ui.field>
            <div class="text-sm">
                <p class="rotulo">Total</p>
                <p class="rounded-lg border border-borda bg-white px-3 py-2 font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($total) }}</p>
            </div>
        </div>
    </div>

    <x-ui.field label="Nota fiscal" name="form.nota_fiscal">
        <input type="text" wire:model="form.nota_fiscal" maxlength="50" class="campo">
    </x-ui.field>

    <div class="rounded-xl border border-borda p-4 sm:col-span-2">
        <p class="mb-1 text-sm font-medium text-tinta">Próxima manutenção (opcional)</p>
        <p class="mb-3 text-xs text-gray-500">Gera um alerta quando o hodômetro chegar perto do km ou a data se aproximar.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="Na quilometragem" name="form.proxima_km">
                <input type="text" wire:model="form.proxima_km" inputmode="decimal" placeholder="0,0" @class(['campo', 'campo-erro' => $errors->has('form.proxima_km')])>
            </x-ui.field>
            <x-ui.field label="Na data" name="form.proxima_data">
                <input type="date" wire:model="form.proxima_data" @class(['campo', 'campo-erro' => $errors->has('form.proxima_data')])>
            </x-ui.field>
        </div>
    </div>

    <x-ui.field label="Observações" name="form.observacoes" class="sm:col-span-2">
        <textarea wire:model="form.observacoes" rows="3" maxlength="2000" class="campo"></textarea>
    </x-ui.field>
</div>
