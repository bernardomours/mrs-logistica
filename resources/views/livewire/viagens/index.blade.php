<div>
    <x-ui.page-header eyebrow="MRS / Operação" title="Viagens" subtitle="Fretes realizados por motorista e veículo. Só viagens concluídas entram nos totais.">
        <x-slot:actions>
            @can('create', \App\Models\Viagem::class)
                <a href="{{ route('viagens.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Nova viagem
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filtros placeholder="Origem ou destino" descricao="Filtre por período, motorista, veículo ou situação." :tem-filtros="$this->temFiltros">
        <x-slot:filtro>
            <x-ui.field label="Situação" name="status">
                <select wire:model.live="status" class="campo">
                    <option value="todos">Todas</option>
                    @foreach ($statusOpcoes as $opcao)
                        <option value="{{ $opcao->value }}">{{ $opcao->rotulo() }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </x-slot:filtro>
    </x-ui.filtros>

    <div class="-mt-3 mb-6 grid gap-4 rounded-2xl border border-borda bg-white p-5 shadow-cartao sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="De" name="de"><input type="date" wire:model.live="de" class="campo"></x-ui.field>
        <x-ui.field label="Até" name="ate"><input type="date" wire:model.live="ate" class="campo"></x-ui.field>
        <x-ui.field label="Motorista" name="motorista">
            <select wire:model.live="motorista" class="campo">
                <option value="">Todos</option>
                @foreach ($motoristas as $m)<option value="{{ $m->id }}">{{ $m->nome }}</option>@endforeach
            </select>
        </x-ui.field>
        <x-ui.field label="Veículo" name="veiculo">
            <select wire:model.live="veiculo" class="campo">
                <option value="">Todos</option>
                @foreach ($veiculos as $v)<option value="{{ $v->id }}">{{ $v->placa }}</option>@endforeach
            </select>
        </x-ui.field>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Viagens concluídas</p>
            <p class="mt-2 text-2xl font-semibold text-tinta">{{ $totais->quantidade }}</p>
        </div>
        @can('viagens.ver_valores')
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Frete (faturamento)</p>
                <p class="mt-2 text-2xl font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($totais->frete) }}</p>
            </div>
        @endcan
        @can('comissoes.ver')
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Comissão dos motoristas</p>
                <p class="mt-2 text-2xl font-semibold text-roxo-700">R$ {{ \App\Support\Numero::formatar($totais->comissao) }}</p>
            </div>
        @endcan
    </div>

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,status,motorista,veiculo,de,ate,ordenar,limparFiltros">
        @if ($viagens->isEmpty())
            <x-ui.vazio icone="route" titulo="Nenhuma viagem encontrada" :filtrado="$this->temFiltros" dica="Lance a primeira viagem para começar." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <x-ui.th-ordenavel coluna="data_viagem" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Data</x-ui.th-ordenavel>
                            <th scope="col">Rota</th>
                            <th scope="col">Motorista / veículo</th>
                            @can('viagens.ver_valores')
                                <x-ui.th-ordenavel coluna="valor_frete" :atual="$ordenarPor" :direcao="$direcao" class="text-right">Frete</x-ui.th-ordenavel>
                            @endcan
                            @can('comissoes.ver')<th scope="col" class="text-right">Comissão</th>@endcan
                            <x-ui.th-ordenavel coluna="status" :atual="$ordenarPor" :direcao="$direcao">Situação</x-ui.th-ordenavel>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($viagens as $viagem)
                            <tr wire:key="viagem-{{ $viagem->id }}" @class(['opacity-60' => $viagem->estaCancelada()])>
                                <td class="whitespace-nowrap">
                                    <p class="font-medium text-tinta">{{ $viagem->data_viagem->format('d/m/Y') }}</p>
                                    <p class="text-xs text-gray-500">Sem. {{ $viagem->semana()->semana }}</p>
                                </td>
                                <td class="text-tinta">{{ $viagem->origem->nome }} <span class="text-gray-400">→</span> {{ $viagem->destino->nome }}</td>
                                <td class="text-gray-600">{{ $viagem->motorista->nome }}<span class="block text-xs text-gray-500">{{ $viagem->veiculo->placa_formatada }}</span></td>
                                @can('viagens.ver_valores')
                                    <td class="text-right whitespace-nowrap">
                                        R$ {{ \App\Support\Numero::formatar($viagem->valor_frete) }}
                                        @if ($viagem->divergeDaTarifa())
                                            <span class="block text-xs text-amber-700" title="Tarifa: R$ {{ \App\Support\Numero::formatar($viagem->tarifa->valor_frete) }}">≠ tarifa</span>
                                        @endif
                                    </td>
                                @endcan
                                @can('comissoes.ver')
                                    <td class="text-right whitespace-nowrap text-gray-600">R$ {{ \App\Support\Numero::formatar($viagem->comissao_valor) }}<span class="block text-xs text-gray-400">{{ \App\Support\Numero::formatar($viagem->comissao_percentual) }}%</span></td>
                                @endcan
                                <td><x-ui.badge :tipo="$viagem->status->tipoDeBadge()">{{ $viagem->status->rotulo() }}</x-ui.badge></td>
                                <td class="text-right whitespace-nowrap">
                                    @can('update', $viagem)
                                        <a href="{{ route('viagens.edit', $viagem) }}" wire:navigate class="btn btn-link"><x-ui.icon name="pencil" class="size-4" /> Editar</a>
                                    @endcan
                                    @can('cancelar', $viagem)
                                        <button type="button" wire:click="cancelar({{ $viagem->id }})" wire:confirm="Cancelar esta viagem? Ela deixa de entrar nos totais." class="btn btn-link">Cancelar</button>
                                    @endcan
                                    @can('delete', $viagem)
                                        <button type="button" wire:click="excluir({{ $viagem->id }})" wire:confirm="Excluir esta viagem? A ação fica registrada na auditoria." class="btn btn-link text-red-700 hover:bg-red-50">Excluir</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($viagens as $viagem)
                    <li wire:key="viagem-card-{{ $viagem->id }}" @class(['space-y-3 p-4', 'opacity-60' => $viagem->estaCancelada()])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-tinta">{{ $viagem->origem->nome }} <span class="text-gray-400">→</span> {{ $viagem->destino->nome }}</p>
                                <p class="text-sm text-gray-500">{{ $viagem->data_viagem->format('d/m/Y') }} · Sem. {{ $viagem->semana()->semana }}</p>
                                <p class="text-sm text-gray-500">{{ $viagem->motorista->nome }} · {{ $viagem->veiculo->placa_formatada }}</p>
                            </div>
                            <x-ui.badge :tipo="$viagem->status->tipoDeBadge()">{{ $viagem->status->rotulo() }}</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @can('viagens.ver_valores')<span class="text-base font-semibold text-tinta">R$ {{ \App\Support\Numero::formatar($viagem->valor_frete) }}</span>@endcan
                            @can('comissoes.ver')<x-ui.badge>Comissão R$ {{ \App\Support\Numero::formatar($viagem->comissao_valor) }}</x-ui.badge>@endcan
                            <div class="ml-auto flex gap-2">
                                @can('update', $viagem)<a href="{{ route('viagens.edit', $viagem) }}" wire:navigate class="btn btn-secundario">Editar</a>@endcan
                                @can('cancelar', $viagem)<button type="button" wire:click="cancelar({{ $viagem->id }})" wire:confirm="Cancelar esta viagem?" class="btn btn-perigo">Cancelar</button>@endcan
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($viagens->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $viagens->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
