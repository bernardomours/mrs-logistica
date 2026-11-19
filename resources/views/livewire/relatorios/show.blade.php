@php use App\Services\Relatorios\Tabela; @endphp

<div>
    <x-ui.page-header eyebrow="MRS / Relatórios" :title="$definicao->titulo()" :subtitle="$definicao->descricao()">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <a href="{{ route('relatorios.index') }}" wire:navigate class="btn btn-secundario"><x-ui.icon name="chevron-left" class="size-4" /> Relatórios</a>
                @if ($podeExportar)
                    <a href="{{ route('relatorios.excel', ['relatorio' => $relatorio, 'periodo' => $atalhoAtual, 'de' => $periodoEscolhido->de(), 'ate' => $periodoEscolhido->ate()]) }}" class="btn btn-secundario">
                        <x-ui.icon name="upload" class="size-4 rotate-180" /> Exportar Excel
                    </a>
                @endif
                <button type="button" onclick="window.print()" class="btn btn-secundario">Imprimir</button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 rounded-2xl border border-borda bg-white p-5 shadow-cartao sm:grid-cols-2 lg:grid-cols-4 print:hidden">
        <x-ui.field label="Período" name="periodo">
            <select wire:model.live="periodo" class="campo">
                @foreach ($atalhos as $chave => $rotulo)<option value="{{ $chave }}">{{ $rotulo }}</option>@endforeach
            </select>
        </x-ui.field>
        @if ($atalhoAtual === 'personalizado')
            <x-ui.field label="De" name="de"><input type="date" wire:model.live="de" class="campo"></x-ui.field>
            <x-ui.field label="Até" name="ate"><input type="date" wire:model.live="ate" class="campo"></x-ui.field>
        @endif
    </div>

    <p class="mb-3 text-sm text-gray-500">Período: <strong class="text-tinta">{{ $periodoEscolhido->rotulo() }}</strong> · {{ $linhas->count() }} linha(s)</p>

    @if ($cartoes)
        @php
            $cores = ['receita' => 'text-emerald-700', 'despesa' => 'text-red-700', 'neutro' => 'text-tinta'];
            $barras = ['receita' => 'bg-emerald-500', 'despesa' => 'bg-red-500', 'neutro' => 'bg-roxo-500'];
        @endphp

        <div class="mb-4 grid gap-3 sm:grid-cols-2" wire:loading.class="opacity-70" wire:target="periodo,de,ate">
            @foreach ($cartoes['principais'] as $cartao)
                <div wire:key="cartao-principal-{{ $loop->index }}" class="relative overflow-hidden rounded-2xl border border-borda bg-white p-5 shadow-cartao">
                    <span class="absolute inset-y-0 left-0 w-1.5 {{ $barras[$cartao['tom']] ?? $barras['neutro'] }}" aria-hidden="true"></span>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">{{ $cartao['rotulo'] }}</p>
                    <p class="mt-2 text-3xl font-semibold {{ $cores[$cartao['tom']] ?? $cores['neutro'] }}">R$ {{ \App\Support\Numero::formatar($cartao['valor']) }}</p>
                    @isset($cartao['detalhe'])<p class="mt-1 text-xs text-gray-500">{{ $cartao['detalhe'] }}</p>@endisset
                </div>
            @endforeach
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4" wire:loading.class="opacity-70" wire:target="periodo,de,ate">
            @foreach ($cartoes['detalhes'] as $cartao)
                <div wire:key="cartao-detalhe-{{ $loop->index }}" class="rounded-xl border border-borda bg-white p-4 shadow-cartao">
                    <p class="text-xs font-medium text-gray-500">{{ $cartao['rotulo'] }}</p>
                    <p class="mt-1.5 text-lg font-semibold {{ $cores[$cartao['tom']] ?? $cores['neutro'] }}">R$ {{ \App\Support\Numero::formatar($cartao['valor']) }}</p>
                    @isset($cartao['detalhe'])<p class="mt-0.5 text-xs text-gray-400">{{ $cartao['detalhe'] }}</p>@endisset
                </div>
            @endforeach
        </div>
    @endif

    @if ($detalhes)
        @php
            $coresTitulo = ['receita' => 'text-emerald-700', 'despesa' => 'text-red-700'];
            $coresTipo = ['Abastecimento' => 'text-amber-700', 'Manutenção' => 'text-sky-700', 'Comissão' => 'text-violet-700'];
        @endphp

        <div class="mb-6 grid items-start gap-4 lg:grid-cols-2" wire:loading.class="opacity-70" wire:target="periodo,de,ate">
            @foreach ($detalhes as $lado)
                <x-ui.card :flush="true" wire:key="detalhe-{{ $loop->index }}">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-borda px-4 py-3">
                        <div>
                            <h2 class="text-sm font-semibold {{ $coresTitulo[$lado['tom']] ?? 'text-tinta' }}">{{ $lado['titulo'] }}</h2>
                            <p class="text-xs text-gray-500">{{ $lado['quantidade'] }} {{ $lado['quantidade'] === 1 ? 'lançamento' : 'lançamentos' }}</p>
                        </div>
                        <p class="text-base font-semibold {{ $coresTitulo[$lado['tom']] ?? 'text-tinta' }}">R$ {{ \App\Support\Numero::formatar($lado['total']) }}</p>
                    </div>

                    @if ($lado['linhas']->isEmpty())
                        <p class="px-4 py-8 text-center text-sm text-gray-500">{{ $lado['vazio'] }}</p>
                    @else
                        <div class="max-h-[34rem] overflow-auto">
                            <table class="tabela min-w-full text-xs">
                                <thead class="sticky top-0 z-10 [&_th]:bg-gray-50">
                                    <tr>
                                        @foreach ($lado['colunas'] as $coluna)
                                            <th scope="col" @class(['text-right' => Tabela::ehNumero($coluna['tipo'])])>{{ $coluna['rotulo'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lado['linhas'] as $linha)
                                        <tr wire:key="detalhe-{{ $loop->parent->index }}-{{ $loop->index }}">
                                            @foreach ($lado['colunas'] as $coluna)
                                                <td @class([
                                                    'text-right whitespace-nowrap font-medium text-tinta' => Tabela::ehNumero($coluna['tipo']),
                                                    'whitespace-nowrap' => in_array($coluna['chave'], ['data', 'filial', 'tipo'], true),
                                                    ($coresTipo[$linha['tipo'] ?? ''] ?? '').' font-medium' => $coluna['chave'] === 'tipo',
                                                ])>{{ Tabela::formatar($coluna['tipo'], $linha[$coluna['chave']] ?? null) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($lado['truncado'])
                            <p class="border-t border-borda bg-amber-50 px-4 py-2 text-xs text-amber-900">Mostrando os lançamentos mais recentes; o total acima considera todos. Reduza o período para ver os demais.</p>
                        @endif
                    @endif
                </x-ui.card>
            @endforeach
        </div>

        <h2 class="mb-2 text-sm font-semibold text-tinta">Resumo por filial</h2>
    @endif

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="periodo,de,ate">
        @if ($linhas->isEmpty())
            <x-ui.vazio icone="chart" titulo="Nada para mostrar neste período" dica="Tente outro período ou outra filial no seletor do menu." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            @foreach ($colunas as $coluna)
                                <th scope="col" @class(['text-right' => Tabela::ehNumero($coluna['tipo'])])>{{ $coluna['rotulo'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $i => $linha)
                            <tr wire:key="linha-{{ $i }}">
                                @foreach ($colunas as $coluna)
                                    <td @class(['text-right whitespace-nowrap' => Tabela::ehNumero($coluna['tipo']), 'font-medium text-tinta' => $loop->first])>{{ Tabela::formatar($coluna['tipo'], $linha[$coluna['chave']] ?? null) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($totais)
                        <tfoot>
                            <tr class="bg-roxo-50/60 font-semibold text-tinta">
                                @foreach ($colunas as $coluna)
                                    <td @class(['text-right whitespace-nowrap' => Tabela::ehNumero($coluna['tipo'])])>
                                        @if ($loop->first)TOTAL @else {{ $totais[$coluna['chave']] !== null ? Tabela::formatar($coluna['tipo'], $totais[$coluna['chave']]) : '' }} @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($linhas as $i => $linha)
                    <li wire:key="cartao-{{ $i }}" class="space-y-1 p-4">
                        @foreach ($colunas as $coluna)
                            @if ($loop->first)
                                <p class="font-medium text-tinta">{{ Tabela::formatar($coluna['tipo'], $linha[$coluna['chave']] ?? null) }}</p>
                            @else
                                <p class="flex justify-between gap-3 text-sm"><span class="text-gray-500">{{ $coluna['rotulo'] }}</span><span class="text-right text-tinta">{{ Tabela::formatar($coluna['tipo'], $linha[$coluna['chave']] ?? null) }}</span></p>
                            @endif
                        @endforeach
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
