@php use App\Support\Numero; @endphp

<div class="space-y-4">
    <x-portal.seletor :rotulo="'Semana '.$resumo->semana->semana.'/'.$resumo->semana->ano"
        :detalhe="$resumo->semana->inicio->format('d/m/Y').' a '.$resumo->semana->fim->format('d/m/Y')"
        :anterior="'irParaSemana(\''.$anterior.'\')'" :proximo="$ehAtual ? null : 'irParaSemana(\''.$proxima.'\')'"
        :atual="$ehAtual ? null : 'irParaSemana(\''.$semanaAtualChave.'\')'" atual-rotulo="Ir para a semana atual" />

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Abastecido</p>
            <p class="mt-1 text-xl font-semibold text-tinta">R$ {{ Numero::formatar($resumo->abastecimento) }}</p>
            <p class="text-xs text-gray-500">{{ count($resumo->abastecimentos) }} {{ count($resumo->abastecimentos) === 1 ? 'abastecimento' : 'abastecimentos' }}</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Litros</p>
            <p class="mt-1 text-xl font-semibold text-tinta">{{ Numero::formatar($resumo->litros, 1) }} L</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Km rodados</p>
            <p class="mt-1 text-xl font-semibold text-tinta">{{ Numero::formatar($resumo->km, 0) }} km</p>
        </div>
        <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
            <p class="text-xs font-medium text-gray-500">Média da semana</p>
            <p class="mt-1 text-xl font-semibold text-tinta">{{ $resumo->mediaSimples !== null ? Numero::formatar($resumo->mediaSimples, 2).' km/l' : '—' }}</p>
        </div>
    </div>

    <section class="rounded-2xl border border-borda bg-white shadow-cartao">
        <h2 class="px-4 pt-4 text-base font-semibold text-tinta">Abastecimentos da semana</h2>

        @if ($resumo->abastecimentos === [])
            <p class="px-4 py-8 text-center text-sm text-gray-500">Nenhum abastecimento nesta semana.</p>
        @else
            {{-- Tela larga (com o menu aberto, a partir de 1280 px): tabela --}}
            <div class="mt-2 hidden overflow-x-auto xl:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Posto</th>
                            <th scope="col" class="text-right">Litros</th>
                            <th scope="col" class="text-right">Preço/L</th>
                            <th scope="col" class="text-right">Valor</th>
                            <th scope="col" class="text-right">Km inicial</th>
                            <th scope="col" class="text-right">Km final</th>
                            <th scope="col" class="text-right">Km rodados</th>
                            <th scope="col" class="text-right">Média</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumo->abastecimentos as $i => $a)
                            <tr wire:key="tabela-abastecimento-{{ $resumo->semana->chave() }}-{{ $i }}">
                                <td class="whitespace-nowrap">{{ $a['data']->format('d/m/Y') }}</td>
                                <td>{{ $a['posto'] ?? '—' }}</td>
                                <td class="text-right whitespace-nowrap">{{ Numero::formatar($a['litros'], 3) }}</td>
                                <td class="text-right whitespace-nowrap">R$ {{ Numero::formatar($a['preco'], 3) }}</td>
                                <td class="text-right font-medium whitespace-nowrap text-tinta">R$ {{ Numero::formatar($a['valor']) }}</td>
                                <td class="text-right whitespace-nowrap">{{ $a['km_inicial'] !== null ? Numero::formatar($a['km_inicial'], 1) : '—' }}</td>
                                <td class="text-right whitespace-nowrap">{{ Numero::formatar($a['odometro'], 1) }}</td>
                                <td class="text-right whitespace-nowrap">{{ $a['km'] !== null ? Numero::formatar($a['km'], 1) : '—' }}</td>
                                <td class="text-right whitespace-nowrap">{{ $a['media'] !== null ? Numero::formatar($a['media'], 2).' km/l' : '—' }}{{ $a['tanque_cheio'] ? '' : ' (parcial)' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-roxo-50/60 font-semibold text-tinta">
                            <td colspan="2">TOTAL</td>
                            <td class="text-right whitespace-nowrap">{{ Numero::formatar($resumo->litros, 3) }}</td>
                            <td></td>
                            <td class="text-right whitespace-nowrap">R$ {{ Numero::formatar($resumo->abastecimento) }}</td>
                            <td></td>
                            <td></td>
                            <td class="text-right whitespace-nowrap">{{ Numero::formatar($resumo->km, 1) }}</td>
                            <td class="text-right whitespace-nowrap">{{ $resumo->mediaSimples !== null ? Numero::formatar($resumo->mediaSimples, 2).' km/l' : '—' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Celular: cartões --}}
            <ul class="mt-2 divide-y divide-borda xl:hidden">
                @foreach ($resumo->abastecimentos as $i => $a)
                    <li wire:key="cartao-abastecimento-{{ $resumo->semana->chave() }}-{{ $i }}" class="space-y-1 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium text-tinta">{{ $a['posto'] ?? 'Posto não informado' }}</p>
                            <span class="font-semibold text-tinta">R$ {{ Numero::formatar($a['valor']) }}</span>
                        </div>
                        <p class="text-sm text-gray-500">{{ $a['data']->translatedFormat('D, d/m/Y') }} · {{ Numero::formatar($a['litros'], 1) }} L · R$ {{ Numero::formatar($a['preco'], 3) }}/L</p>
                        <p class="text-sm text-gray-600">
                            Km {{ $a['km_inicial'] !== null ? Numero::formatar($a['km_inicial'], 1) : '—' }} → {{ Numero::formatar($a['odometro'], 1) }}
                            @if ($a['km'] !== null) · {{ Numero::formatar($a['km'], 1) }} km rodados @endif
                            @if ($a['media'] !== null) · {{ Numero::formatar($a['media'], 2) }} km/l @endif
                            @unless ($a['tanque_cheio']) · parcial @endunless
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="px-1 text-center text-xs text-gray-400">Somente consulta. Para corrigir um lançamento, fale com o escritório.</p>
</div>
