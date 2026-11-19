<div>
    <x-ui.page-header
        eyebrow="MRS / Cadastros / Filiais"
        title="Nova filial"
        subtitle="Cadastre uma unidade da empresa."
    />

    <form wire:submit="salvar" novalidate>
        <x-ui.card title="Dados da filial" description="Os campos marcados com * são obrigatórios.">
            @include('livewire.filiais._form')
        </x-ui.card>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('filiais.index') }}" wire:navigate class="btn btn-secundario">Cancelar</a>
            <button type="submit" class="btn btn-primario" wire:loading.attr="disabled" wire:target="salvar">
                <span wire:loading.remove wire:target="salvar">Salvar filial</span>
                <span wire:loading wire:target="salvar">Salvando…</span>
            </button>
        </div>
    </form>
</div>
