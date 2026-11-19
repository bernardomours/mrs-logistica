{{-- Ações de uma linha de cadastro: editar + ativar/desativar. Passe os booleans já avaliados pela policy. --}}
@props(['editar' => null, 'podeEditar' => false, 'podeDesativar' => false, 'ativo' => true, 'id', 'nome' => 'registro', 'compacto' => false])

@if ($podeEditar && $editar)
    <a href="{{ $editar }}" wire:navigate @class(['btn', 'btn-link' => ! $compacto, 'btn-secundario' => $compacto])>
        @unless ($compacto)<x-ui.icon name="pencil" class="size-4" />@endunless Editar
    </a>
@endif

@if ($podeDesativar)
    <button type="button" wire:click="alternarAtivo({{ $id }})"
            wire:confirm="{{ ($ativo ? 'Desativar ' : 'Reativar ').$nome.'?' }}"
            @class(['btn', 'btn-link' => ! $compacto, $ativo ? 'text-red-700 hover:bg-red-50' : '' => ! $compacto, 'btn-perigo' => $compacto && $ativo, 'btn-secundario' => $compacto && ! $ativo])>
        @unless ($compacto)<x-ui.icon name="power" class="size-4" />@endunless {{ $ativo ? 'Desativar' : 'Reativar' }}
    </button>
@endif
