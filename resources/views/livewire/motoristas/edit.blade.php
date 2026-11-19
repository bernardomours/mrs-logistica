<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Motoristas" title="Editar motorista" :subtitle="$form->motorista?->nome" />

    <x-ui.formulario :voltar="route('motoristas.index')" rotulo="Salvar alterações" titulo="Dados do motorista">
        @include('livewire.motoristas._form')
    </x-ui.formulario>
</div>
