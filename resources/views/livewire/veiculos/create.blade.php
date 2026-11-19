<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Veículos" title="Novo veículo" subtitle="Cadastre um caminhão ou outro veículo da frota." />

    <x-ui.formulario :voltar="route('veiculos.index')" rotulo="Salvar veículo" titulo="Dados do veículo">
        @include('livewire.veiculos._form')
    </x-ui.formulario>
</div>
