<div>
    <x-ui.page-header eyebrow="MRS / Administração / Papéis" title="Editar papel" :subtitle="\App\Support\PapeisDoSistema::rotulo($form->papel->name)" />

    <x-ui.formulario :voltar="route('papeis.index')" rotulo="Salvar alterações" titulo="Dados do papel">
        @include('livewire.papeis._form')
    </x-ui.formulario>
</div>
