<div>
    <x-ui.page-header eyebrow="MRS / Administração" title="Usuários" subtitle="Equipe com acesso ao sistema. Motoristas entram pelo cadastro de motoristas (Criar acesso).">
        <x-slot:actions>
            @can('create', \App\Models\User::class)
                <a href="{{ route('usuarios.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Novo usuário
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Filtros" description="Busque por nome, e-mail ou CPF." class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto] lg:items-end">
            <x-ui.field label="Busca" name="busca">
                <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Nome, e-mail ou CPF" class="campo">
            </x-ui.field>

            <x-ui.field label="Papel" name="papel">
                <select wire:model.live="papel" class="campo">
                    <option value="">Todos</option>
                    @foreach ($papeis as $opcao)
                        <option value="{{ $opcao->name }}">{{ \App\Support\PapeisDoSistema::rotulo($opcao->name) }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Situação" name="status">
                <select wire:model.live="status" class="campo">
                    <option value="todos">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </x-ui.field>

            <button type="button" wire:click="limparFiltros" @disabled(! $this->temFiltros) class="btn btn-secundario">Limpar filtros</button>
        </div>
    </x-ui.card>

    <x-ui.card :flush="true" wire:loading.class="opacity-70" wire:target="busca,status,papel,ordenar,limparFiltros">
        @if ($usuarios->isEmpty())
            <x-ui.vazio icone="user-cog" titulo="Nenhum usuário encontrado" :filtrado="$this->temFiltros" dica="Cadastre o primeiro usuário da equipe." />
        @else
            <div class="hidden overflow-x-auto xl:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <x-ui.th-ordenavel coluna="name" :atual="$ordenarPor" :direcao="$direcao" :padrao="true">Usuário</x-ui.th-ordenavel>
                            <th scope="col">Papel</th>
                            <th scope="col">Filiais</th>
                            <x-ui.th-ordenavel coluna="ultimo_login_at" :atual="$ordenarPor" :direcao="$direcao">Último acesso</x-ui.th-ordenavel>
                            <x-ui.th-ordenavel coluna="ativo" :atual="$ordenarPor" :direcao="$direcao">Situação</x-ui.th-ordenavel>
                            <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $usuario)
                            @php($nomesDasFiliais = $usuario->filiais->pluck('codigo')->implode(', '))
                            <tr wire:key="usuario-{{ $usuario->id }}">
                                <td>
                                    <p class="font-medium text-tinta">{{ $usuario->name }}@if ($usuario->is(auth()->user())) <x-ui.badge tipo="destaque">Você</x-ui.badge>@endif</p>
                                    <p class="text-xs text-gray-500">{{ $usuario->email }}</p>
                                </td>
                                <td>
                                    @foreach ($usuario->roles as $papelDoUsuario)
                                        <x-ui.badge tipo="destaque">{{ \App\Support\PapeisDoSistema::rotulo($papelDoUsuario->name) }}</x-ui.badge>
                                    @endforeach
                                </td>
                                <td class="text-gray-600">{{ $usuario->veTodasAsFiliais() ? 'Todas' : ($nomesDasFiliais ?: '—') }}</td>
                                <td class="whitespace-nowrap text-gray-600">{{ $usuario->ultimo_login_at?->format('d/m/Y H:i') ?? 'Nunca entrou' }}</td>
                                <td><x-ui.badge :tipo="$usuario->ativo ? 'sucesso' : 'neutro'">{{ $usuario->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge></td>
                                <td class="text-right whitespace-nowrap">
                                    <x-ui.acoes-linha :editar="route('usuarios.edit', $usuario)" :pode-editar="auth()->user()->can('update', $usuario)"
                                        :pode-desativar="auth()->user()->can('desativar', $usuario)" :ativo="$usuario->ativo" :id="$usuario->id" :nome="'o usuário '.$usuario->name" />
                                    @can('delete', $usuario)
                                        <button type="button" wire:click="excluir({{ $usuario->id }})"
                                                wire:confirm="Excluir o usuário {{ $usuario->name }}? Isso não pode ser desfeito."
                                                class="btn btn-link text-red-700 hover:bg-red-50">
                                            <x-ui.icon name="trash" class="size-4" /> Excluir
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="divide-y divide-borda xl:hidden">
                @foreach ($usuarios as $usuario)
                    <li wire:key="usuario-card-{{ $usuario->id }}" class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-tinta">{{ $usuario->name }}@if ($usuario->is(auth()->user())) <x-ui.badge tipo="destaque">Você</x-ui.badge>@endif</p>
                                <p class="truncate text-sm text-gray-500">{{ $usuario->email }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $usuario->roles->map(fn ($r) => \App\Support\PapeisDoSistema::rotulo($r->name))->implode(', ') }}
                                    · {{ $usuario->veTodasAsFiliais() ? 'Todas as filiais' : ($usuario->filiais->pluck('codigo')->implode(', ') ?: 'Sem filial') }}
                                </p>
                            </div>
                            <x-ui.badge :tipo="$usuario->ativo ? 'sucesso' : 'neutro'">{{ $usuario->ativo ? 'Ativo' : 'Inativo' }}</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <x-ui.acoes-linha :compacto="true" :editar="route('usuarios.edit', $usuario)" :pode-editar="auth()->user()->can('update', $usuario)"
                                :pode-desativar="auth()->user()->can('desativar', $usuario)" :ativo="$usuario->ativo" :id="$usuario->id" :nome="'o usuário '.$usuario->name" />
                            @can('delete', $usuario)
                                <button type="button" wire:click="excluir({{ $usuario->id }})"
                                        wire:confirm="Excluir o usuário {{ $usuario->name }}? Isso não pode ser desfeito."
                                        class="btn btn-perigo">Excluir</button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($usuarios->hasPages())
                <div class="border-t border-borda px-4 py-3">{{ $usuarios->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</div>
