<div>
    <x-ui.page-header eyebrow="MRS / Administração / Usuários" title="Novo usuário" subtitle="Cadastre alguém da equipe. O acesso é pelo e-mail e pela senha definida aqui." />

    <x-ui.formulario :voltar="route('usuarios.index')" rotulo="Salvar usuário" titulo="Dados do usuário">
        @include('livewire.usuarios._form')
    </x-ui.formulario>
</div>
