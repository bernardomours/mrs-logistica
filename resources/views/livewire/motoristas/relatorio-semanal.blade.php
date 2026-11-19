<div>
    @if ($aberto && $motorista)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-marinho-950/60 p-0 sm:items-center sm:p-4"
             x-data x-on:keydown.escape.window="$wire.fechar()" role="dialog" aria-modal="true" aria-labelledby="titulo-relatorio-semanal">
            <div class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-md sm:rounded-2xl" x-on:click.outside="$wire.fechar()">
                <div class="flex items-start justify-between gap-3 border-b border-borda px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="titulo-relatorio-semanal" class="text-base font-semibold text-tinta">Relatório semanal</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $motorista->nome }}</p>
                    </div>
                    <button type="button" wire:click="fechar" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Fechar">
                        <x-ui.icon name="x" class="size-5" />
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <x-ui.field label="Semana" name="semana">
                        <select wire:model.live="semana" class="campo">
                            @foreach ($semanas as $opcao)
                                <option value="{{ $opcao['chave'] }}">{{ $opcao['rotulo'] }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    @if ($resumo)
                        @if ($resumo->semLancamentos())
                            <x-ui.alert tipo="aviso">Este motorista não tem viagens nem abastecimentos na semana {{ $semanaIso->semana }}/{{ $semanaIso->ano }}.</x-ui.alert>
                        @else
                            <p class="text-sm text-gray-600">
                                {{ $resumo->qtdViagens }} {{ $resumo->qtdViagens === 1 ? 'viagem concluída' : 'viagens concluídas' }}
                                e {{ $resumo->abastecimentos->count() }} {{ $resumo->abastecimentos->count() === 1 ? 'abastecimento' : 'abastecimentos' }}.
                                Situação:
                                <strong class="text-tinta">{{ $resumo->fechamento ? ($resumo->fechamento->estaPago() ? 'paga' : 'fechada') : 'em andamento' }}</strong>.
                            </p>
                        @endif
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-borda px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" wire:click="fechar" class="btn btn-secundario">Fechar</button>
                    @if ($resumo && ! $resumo->semLancamentos())
                        <a href="{{ route('fechamentos.pdf', [$motorista, $semanaIso->chave()]) }}" class="btn btn-primario">
                            <x-ui.icon name="upload" class="size-4 rotate-180" /> Baixar PDF
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
