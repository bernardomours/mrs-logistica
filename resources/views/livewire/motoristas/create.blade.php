<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Motoristas" title="Novo motorista" subtitle="Cadastre um motorista e a porcentagem da comissão dele." />

    <x-ui.formulario :voltar="route('motoristas.index')" rotulo="Salvar motorista" titulo="Dados do motorista">
        @include('livewire.motoristas._form')
    </x-ui.formulario>
</div>
