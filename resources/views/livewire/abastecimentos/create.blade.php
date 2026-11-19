<div>
    <x-ui.page-header eyebrow="MRS / Operação / Abastecimentos" title="Novo abastecimento" subtitle="Informe o km final: o km inicial vem do abastecimento anterior do mesmo veículo." />

    <x-ui.formulario :voltar="route('abastecimentos.index')" rotulo="Lançar abastecimento" titulo="Dados do abastecimento">
        @include('livewire.abastecimentos._form')
    </x-ui.formulario>
</div>
