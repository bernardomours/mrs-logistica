<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Tarifas de rota" title="Editar tarifa de rota" subtitle="Altere o valor ou a vigência. Viagens já lançadas não mudam." />

    <x-ui.formulario :voltar="route('tarifas.index')" rotulo="Salvar alterações" titulo="Dados da tarifa">
        @include('livewire.tarifas._form')
    </x-ui.formulario>

    <livewire:tarifas.novo-local />
</div>
