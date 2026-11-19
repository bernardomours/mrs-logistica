{{-- Campos compartilhados entre Create e Edit. Espera $this->form (ViagemForm), $motoristas, $veiculos, $locais, $statusOpcoes. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Data da viagem" name="form.data_viagem" :required="true">
        <input type="date" wire:model.live="form.data_viagem" @class(['campo', 'campo-erro' => $errors->has('form.data_viagem')])>
    </x-ui.field>

    <x-ui.field label="Situação" name="form.status" :required="true">
        <select wire:model="form.status" @class(['campo', 'campo-erro' => $errors->has('form.status')])>
            @foreach ($statusOpcoes as $opcao)
                <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Motorista" name="form.motorista_id" :required="true">
        <select wire:model.live="form.motorista_id" @class(['campo', 'campo-erro' => $errors->has('form.motorista_id')])>
            <option value="">Selecione</option>
            @foreach ($motoristas as $motorista)
                <option value="{{ $motorista->id }}">{{ $motorista->nome }} ({{ $motorista->filial->codigo }})</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Veículo" name="form.veiculo_id" :required="true" hint="Só aparecem veículos da filial do motorista.">
        <select wire:model="form.veiculo_id" @disabled($veiculos->isEmpty()) @class(['campo', 'campo-erro' => $errors->has('form.veiculo_id')])>
            <option value="">{{ $veiculos->isEmpty() ? 'Escolha o motorista primeiro' : 'Selecione' }}</option>
            @foreach ($veiculos as $veiculo)
                <option value="{{ $veiculo->id }}">{{ $veiculo->placa_formatada }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Origem" name="form.origem_local_id" :required="true">
        <select wire:model.live="form.origem_local_id" @class(['campo', 'campo-erro' => $errors->has('form.origem_local_id')])>
            <option value="">Selecione</option>
            @foreach ($locais as $local)<option value="{{ $local->id }}">{{ $local->nome }}</option>@endforeach
        </select>
        @can('create', \App\Models\Local::class)
            <button type="button" wire:click="$dispatch('abrir-novo-local', { alvo: 'origem' })" class="mt-1.5 text-xs font-medium text-roxo-700 hover:underline">+ Cadastrar novo local</button>
        @endcan
    </x-ui.field>

    <x-ui.field label="Destino" name="form.destino_local_id" :required="true">
        <select wire:model.live="form.destino_local_id" @class(['campo', 'campo-erro' => $errors->has('form.destino_local_id')])>
            <option value="">Selecione</option>
            @foreach ($locais as $local)<option value="{{ $local->id }}">{{ $local->nome }}</option>@endforeach
        </select>
        @can('create', \App\Models\Local::class)
            <button type="button" wire:click="$dispatch('abrir-novo-local', { alvo: 'destino' })" class="mt-1.5 text-xs font-medium text-roxo-700 hover:underline">+ Cadastrar novo local</button>
        @endcan
    </x-ui.field>

    @can('viagens.ver_valores')
        <x-ui.field label="Valor do frete (R$)" name="form.valor_frete" :required="true"
            :hint="$freteSugerido ? 'Tarifa vigente para esta rota: R$ '.$freteSugerido.'. Você pode alterar o valor.' : 'Sem tarifa vigente para esta rota/data: informe o valor.'">
            <input type="text" wire:model.live.debounce.400ms="form.valor_frete" inputmode="decimal" placeholder="0,00" @class(['campo', 'campo-erro' => $errors->has('form.valor_frete')])>
        </x-ui.field>
    @endcan

    @can('comissoes.ver')
        <x-ui.field label="Porcentagem da comissão" name="form.comissao_percentual"
            :hint="($this->comissaoPrevista() !== null ? 'Comissão desta viagem: R$ '.\App\Support\Numero::formatar($this->comissaoPrevista()).'. ' : '').(auth()->user()->can('comissoes.editar') ? 'Vem do cadastro do motorista; você pode ajustar.' : 'Vem do cadastro do motorista.')">
            <div class="relative">
                <input type="text" wire:model.live.debounce.400ms="form.comissao_percentual" inputmode="decimal" placeholder="0,00"
                       @disabled(! auth()->user()->can('comissoes.editar')) @class(['campo pr-9', 'campo-erro' => $errors->has('form.comissao_percentual')])>
                <span class="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm text-gray-400">%</span>
            </div>
        </x-ui.field>
    @endcan

    <x-ui.field label="Observações" name="form.observacoes" class="sm:col-span-2">
        <textarea wire:model="form.observacoes" rows="3" maxlength="2000" class="campo"></textarea>
    </x-ui.field>
</div>
