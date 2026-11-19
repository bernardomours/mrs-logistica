@php
    $icones = [
        'consumo-por-veiculo' => 'fuel', 'faturamento-por-rota' => 'route', 'comissoes' => 'users',
        'custo-de-manutencao' => 'wrench', 'receitas-e-despesas' => 'chart', 'inconsistencias' => 'alert',
    ];
@endphp

<div>
    <x-ui.page-header eyebrow="MRS / Relatórios" title="Relatórios" subtitle="Escolha um relatório, ajuste o período e, se quiser, exporte para o Excel." />

    @if ($relatorios->isEmpty())
        <x-ui.card>
            <x-ui.vazio icone="chart" titulo="Nenhum relatório disponível para o seu perfil" />
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($relatorios as $chave => $relatorio)
                <a href="{{ route('relatorios.show', $chave) }}" wire:navigate wire:key="relatorio-{{ $chave }}"
                   class="group rounded-2xl border border-borda bg-white p-5 shadow-cartao transition hover:border-roxo-200 hover:shadow-md">
                    <span class="grid size-10 place-items-center rounded-xl bg-roxo-50 text-roxo-700 group-hover:bg-roxo-600 group-hover:text-white">
                        <x-ui.icon :name="$icones[$chave] ?? 'chart'" class="size-5" />
                    </span>
                    <h2 class="mt-4 text-base font-semibold text-tinta">{{ $relatorio->titulo() }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $relatorio->descricao() }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
