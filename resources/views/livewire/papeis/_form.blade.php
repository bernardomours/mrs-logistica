{{-- Campos compartilhados entre Create e Edit. Espera $this->form (PapelForm), $grupos, $pode e $ehPadrao. --}}
<div class="space-y-6">
    @if ($ehPadrao)
        <x-ui.alert tipo="info">Este é um papel padrão do sistema: você pode ajustar as permissões, mas o nome é fixo e ele não pode ser excluído.</x-ui.alert>
    @endif

    <x-ui.field label="Nome do papel" name="form.nome" :required="true" class="max-w-md" hint="Use letras, números, espaços, sublinhado ou hífen.">
        <input type="text" wire:model="form.nome" maxlength="60" @readonly($ehPadrao) autofocus @class(['campo', 'campo-erro' => $errors->has('form.nome'), 'bg-gray-50 text-gray-500' => $ehPadrao])>
    </x-ui.field>

    @error('form.permissoes')
        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($grupos as $chave => $grupo)
            <fieldset wire:key="grupo-{{ $chave }}" class="rounded-xl border border-borda p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <legend class="text-sm font-semibold text-tinta">{{ $grupo['titulo'] }}</legend>
                    <span class="flex gap-3 text-xs font-medium text-roxo-700">
                        <button type="button" wire:click="marcarGrupo('{{ $chave }}', true)" class="hover:underline">Marcar todas</button>
                        <button type="button" wire:click="marcarGrupo('{{ $chave }}', false)" class="hover:underline">Limpar</button>
                    </span>
                </div>
                <div class="space-y-1">
                    @foreach ($grupo['permissoes'] as $permissao => $rotulo)
                        @php($concede = $pode($permissao))
                        <label wire:key="permissao-{{ $permissao }}" @class(['flex min-h-9 items-start gap-2 rounded-lg px-2 py-1.5 text-sm', 'text-tinta hover:bg-gray-50' => $concede, 'text-gray-400' => ! $concede])>
                            <input type="checkbox" wire:model="form.permissoes" value="{{ $permissao }}" @disabled(! $concede)
                                   class="mt-0.5 size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
                            <span>
                                {{ $rotulo }}
                                @unless ($concede)<span class="block text-xs">Você não tem esta permissão para conceder.</span>@endunless
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>
</div>
