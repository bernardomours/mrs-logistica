<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Veículos" title="Editar veículo" :subtitle="$form->veiculo?->placa_formatada" />

    <x-ui.formulario :voltar="route('veiculos.index')" rotulo="Salvar alterações" titulo="Dados do veículo">
        @include('livewire.veiculos._form')
    </x-ui.formulario>
</div>
