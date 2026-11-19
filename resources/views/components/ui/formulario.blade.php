{{-- Moldura padrão das páginas Create/Edit: cartão + botões. O componente Livewire precisa ter o método salvar(). --}}
@props(['voltar', 'rotulo' => 'Salvar', 'titulo' => 'Dados do cadastro', 'descricao' => 'Os campos marcados com * são obrigatórios.'])

<form wire:submit="salvar" novalidate>
    <x-ui.card :title="$titulo" :description="$descricao">
        {{ $slot }}
    </x-ui.card>

    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <a href="{{ $voltar }}" wire:navigate class="btn btn-secundario">Cancelar</a>
        <button type="submit" class="btn btn-primario" wire:loading.attr="disabled" wire:target="salvar">
            <span wire:loading.remove wire:target="salvar">{{ $rotulo }}</span>
            <span wire:loading wire:target="salvar">Salvando…</span>
        </button>
    </div>
</form>
