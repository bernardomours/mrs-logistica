<div>
    <x-ui.page-header eyebrow="MRS / Administração" title="Papéis" subtitle="Conjuntos de permissões que você atribui aos usuários. Administrador e motorista são protegidos.">
        <x-slot:actions>
            @can('create', \Spatie\Permission\Models\Role::class)
                <a href="{{ route('papeis.create') }}" wire:navigate class="btn btn-primario">
                    <x-ui.icon name="plus" class="size-4" /> Novo papel
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :flush="true">
        <div class="hidden overflow-x-auto md:block">
            <table class="tabela">
                <thead>
                    <tr>
                        <th scope="col">Papel</th>
                        <th scope="col">Tipo</th>
                        <th scope="col" class="text-right">Permissões</th>
                        <th scope="col" class="text-right">Usuários</th>
                        <th scope="col" class="text-right"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($papeis as $papel)
                        <tr wire:key="papel-{{ $papel->id }}">
                            <td>
                                <p class="font-medium text-tinta">{{ \App\Support\PapeisDoSistema::rotulo($papel->name) }}</p>
                                @if ($descricao = \App\Support\PapeisDoSistema::descricao($papel->name))
                                    <p class="text-xs text-gray-500">{{ $descricao }}</p>
                                @endif
                            </td>
                            <td>
                                <x-ui.badge :tipo="\App\Support\PapeisDoSistema::ehDoSistema($papel->name) ? 'neutro' : 'destaque'">
                                    {{ \App\Support\PapeisDoSistema::ehDoSistema($papel->name) ? 'Padrão' : 'Personalizado' }}
                                </x-ui.badge>
                            </td>
                            <td class="text-right text-gray-600">{{ $papel->permissions_count }}</td>
                            <td class="text-right text-gray-600">{{ $papel->users_count }}</td>
                            <td class="text-right whitespace-nowrap">
                                @can('update', $papel)
                                    <a href="{{ route('papeis.edit', $papel) }}" wire:navigate class="btn btn-link"><x-ui.icon name="pencil" class="size-4" /> Editar</a>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500"><x-ui.icon name="lock" class="size-4" /> Protegido</span>
                                @endcan
                                @can('delete', $papel)
                                    <button type="button" wire:click="excluir({{ $papel->id }})"
                                            wire:confirm="Excluir o papel {{ $papel->name }}? Isso não pode ser desfeito."
                                            @disabled($papel->users_count > 0) title="{{ $papel->users_count > 0 ? 'Há usuários com este papel' : '' }}"
                                            class="btn btn-link text-red-700 hover:bg-red-50 disabled:opacity-40">
                                        <x-ui.icon name="trash" class="size-4" /> Excluir
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="divide-y divide-borda md:hidden">
            @foreach ($papeis as $papel)
                <li wire:key="papel-card-{{ $papel->id }}" class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-tinta">{{ \App\Support\PapeisDoSistema::rotulo($papel->name) }}</p>
                            <p class="text-xs text-gray-500">{{ $papel->permissions_count }} permissões · {{ $papel->users_count }} {{ $papel->users_count === 1 ? 'usuário' : 'usuários' }}</p>
                        </div>
                        <x-ui.badge :tipo="\App\Support\PapeisDoSistema::ehDoSistema($papel->name) ? 'neutro' : 'destaque'">
                            {{ \App\Support\PapeisDoSistema::ehDoSistema($papel->name) ? 'Padrão' : 'Personalizado' }}
                        </x-ui.badge>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        @can('update', $papel)
                            <a href="{{ route('papeis.edit', $papel) }}" wire:navigate class="btn btn-secundario">Editar</a>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-gray-500"><x-ui.icon name="lock" class="size-4" /> Protegido</span>
                        @endcan
                        @can('delete', $papel)
                            <button type="button" wire:click="excluir({{ $papel->id }})" wire:confirm="Excluir o papel {{ $papel->name }}? Isso não pode ser desfeito."
                                    @disabled($papel->users_count > 0) class="btn btn-perigo disabled:opacity-40">Excluir</button>
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</div>
