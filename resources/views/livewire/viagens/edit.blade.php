<div>
    <x-ui.page-header eyebrow="MRS / Operação / Viagens" title="Editar viagem" :subtitle="$form->viagem?->data_viagem->format('d/m/Y').' · semana '.$form->viagem?->semana()->semana" />

    <x-ui.formulario :voltar="route('viagens.index')" rotulo="Salvar alterações" titulo="Dados da viagem">
        @include('livewire.viagens._form')
    </x-ui.formulario>

    <livewire:tarifas.novo-local />
</div>
