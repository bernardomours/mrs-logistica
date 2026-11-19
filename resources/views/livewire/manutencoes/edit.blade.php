<div>
    <x-ui.page-header eyebrow="MRS / Operação / Manutenções" title="Editar manutenção" :subtitle="$form->manutencao?->data_manutencao->format('d/m/Y').' · '.$form->manutencao?->veiculo->placa_formatada" />

    <x-ui.formulario :voltar="route('manutencoes.index')" rotulo="Salvar alterações" titulo="Dados da manutenção">
        @include('livewire.manutencoes._form')
    </x-ui.formulario>
</div>
