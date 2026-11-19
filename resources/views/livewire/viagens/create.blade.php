<div>
    <x-ui.page-header eyebrow="MRS / Operação / Viagens" title="Nova viagem" subtitle="O frete vem da tarifa de rota e a comissão do cadastro do motorista." />

    <x-ui.formulario :voltar="route('viagens.index')" rotulo="Lançar viagem" titulo="Dados da viagem">
        @include('livewire.viagens._form')
    </x-ui.formulario>

    <livewire:tarifas.novo-local />
</div>
