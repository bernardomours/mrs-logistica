<div>
    @if ($aberto)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-marinho-950/60 p-0 sm:items-center sm:p-4"
             x-data x-on:keydown.escape.window="$wire.fechar()" role="dialog" aria-modal="true" aria-labelledby="titulo-novo-local">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl" x-on:click.outside="$wire.fechar()">
                <form wire:submit="salvar" novalidate>
                    <div class="flex items-start justify-between gap-3 border-b border-borda px-5 py-4 sm:px-6">
                        <div>
                            <h2 id="titulo-novo-local" class="text-base font-semibold text-tinta">Novo local</h2>
                            <p class="mt-1 text-sm text-gray-500">Cadastre uma origem ou destino que ainda não existe.</p>
                        </div>
                        <button type="button" wire:click="fechar" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Fechar">
                            <x-ui.icon name="x" class="size-5" />
                        </button>
                    </div>

                    <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 sm:px-6">
                        <x-ui.field label="Nome do local" name="form.nome" :required="true" class="sm:col-span-2" hint="Como é reconhecido no dia a dia. Ex.: SÃO SABINO/APODI, NATAL/RN.">
                            <input type="text" wire:model="form.nome" maxlength="150" autofocus @class(['campo', 'campo-erro' => $errors->has('form.nome')])>
                        </x-ui.field>

                        <x-ui.field label="Tipo" name="form.tipo">
                            <select wire:model="form.tipo" @class(['campo', 'campo-erro' => $errors->has('form.tipo')])>
                                <option value="">Não informado</option>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo->value }}">{{ $tipo->rotulo() }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field label="UF" name="form.uf">
                            <select wire:model="form.uf" @class(['campo', 'campo-erro' => $errors->has('form.uf')])>
                                <option value="">Selecione</option>
                                @foreach (\App\Support\Uf::TODAS as $sigla => $nome)
                                    <option value="{{ $sigla }}">{{ $sigla }} — {{ $nome }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <x-ui.field label="Cidade" name="form.cidade" class="sm:col-span-2">
                            <input type="text" wire:model="form.cidade" maxlength="100" class="campo">
                        </x-ui.field>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-borda px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" wire:click="fechar" class="btn btn-secundario">Cancelar</button>
                        <button type="submit" class="btn btn-primario" wire:loading.attr="disabled" wire:target="salvar">
                            <span wire:loading.remove wire:target="salvar">Salvar local</span>
                            <span wire:loading wire:target="salvar">Salvando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
