<div>
    <x-ui.page-header eyebrow="MRS / Cadastros / Tarifas de rota" title="Importar planilha" subtitle="Crie e atualize várias tarifas de uma vez a partir de uma planilha do Excel.">
        <x-slot:actions>
            <a href="{{ route('tarifas.index') }}" wire:navigate class="btn btn-secundario">Voltar às tarifas</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert class="mb-6">
        <p class="font-medium">Como funciona</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5 text-gray-600">
            <li>Coluna <strong>A</strong> = origem, <strong>B</strong> = destino e <strong>C</strong> = valor do frete. A primeira linha pode ser o cabeçalho.</li>
            <li>Rota sem tarifa vigente é <strong>criada</strong>; se o valor mudou, a tarifa antiga é encerrada na véspera e uma <strong>nova</strong> começa na data de vigência (o histórico fica guardado); se o valor é o mesmo, <strong>nada muda</strong> — pode reenviar o mesmo arquivo com segurança.</li>
            <li>Origens e destinos que ainda não existem são <strong>cadastrados automaticamente</strong>.</li>
        </ul>
        <p class="mt-2"><a href="{{ route('tarifas.modelo') }}" class="font-medium text-roxo-700 underline">Baixar planilha-modelo</a></p>
    </x-ui.alert>

    <x-ui.card title="1. Escolha a planilha" description="Arquivos .xlsx, .xls ou .csv de até 5 MB.">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui.field label="Planilha" name="arquivo" :required="true" class="sm:col-span-2">
                <input type="file" wire:model="arquivo" accept=".xlsx,.xls,.csv"
                       class="block w-full rounded-lg border border-borda bg-white text-sm file:mr-4 file:border-0 file:bg-roxo-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-roxo-700 hover:file:bg-roxo-100">
                <div wire:loading wire:target="arquivo" class="mt-1 text-xs text-gray-500">Enviando arquivo…</div>
            </x-ui.field>

            <x-ui.field label="Vigente a partir de" name="vigencia_inicio" :required="true" hint="Data em que os valores novos passam a valer.">
                <input type="date" wire:model.live="vigencia_inicio" @class(['campo', 'campo-erro' => $errors->has('vigencia_inicio')])>
            </x-ui.field>

            <label class="flex items-start gap-2 text-sm text-tinta sm:col-span-2">
                <input type="checkbox" wire:model.live="encerrar_ausentes" class="mt-0.5 size-4 rounded border-borda text-roxo-600 focus:ring-roxo-200">
                <span>Encerrar as tarifas vigentes que <strong>não estão</strong> na planilha
                    <span class="block text-xs text-gray-500">Use quando a planilha é a lista completa e atual. Elas deixam de valer na véspera da data acima; nada é apagado.</span>
                </span>
            </label>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" wire:click="analisar" class="btn btn-primario" wire:loading.attr="disabled" wire:target="analisar,arquivo" @disabled(! $arquivo)>
                <span wire:loading.remove wire:target="analisar">Analisar planilha</span>
                <span wire:loading wire:target="analisar">Analisando…</span>
            </button>
        </div>
    </x-ui.card>

    @if ($analise)
        @php($r = ($analise['resumo'] ?? []) + ['criar' => 0, 'atualizar' => 0, 'sem_alteracao' => 0, 'erro' => 0, 'locais_novos' => 0, 'a_encerrar' => 0])

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-6">
            @foreach ([
                ['Novas', $r['criar'], 'text-emerald-700'],
                ['Atualizadas', $r['atualizar'], 'text-roxo-700'],
                ['Sem alteração', $r['sem_alteracao'], 'text-gray-600'],
                ['Com erro', $r['erro'], $r['erro'] ? 'text-red-700' : 'text-gray-400'],
                ['Locais novos', $r['locais_novos'], 'text-gray-600'],
                ['A encerrar', $r['a_encerrar'], $r['a_encerrar'] ? 'text-amber-700' : 'text-gray-400'],
            ] as [$rotulo, $valor, $cor])
                <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                    <p class="text-xs font-medium text-gray-500">{{ $rotulo }}</p>
                    <p class="mt-2 text-2xl font-semibold {{ $cor }}">{{ $valor }}</p>
                </div>
            @endforeach
        </div>

        <x-ui.card title="2. Confira antes de importar" description="Nada foi gravado ainda. Linhas com erro serão ignoradas." class="mt-6" :flush="false">
            <x-slot:actions>
                <select wire:model.live="mostrar" class="campo !w-auto" aria-label="Filtrar linhas">
                    <option value="mudancas">Só o que muda</option>
                    <option value="erros">Só erros</option>
                    <option value="todas">Todas as linhas</option>
                </select>
            </x-slot:actions>

            @if ($linhasVisiveis->isEmpty())
                <p class="py-6 text-center text-sm text-gray-500">Nenhuma linha neste filtro.</p>
            @else
                <div class="-mx-5 overflow-x-auto sm:-mx-6">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th scope="col">Linha</th>
                                <th scope="col">Rota</th>
                                <th scope="col" class="text-right">Valor</th>
                                <th scope="col">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($linhasVisiveis as $linha)
                                <tr wire:key="linha-{{ $linha['linha'] }}">
                                    <td class="text-gray-500">{{ $linha['linha'] }}</td>
                                    <td class="font-medium text-tinta">
                                        {{ $linha['origem'] ?: '—' }} <span class="text-gray-400">→</span> {{ $linha['destino'] ?: '—' }}
                                        @if ($linha['locais_novos'])
                                            <span class="block text-xs font-normal text-amber-700">Cadastrar: {{ implode(', ', $linha['locais_novos']) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        @if ($linha['valor'])
                                            @if ($linha['valor_atual'] !== null && $linha['acao'] === 'atualizar')
                                                <span class="text-gray-400 line-through">{{ \App\Support\Numero::formatar($linha['valor_atual']) }}</span>
                                            @endif
                                            R$ {{ \App\Support\Numero::formatar($linha['valor']) }}
                                        @else
                                            {{ $linha['valor_original'] ?: '—' }}
                                        @endif
                                    </td>
                                    <td>
                                        @switch($linha['acao'])
                                            @case('criar') <x-ui.badge tipo="sucesso">Nova</x-ui.badge> @break
                                            @case('atualizar') <x-ui.badge tipo="destaque">Atualizar</x-ui.badge> @break
                                            @case('sem_alteracao') <x-ui.badge>Sem alteração</x-ui.badge> @break
                                            @default <x-ui.badge tipo="erro">Erro</x-ui.badge> <span class="ml-1 text-xs text-red-700">{{ $linha['mensagem'] }}</span>
                                        @endswitch
                                        @if ($linha['acao'] !== 'erro' && $linha['mensagem'])
                                            <span class="block text-xs text-amber-700">{{ $linha['mensagem'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($linhasOcultas > 0)
                    <p class="mt-3 text-xs text-gray-500">Mostrando as primeiras 300 linhas; mais {{ $linhasOcultas }} não exibidas (todas são importadas).</p>
                @endif
            @endif

            @if ($analise['ausentes'])
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-medium">{{ count($analise['ausentes']) }} tarifa(s) vigente(s) fora da planilha serão encerradas:</p>
                    <ul class="mt-1 list-inside list-disc text-xs">
                        @foreach (array_slice($analise['ausentes'], 0, 15) as $ausente)
                            <li>{{ $ausente['rota'] }}</li>
                        @endforeach
                        @if (count($analise['ausentes']) > 15)<li>… e mais {{ count($analise['ausentes']) - 15 }}</li>@endif
                    </ul>
                </div>
            @endif
        </x-ui.card>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" wire:click="cancelar" class="btn btn-secundario">Cancelar</button>
            <button type="button" wire:click="confirmar" wire:confirm="Importar a planilha agora?" class="btn btn-primario"
                    wire:loading.attr="disabled" wire:target="confirmar" @disabled($this->totalDeMudancas() === 0)>
                <span wire:loading.remove wire:target="confirmar">Confirmar importação</span>
                <span wire:loading wire:target="confirmar">Importando…</span>
            </button>
        </div>
    @endif
</div>
