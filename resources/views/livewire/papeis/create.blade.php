<div>
    <x-ui.page-header eyebrow="MRS / Administração / Papéis" title="Novo papel" subtitle="Dê um nome e marque o que este papel pode fazer." />

    <x-ui.formulario :voltar="route('papeis.index')" rotulo="Salvar papel" titulo="Dados do papel">
        @include('livewire.papeis._form')
    </x-ui.formulario>
</div>
