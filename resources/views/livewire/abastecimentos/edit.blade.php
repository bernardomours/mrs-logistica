<div>
    <x-ui.page-header eyebrow="MRS / Operação / Abastecimentos" title="Editar abastecimento" :subtitle="$form->abastecimento?->data_abastecimento->format('d/m/Y').' · semana '.$form->abastecimento?->semana()->semana" />

    <x-ui.formulario :voltar="route('abastecimentos.index')" rotulo="Salvar alterações" titulo="Dados do abastecimento">
        @include('livewire.abastecimentos._form')
    </x-ui.formulario>
</div>
