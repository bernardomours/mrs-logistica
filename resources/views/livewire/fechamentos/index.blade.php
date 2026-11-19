<div>
    <x-ui.page-header eyebrow="MRS / Operação" title="Fechamento semanal" subtitle="Acerto de cada motorista por semana (segunda a domingo). Abra a folha para conferir, fechar e registrar o pagamento.">
        <x-slot:actions>
            @can('exportarTodos', \App\Models\FechamentoSemanal::class)
                @if ($resumoGeral['movimento'] > 0)
                    <a href="{{ route('fechamentos.pdf-semana', $semanaIso->chave()) }}" class="btn btn-secundario">
                        <x-ui.icon name="upload" class="size-4 rotate-180" /> PDF de todos os motoristas
                    </a>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Contexto: semana --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-borda bg-white p-4 shadow-cartao">
        <div class="flex items-center gap-2">
            <button type="button" wire:click="irParaSemana('{{ $semanaIso->anterior()->chave() }}')" class="btn btn-secundario" aria-label="Semana anterior">
                <x-ui.icon name="chevron-left" class="size-4" />
            </button>
            <div class="min-w-48 text-center">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Semana {{ $semanaIso->semana }}/{{ $semanaIso->ano }}</p>
                <p class="text-sm font-semibold text-tinta">{{ $semanaIso->inicio->format('d/m/Y') }} a {{ $semanaIso->fim->format('d/m/Y') }}</p>
            </div>
            <button type="button" wire:click="irParaSemana('{{ $semanaIso->proxima()->chave() }}')" class="btn btn-secundario" aria-label="Próxima semana">
                <x-ui.icon name="chevron-right" class="size-4" />
            </button>
        </div>
        <button type="button" wire:click="irParaSemana('{{ \App\Support\SemanaIso::atual()->chave() }}')" class="btn btn-link">Ir para a semana atual</button>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Motoristas com movimento</p>
            <p class="mt-2 text-2xl font-semibold text-tinta">{{ $resumoGeral['movimento'] }}</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Em andamento</p>
            <p class="mt-2 text-2xl font-semibold text-amber-700">{{ $resumoGeral['em_andamento'] }}</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Fechadas (a pagar)</p>
            <p class="mt-2 text-2xl font-semibold text-roxo-700">{{ $resumoGeral['fechadas'] }}</p>
            @can('comissoes.ver')
                <p class="text-xs text-gray-500">R$ {{ \App\Support\Numero::formatar($resumoGeral['a_pagar']) }}</p>
            @endcan
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Pagas</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ $resumoGeral['pagas'] }}</p>
        </div>
    </div>

    <div class="mb-6 grid gap-4 rounded-2xl border border-borda bg-white p-5 shadow-cartao sm:grid-cols-3">
        <x-ui.field label="Motorista" name="busca">
            <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Buscar pelo nome" class="campo">
        </x-ui.field>
        <x-ui.field label="Situação" name="situacao">
            <select wire:model.live="situacao" class="campo">
                <option value="todas">Todas</option>
                <option value="em_andamento">Em andamento</option>
                <option value="fechado">Fechadas</option>
                <option value="pago">Pagas</option>
            </select>
        </x-ui.field>
        @if ($temFiltros)
            <div class="flex items-end"><button type="button" wire:click="limparFiltros" class="btn btn-secundario">Limpar filtros</button></div>
        @endif
    </div>

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="semana,busca,situacao,irParaSemana,limparFiltros">
        @if ($motoristas->isEmpty())
            <x-ui.vazio icone="clipboard" titulo="Nenhum motorista com lançamentos nesta semana" :filtrado="$temFiltros" dica="Lance viagens ou abastecimentos, ou navegue para outra semana." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Motorista</th>
                            <th scope="col" class="text-right">Viagens</th>
                            @can('viagens.ver_valores')<th scope="col" class="text-right">Faturamento</th>@endcan
                            @can('comissoes.ver')<th scope="col" class="text-right">Comissão</th>@endcan
                            <th scope="col" class="text-right">Abastecimento</th>
                            <th scope="col">Situação</th>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($motoristas as $motorista)
                            @php
                                $f = $fechamentos->get($motorista->id);
                                $v = $viagens->get($motorista->id);
                                $a = $abastecimentos->get($motorista->id);
                                $qtd = $f?->qtd_viagens ?? (int) ($v->concluidas ?? 0);
                                $frete = $f?->total_faturamento ?? ($v->frete ?? 0);
                                $comissao = $f?->total_comissao ?? ($v->comissao ?? 0);
                                $combustivel = $f?->total_abastecimento ?? ($a->valor ?? 0);
                                $url = route('fechamentos.folha', [$motorista, $semanaIso->chave()]);
                            @endphp
                            <tr wire:key="fech-{{ $motorista->id }}">
                                <td class="font-medium text-tinta">{{ $motorista->nome }}<span class="block text-xs font-normal text-gray-500">{{ $motorista->filial->codigo }}</span></td>
                                <td class="text-right">{{ $qtd }}@if (($v->pendentes ?? 0) > 0)<span class="block text-xs text-amber-700">{{ $v->pendentes }} pendente(s)</span>@endif</td>
                                @can('viagens.ver_valores')<td class="text-right whitespace-nowrap">R$ {{ \App\Support\Numero::formatar($frete) }}</td>@endcan
                                @can('comissoes.ver')<td class="text-right whitespace-nowrap text-gray-600">R$ {{ \App\Support\Numero::formatar($comissao) }}</td>@endcan
                                <td class="text-right whitespace-nowrap text-gray-600">R$ {{ \App\Support\Numero::formatar($combustivel) }}</td>
                                <td>
                                    @if ($f)
                                        <x-ui.badge :tipo="$f->status->tipoDeBadge()">{{ $f->status->rotulo() }}</x-ui.badge>
                                    @else
                                        <x-ui.badge tipo="aviso">Em andamento</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap"><a href="{{ $url }}" wire:navigate class="btn btn-link">Abrir folha</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($motoristas as $motorista)
                    @php
                        $f = $fechamentos->get($motorista->id);
                        $v = $viagens->get($motorista->id);
                        $a = $abastecimentos->get($motorista->id);
                        $comissao = $f?->total_comissao ?? ($v->comissao ?? 0);
                    @endphp
                    <li wire:key="fech-card-{{ $motorista->id }}" class="space-y-2 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-tinta">{{ $motorista->nome }}</p>
                                <p class="text-sm text-gray-500">{{ $f?->qtd_viagens ?? (int) ($v->concluidas ?? 0) }} viagem(ns) · {{ $motorista->filial->codigo }}</p>
                            </div>
                            @if ($f)
                                <x-ui.badge :tipo="$f->status->tipoDeBadge()">{{ $f->status->rotulo() }}</x-ui.badge>
                            @else
                                <x-ui.badge tipo="aviso">Em andamento</x-ui.badge>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @can('comissoes.ver')<x-ui.badge>Comissão R$ {{ \App\Support\Numero::formatar($comissao) }}</x-ui.badge>@endcan
                            <x-ui.badge>Abast. R$ {{ \App\Support\Numero::formatar($f?->total_abastecimento ?? ($a->valor ?? 0)) }}</x-ui.badge>
                            <a href="{{ route('fechamentos.folha', [$motorista, $semanaIso->chave()]) }}" wire:navigate class="btn btn-secundario ml-auto">Abrir folha</a>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($motoristas->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $motoristas->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
