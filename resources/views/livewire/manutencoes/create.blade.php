<div>
    <x-ui.page-header eyebrow="MRS / Operação / Manutenções" title="Nova manutenção" subtitle="Serviço feito no caminhão. Não altera o hodômetro dos abastecimentos nem o líquido da semana do motorista." />

    <x-ui.formulario :voltar="route('manutencoes.index')" rotulo="Registrar manutenção" titulo="Dados da manutenção">
        @include('livewire.manutencoes._form')
    </x-ui.formulario>
</div>
