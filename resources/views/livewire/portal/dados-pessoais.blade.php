@php
    use App\Support\Numero;

    $linhas = [
        'Nome' => $dados['nome'],
        'CPF' => $dados['cpf'],
        'Telefone' => $dados['telefone'],
        'Data de admissão' => $dados['data_admissao']?->format('d/m/Y'),
        'Porcentagem da comissão' => Numero::formatar($dados['comissao']).'%',
        'CNH' => collect([$dados['cnh_numero'], $dados['cnh_categoria'] ? 'categoria '.$dados['cnh_categoria'] : null])->filter()->implode(' · '),
    ];
@endphp

<div class="space-y-4">
    <p class="text-sm text-gray-500">Estes são os dados do seu cadastro. Se algo estiver errado, fale com o escritório: só o administrador altera.</p>

    <section class="rounded-2xl border border-borda bg-white shadow-cartao">
        <div class="flex items-start justify-between gap-3 px-4 pt-4">
            <h2 class="text-base font-semibold text-tinta">Meus dados</h2>
            <x-ui.badge :tipo="$dados['ativo'] ? 'sucesso' : 'neutro'">{{ $dados['ativo'] ? 'Cadastro ativo' : 'Inativo' }}</x-ui.badge>
        </div>
        <dl class="mt-3 grid gap-x-6 gap-y-4 px-4 pb-5 sm:grid-cols-2">
            @foreach ($linhas as $rotulo => $valor)
                <div>
                    <dt class="text-xs font-medium text-gray-500">{{ $rotulo }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-tinta">{{ $valor !== null && $valor !== '' ? $valor : '—' }}</dd>
                </div>
            @endforeach
            <div>
                <dt class="text-xs font-medium text-gray-500">Validade da CNH</dt>
                <dd class="mt-0.5 text-sm font-medium text-tinta">
                    @if ($dados['cnh_validade'])
                        {{ $dados['cnh_validade']->format('d/m/Y') }}
                        @if ($dados['cnh_vencida']) <x-ui.badge tipo="erro">Vencida</x-ui.badge> @endif
                    @else — @endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="rounded-2xl border border-borda bg-white shadow-cartao">
        <h2 class="px-4 pt-4 text-base font-semibold text-tinta">Filial</h2>
        <dl class="mt-3 grid gap-x-6 gap-y-4 px-4 pb-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500">Onde você trabalha</dt>
                <dd class="mt-0.5 text-sm font-medium text-tinta">{{ $filial['nome'] }} <span class="text-gray-400">({{ $filial['codigo'] }})</span></dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500">Cidade</dt>
                <dd class="mt-0.5 text-sm font-medium text-tinta">{{ collect([$filial['cidade'], $filial['uf']])->filter()->implode('/') ?: '—' }}</dd>
            </div>
            @if ($filial['telefone'])
                <div>
                    <dt class="text-xs font-medium text-gray-500">Telefone da filial</dt>
                    <dd class="mt-0.5 text-sm font-medium text-tinta">{{ $filial['telefone'] }}</dd>
                </div>
            @endif
        </dl>
    </section>

    <section class="rounded-2xl border border-borda bg-white shadow-cartao">
        <h2 class="px-4 pt-4 text-base font-semibold text-tinta">{{ count($veiculos) === 1 ? 'Meu veículo' : 'Meus veículos' }}</h2>

        @if ($veiculos === [])
            <p class="px-4 py-8 text-center text-sm text-gray-500">Nenhum veículo está ligado ao seu cadastro ainda. Fale com o escritório.</p>
        @else
            @unless ($veiculoDoCadastro)
                <p class="px-4 pt-1 text-xs text-gray-500">Seu cadastro não tem um veículo fixo; este é o último que você usou.</p>
            @endunless
            <div class="mt-3 divide-y divide-borda">
                @foreach ($veiculos as $i => $veiculo)
                    <dl wire:key="veiculo-{{ $i }}" class="grid gap-x-6 gap-y-4 px-4 py-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Placa</dt>
                            <dd class="mt-0.5 text-lg font-semibold tracking-wide text-tinta">{{ $veiculo['placa'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Tipo</dt>
                            <dd class="mt-0.5 text-sm font-medium text-tinta">{{ $veiculo['tipo'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Marca / modelo</dt>
                            <dd class="mt-0.5 text-sm font-medium text-tinta">{{ collect([$veiculo['marca'], $veiculo['modelo']])->filter()->implode(' ') ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Ano de fabricação</dt>
                            <dd class="mt-0.5 text-sm font-medium text-tinta">{{ $veiculo['ano'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Capacidade do tanque</dt>
                            <dd class="mt-0.5 text-sm font-medium text-tinta">{{ $veiculo['tanque'] !== null ? Numero::formatar($veiculo['tanque'], 0).' litros' : '—' }}</dd>
                        </div>
                    </dl>
                @endforeach
            </div>
        @endif
    </section>
</div>
