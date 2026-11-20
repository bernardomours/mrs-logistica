<div>
    <x-ui.page-header eyebrow="MRS / Administração / Usuários" title="Editar usuário" :subtitle="$form->usuario->name" />

    <x-ui.formulario :voltar="route('usuarios.index')" rotulo="Salvar alterações" titulo="Dados do usuário">
        @include('livewire.usuarios._form')
    </x-ui.formulario>
</div>
