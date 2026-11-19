{{-- Campos compartilhados entre Create e Edit. Espera $this->form (AbastecimentoForm), $veiculos, $motoristas, $postos, $combustiveis e $previa. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Veículo" name="form.veiculo_id" :required="true">
        <select wire:model.live="form.veiculo_id" @class(['campo', 'campo-erro' => $errors->has('form.veiculo_id')])>
            <option value="">Selecione</option>
            @foreach ($veiculos as $veiculo)
                <option value="{{ $veiculo->id }}">{{ $veiculo->placa_formatada }} ({{ $veiculo->filial->codigo }})</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Motorista" name="form.motorista_id" :required="true" hint="Só aparecem motoristas da filial do veículo.">
        <select wire:model="form.motorista_id" @disabled($motoristas->isEmpty()) @class(['campo', 'campo-erro' => $errors->has('form.motorista_id')])>
            <option value="">{{ $motoristas->isEmpty() ? 'Escolha o veículo primeiro' : 'Selecione' }}</option>
            @foreach ($motoristas as $motorista)<option value="{{ $motorista->id }}">{{ $motorista->nome }}</option>@endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Data do abastecimento" name="form.data_abastecimento" :required="true">
        <input type="date" wire:model="form.data_abastecimento" @class(['campo', 'campo-erro' => $errors->has('form.data_abastecimento')])>
    </x-ui.field>

    <x-ui.field label="Posto" name="form.posto_id">
        <select wire:model="form.posto_id" @class(['campo', 'campo-erro' => $errors->has('form.posto_id')])>
            <option value="">Não informado</option>
            @foreach ($postos as $posto)<option value="{{ $posto->id }}">{{ $posto->nome }}</option>@endforeach
        </select>
    </x-ui.field>

    {{-- Hodômetro e consumo --}}
    <div class="rounded-xl border border-borda bg-roxo-50/50 p-4 sm:col-span-2">
        <p class="mb-3 text-sm font-medium text-tinta">Hodômetro e consumo</p>
        <div class="grid gap-4 sm:grid-cols-3">
            @if ($previa['precisa_km_inicial'])
                <x-ui.field label="Km inicial" name="form.km_anterior" hint="1º abastecimento deste veículo: informe a leitura anterior (ou cadastre o hodômetro inicial no veículo).">
                    <input type="text" wire:model.live.debounce.400ms="form.km_anterior" inputmode="decimal" placeholder="0,0" @class(['campo', 'campo-erro' => $errors->has('form.km_anterior')])>
                </x-ui.field>
            @else
                <x-ui.field label="Km inicial" name="form.km_anterior" hint="Automático: hodômetro do abastecimento anterior.">
                    <input type="text" value="{{ $previa['referencia'] !== null ? \App\Support\Numero::formatar($previa['referencia'], 1) : '—' }}" class="campo" readonly tabindex="-1">
                </x-ui.field>
            @endif

            <x-ui.field label="Km final (hodômetro)" name="form.odometro_km" :required="true">
                <input type="text" wire:model.live.debounce.400ms="form.odometro_km" inputmode="decimal" placeholder="0,0" @class(['campo', 'campo-erro' => $errors->has('form.odometro_km')])>
            </x-ui.field>

            <div class="text-sm">
                <p class="rotulo">Rodados / média</p>
                <p class="rounded-lg border border-borda bg-white px-3 py-2 text-tinta">
                    {{ $previa['rodados'] !== null ? \App\Support\Numero::formatar($previa['rodados'], 1).' km' : '—' }}
                    <span class="text-gray-400">·</span>
                    {{ $previa['media'] !== null ? \App\Support\Numero::formatar($previa['media'], 2).' km/l' : '—' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Litros, preço e valor: informe 2 e o terceiro é calculado --}}
    <div class="rounded-xl border border-borda bg-roxo-50/50 p-4 sm:col-span-2">
        <p class="mb-1 text-sm font-medium text-tinta">Litros, preço e valor</p>
        <p class="mb-3 text-xs text-gray-500">Informe dois deles; o terceiro é calculado ao sair do campo.</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Litros" name="form.litros">
                <input type="text" wire:model.live.blur="form.litros" inputmode="decimal" placeholder="0,000" @class(['campo', 'campo-erro' => $errors->has('form.litros')])>
            </x-ui.field>
            <x-ui.field label="Preço por litro (R$)" name="form.preco_litro">
                <input type="text" wire:model.live.blur="form.preco_litro" inputmode="decimal" placeholder="0,000" @class(['campo', 'campo-erro' => $errors->has('form.preco_litro')])>
            </x-ui.field>
            <x-ui.field label="Valor total (R$)" name="form.valor_total">
                <input type="text" wire:model.live.blur="form.valor_total" inputmode="decimal" placeholder="0,00" @class(['campo', 'campo-erro' => $errors->has('form.valor_total')])>
            </x-ui.field>
        </div>
    </div>

    <x-ui.field label="Combustível" name="form.combustivel" :required="true">
        <select wire:model="form.combustivel" @class(['campo', 'campo-erro' => $errors->has('form.combustivel')])>
            @foreach ($combustiveis as $chave => $rotulo)<option value="{{ $chave }}">{{ $rotulo }}</option>@endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Nota fiscal" name="form.nota_fiscal">
        <input type="text" wire:model="form.nota_fiscal" maxlength="50" class="campo">
    </x-ui.field>

    <label class="flex items-start gap-2 text-sm text-tinta sm:col-span-2">
        <input type="checkbox" wire:model="form.tanque_cheio" class="mt-0.5 size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
        <span>Tanque cheio
            <span class="block text-xs text-gray-500">A média só é confiável quando o tanque foi completado. Abastecimentos parciais não entram nas médias da semana.</span>
        </span>
    </label>

    <x-ui.field label="Observações" name="form.observacoes" class="sm:col-span-2">
        <textarea wire:model="form.observacoes" rows="3" maxlength="2000" class="campo"></textarea>
    </x-ui.field>
</div>
