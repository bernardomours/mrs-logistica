<div>
    <x-ui.page-header eyebrow="MRS / Cadastros" title="Motoristas" subtitle="Motoristas de cada filial e a porcentagem de comissão de cada um.">
        <x-slot:actions>
            @can('create', \App\Models\Motorista::class)
                <a href="{{ route('motoristas.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Novo motorista
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.filtros placeholder="Nome, CPF ou telefone" descricao="Busque por nome, CPF ou telefone." :tem-filtros="$this->temFiltros" />

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,status,ordenar,limparFiltros">
        @if ($motoristas->isEmpty())
            <x-ui.vazio icone="users" titulo="Nenhum motorista encontrado" :filtrado="$this->temFiltros" dica="Cadastre o primeiro motorista para começar." />
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <x-ui.th-ordenavel coluna="nome" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Motorista</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="cpf" :atual="$ordenarPor" :direcao="$direcao">CPF</x-ui.th-ordenavel>
                            <th scope="col">Filial</th>
                            <x-ui.th-ordenavel coluna="comissao_percentual" :atual="$ordenarPor" :direcao="$direcao">Comissão</x-ui.th-ordenavel>
                            <th scope="col">CNH</th>
                            <x-ui.th-ordenavel coluna="ativo" :atual="$ordenarPor" :direcao="$direcao">Situação</x-ui.th-ordenavel>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($motoristas as $motorista)
                            <tr wire:key="motorista-{{ $motorista->id }}">
                                <td>
                                    <p class="font-medium text-tinta">{{ $motorista->nome }}</p>
                                    @if ($motorista->telefone)<p class="text-xs text-gray-500">{{ $motorista->telefone }}</p>@endif
                                </td>
                                <td class="whitespace-nowrap text-gray-600">{{ \App\Support\Texto::cpf($motorista->cpf) }}</td>
                                <td><x-ui.badge tipo="destaque">{{ $motorista->filial->codigo }}</x-ui.badge></td>
                                <td class="whitespace-nowrap">{{ \App\Support\Numero::formatar($motorista->comissao_percentual) }}%</td>
                                <td class="whitespace-nowrap text-gray-600">
                                    @if ($motorista->cnh_validade)
                                        <x-ui.badge :tipo="$motorista->cnhVencida() ? 'erro' : 'neutro'">{{ $motorista->cnhVencida() ? 'Vencida ' : '' }}{{ $motorista->cnh_validade->format('d/m/Y') }}</x-ui.badge>
                                    @else — @endif
                                </td>
                                <td><x-ui.badge :tipo="$motorista->ativo ? 'sucesso' : 'neutro'">{{ $motorista->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge></td>
                                <td class="text-right whitespace-nowrap">
                                    @can('exportar', [\App\Models\FechamentoSemanal::class, $motorista])
                                        <button type="button" wire:click="$dispatch('abrir-relatorio-semanal', { motoristaId: {{ $motorista->id }} })" class="btn btn-link">Relatório semanal</button>
                                    @endcan
                                    @can('criarAcesso', $motorista)
                                        <button type="button" wire:click="criarAcesso({{ $motorista->id }})" wire:confirm="Criar o acesso ao portal? O motorista entra com o CPF como login e senha." class="btn btn-link">Criar acesso</button>
                                    @elseif ($motorista->user_id)
                                        @can('redefinirSenha', $motorista)
                                            <button type="button" wire:click="redefinirSenha({{ $motorista->id }})" wire:confirm="Voltar a senha de {{ $motorista->nome }} para o CPF?" class="btn btn-link" title="Tem acesso ao portal">Redefinir senha</button>
                                        @else
                                            <x-ui.badge tipo="destaque">Com acesso</x-ui.badge>
                                        @endcan
                                    @endcan
                                    <x-ui.acoes-linha :editar="route('motoristas.edit', $motorista)" :pode-editar="auth()->user()->can('update', $motorista)"
                                        :pode-desativar="auth()->user()->can('desativar', $motorista)" :ativo="$motorista->ativo" :id="$motorista->id" :nome="'o motorista '.$motorista->nome" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda md:hidden">
                @foreach ($motoristas as $motorista)
                    <li wire:key="motorista-card-{{ $motorista->id }}" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-tinta">{{ $motorista->nome }}</p>
                                <p class="text-sm text-gray-500">{{ \App\Support\Texto::cpf($motorista->cpf) }}</p>
                            </div>
                            <x-ui.badge :tipo="$motorista->ativo ? 'sucesso' : 'neutro'">{{ $motorista->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge tipo="destaque">{{ $motorista->filial->codigo }}</x-ui.badge>
                            <x-ui.badge>Comissão {{ \App\Support\Numero::formatar($motorista->comissao_percentual) }}%</x-ui.badge>
                            <div class="ml-auto flex flex-wrap gap-2">
                                @can('exportar', [\App\Models\FechamentoSemanal::class, $motorista])
                                    <button type="button" wire:click="$dispatch('abrir-relatorio-semanal', { motoristaId: {{ $motorista->id }} })" class="btn btn-secundario">Relatório semanal</button>
                                @endcan
                                @can('criarAcesso', $motorista)
                                    <button type="button" wire:click="criarAcesso({{ $motorista->id }})" wire:confirm="Criar o acesso ao portal? O motorista entra com o CPF como login e senha." class="btn btn-secundario">Criar acesso</button>
                                @elseif ($motorista->user_id)
                                    @can('redefinirSenha', $motorista)
                                        <button type="button" wire:click="redefinirSenha({{ $motorista->id }})" wire:confirm="Voltar a senha de {{ $motorista->nome }} para o CPF?" class="btn btn-secundario">Redefinir senha</button>
                                    @endcan
                                @endcan
                                <x-ui.acoes-linha :compacto="true" :editar="route('motoristas.edit', $motorista)" :pode-editar="auth()->user()->can('update', $motorista)"
                                    :pode-desativar="auth()->user()->can('desativar', $motorista)" :ativo="$motorista->ativo" :id="$motorista->id" :nome="'o motorista '.$motorista->nome" />
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($motoristas->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $motoristas->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    <livewire:motoristas.relatorio-semanal />
</div>
