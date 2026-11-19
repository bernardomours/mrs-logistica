<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Postos" title="Editar posto" :subtitle="$form->posto?->nome" />

    <x-ui.formulario :voltar="route('postos.index')" rotulo="Salvar alterações" titulo="Dados do posto">
        @include('livewire.postos._form')
    </x-ui.formulario>
</div>
