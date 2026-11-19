<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Tarifas de rota" title="Nova tarifa de rota" subtitle="Defina o frete sugerido para uma rota." />

    <x-ui.formulario :voltar="route('tarifas.index')" rotulo="Salvar tarifa" titulo="Dados da tarifa">
        @include('livewire.tarifas._form')
    </x-ui.formulario>

    <livewire:tarifas.novo-local />
</div>
