@php
    use App\Support\Numero;

    $primeiroNome = explode(' ', trim($nome))[0];
    $situacoes = ['pago' => ['Paga', 'sucesso'], 'fechado' => ['Fechada', 'destaque'], 'em_andamento' => ['Em andamento', 'aviso']];
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">Olá, <strong class="text-tinta">{{ $primeiroNome }}</strong>. Este é o panorama das suas viagens e da sua comissão.</p>

        {{-- Semana ou mês --}}
        <div class="inline-flex rounded-xl border border-borda bg-white p-1 shadow-cartao" role="group" aria-label="Período">
            <button type="button" wire:click="definirVisao('semana')" aria-pressed="{{ $porMes ? 'false' : 'true' }}"
                    @class(['min-h-10 rounded-lg px-4 text-sm font-medium transition', 'bg-roxo-600 text-white' => ! $porMes, 'text-gray-600 hover:bg-gray-100' => $porMes])>Semana</button>
            <button type="button" wire:click="definirVisao('mes')" aria-pressed="{{ $porMes ? 'true' : 'false' }}"
                    @class(['min-h-10 rounded-lg px-4 text-sm font-medium transition', 'bg-roxo-600 text-white' => $porMes, 'text-gray-600 hover:bg-gray-100' => ! $porMes])>Mês</button>
        </div>
    </div>

    @if (! $porMes)
        {{-- ===== Semana ===== --}}
        @php
            $aReceber = match ($resumo->situacao) {
                'pago' => ['rotulo' => 'Comissão recebida', 'texto' => 'Paga em '.$resumo->pagoEm?->format('d/m/Y')],
                'fechado' => ['rotulo' => 'Comissão a receber', 'texto' => 'Semana fechada, aguardando pagamento'],
                default => ['rotulo' => 'Comissão até agora', 'texto' => 'Semana em andamento (valor parcial)'],
            };
            [$situacaoRotulo, $situacaoBadge] = $situacoes[$resumo->situacao];
        @endphp

        <x-portal.seletor :rotulo="'Semana '.$resumo->semana->semana.'/'.$resumo->semana->ano"
            :detalhe="$resumo->semana->inicio->format('d/m/Y').' a '.$resumo->semana->fim->format('d/m/Y')"
            :anterior="'irParaSemana(\''.$anterior.'\')'" :proximo="$ehAtual ? null : 'irParaSemana(\''.$proxima.'\')'"
            :atual="$ehAtual ? null : 'irParaSemana(\''.$semanaAtualChave.'\')'" atual-rotulo="Ir para a semana atual" />

        <div class="rounded-2xl bg-marinho-900 p-5 text-white shadow-cartao">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm text-marinho-200">{{ $aReceber['rotulo'] }}</p>
                <x-ui.badge :tipo="$situacaoBadge">{{ $situacaoRotulo }}</x-ui.badge>
            </div>
            <p class="mt-2 text-4xl font-bold tracking-tight">R$ {{ Numero::formatar($resumo->comissao) }}</p>
            <p class="mt-1 text-sm text-marinho-200">{{ $aReceber['texto'] }}</p>
            @if (bccomp($resumo->pendenteDePagamento, '0', 2) > 0 && $resumo->situacao !== 'fechado')
                <p class="mt-3 border-t border-white/10 pt-3 text-sm text-marinho-200">Aguardando pagamento (semanas fechadas): <strong class="text-white">R$ {{ Numero::formatar($resumo->pendenteDePagamento) }}</strong></p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Frete das viagens</p>
                <p class="mt-1 text-xl font-semibold text-tinta">R$ {{ Numero::formatar($resumo->frete) }}</p>
                <p class="text-xs text-gray-500">{{ $resumo->qtdViagens }} {{ $resumo->qtdViagens === 1 ? 'viagem concluída' : 'viagens concluídas' }}</p>
            </div>
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Abastecido</p>
                <p class="mt-1 text-xl font-semibold text-tinta">R$ {{ Numero::formatar($resumo->abastecimento) }}</p>
                <p class="text-xs text-gray-500">{{ Numero::formatar($resumo->litros, 1) }} litros</p>
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

        @include('livewire.portal._viagens', ['viagens' => $resumo->viagens, 'prefixo' => $resumo->semana->chave(), 'vazio' => 'Nenhuma viagem nesta semana.'])
    @else
        {{-- ===== Mês ===== --}}
        <x-portal.seletor :rotulo="'Mês'" :detalhe="ucfirst($mesResumo->mes->translatedFormat('F \d\e Y'))"
            :anterior="'irParaMes(\''.$mesAnterior.'\')'" :proximo="$ehMesAtual ? null : 'irParaMes(\''.$mesProximo.'\')'"
            :atual="$ehMesAtual ? null : 'irParaMes(\''.$mesAtualChave.'\')'" atual-rotulo="Ir para o mês atual" />

        <div class="rounded-2xl bg-marinho-900 p-5 text-white shadow-cartao">
            <p class="text-sm text-marinho-200">Comissão do mês</p>
            <p class="mt-2 text-4xl font-bold tracking-tight">R$ {{ Numero::formatar($mesResumo->comissao) }}</p>
            <dl class="mt-4 grid gap-2 border-t border-white/10 pt-3 text-sm sm:grid-cols-3">
                <div><dt class="text-marinho-200">Recebida</dt><dd class="font-semibold text-white">R$ {{ Numero::formatar($mesResumo->comissaoRecebida) }}</dd></div>
                <div><dt class="text-marinho-200">A receber (semanas fechadas)</dt><dd class="font-semibold text-white">R$ {{ Numero::formatar($mesResumo->comissaoAReceber) }}</dd></div>
                <div><dt class="text-marinho-200">Em andamento</dt><dd class="font-semibold text-white">R$ {{ Numero::formatar($mesResumo->comissaoEmAndamento) }}</dd></div>
            </dl>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Frete das viagens</p>
                <p class="mt-1 text-xl font-semibold text-tinta">R$ {{ Numero::formatar($mesResumo->frete) }}</p>
                <p class="text-xs text-gray-500">{{ $mesResumo->qtdViagens }} {{ $mesResumo->qtdViagens === 1 ? 'viagem concluída' : 'viagens concluídas' }}</p>
            </div>
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Abastecido</p>
                <p class="mt-1 text-xl font-semibold text-tinta">R$ {{ Numero::formatar($mesResumo->abastecimento) }}</p>
                <p class="text-xs text-gray-500">{{ Numero::formatar($mesResumo->litros, 1) }} litros</p>
            </div>
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Km rodados</p>
                <p class="mt-1 text-xl font-semibold text-tinta">{{ Numero::formatar($mesResumo->km, 0) }} km</p>
            </div>
            <div class="rounded-2xl border border-borda bg-white p-4 shadow-cartao">
                <p class="text-xs font-medium text-gray-500">Média do mês</p>
                <p class="mt-1 text-xl font-semibold text-tinta">{{ $mesResumo->mediaSimples !== null ? Numero::formatar($mesResumo->mediaSimples, 2).' km/l' : '—' }}</p>
            </div>
        </div>

        @if ($mesResumo->semanas !== [])
            <section class="rounded-2xl border border-borda bg-white shadow-cartao">
                <h2 class="px-4 pt-4 text-base font-semibold text-tinta">Por semana</h2>
                <ul class="mt-2 divide-y divide-borda">
                    @foreach ($mesResumo->semanas as $s)
                        @php([$rotuloSituacao, $badgeSituacao] = $situacoes[$s['situacao']])
                        <li wire:key="mes-semana-{{ $s['chave'] }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <p class="font-medium text-tinta">{{ $s['rotulo'] }}</p>
                                <p class="text-xs text-gray-500">{{ $s['qtd'] }} {{ $s['qtd'] === 1 ? 'viagem' : 'viagens' }} · frete R$ {{ Numero::formatar($s['frete']) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-semibold text-roxo-700">R$ {{ Numero::formatar($s['comissao']) }}</span>
                                <x-ui.badge :tipo="$badgeSituacao">{{ $rotuloSituacao }}</x-ui.badge>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('livewire.portal._viagens', ['viagens' => $mesResumo->viagens, 'prefixo' => $mesChave, 'vazio' => 'Nenhuma viagem neste mês.'])
    @endif

    <p class="px-1 text-center text-xs text-gray-400">Somente consulta. Dúvidas sobre valores: fale com o escritório.</p>
</div>
