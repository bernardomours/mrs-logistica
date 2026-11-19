{{-- Lista de viagens do motorista (portal). Espera $viagens (array do ResumoDoPortal), $prefixo (chave do período) e $vazio. --}}
@php use App\Support\Numero; @endphp

<section class="rounded-2xl border border-borda bg-white shadow-cartao">
    <h2 class="px-4 pt-4 text-base font-semibold text-tinta">Viagens <span class="text-sm font-normal text-gray-500">({{ count($viagens) }})</span></h2>
    @if ($viagens === [])
        <p class="px-4 py-8 text-center text-sm text-gray-500">{{ $vazio }}</p>
    @else
        <ul class="mt-2 divide-y divide-borda">
            @foreach ($viagens as $i => $viagem)
                <li wire:key="viagem-{{ $prefixo }}-{{ $i }}" @class(['space-y-1 px-4 py-3', 'opacity-60' => ! $viagem['concluida']])>
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium text-tinta">{{ $viagem['origem'] }} <span class="text-gray-400">→</span> {{ $viagem['destino'] }}</p>
                        <x-ui.badge :tipo="$viagem['badge']">{{ $viagem['status'] }}</x-ui.badge>
                    </div>
                    <p class="text-sm text-gray-500">{{ $viagem['data']->translatedFormat('D, d/m/Y') }}</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Frete R$ {{ Numero::formatar($viagem['frete']) }}</span>
                        <span class="font-semibold text-roxo-700">Comissão R$ {{ Numero::formatar($viagem['comissao']) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
