<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Postos" title="Novo posto" subtitle="Cadastre um posto de combustível." />

    <x-ui.formulario :voltar="route('postos.index')" rotulo="Salvar posto" titulo="Dados do posto">
        @include('livewire.postos._form')
    </x-ui.formulario>
</div>
