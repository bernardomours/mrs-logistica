{{-- Alertas de manutenção preventiva (RN-13). Espera $alertas (saída de AlertasDePreventiva). --}}
@if ($alertas->isNotEmpty())
    <div class="mb-6 space-y-2">
        @foreach ($alertas as $alerta)
            <x-ui.alert :tipo="$alerta['situacao'] === 'vencida' ? 'erro' : 'aviso'" wire:key="alerta-{{ $alerta['veiculo']->id }}">
                <strong>{{ $alerta['veiculo']->placa_formatada }}</strong> — preventiva {{ $alerta['situacao'] === 'vencida' ? 'vencida' : 'se aproximando' }}
                <span class="text-xs opacity-80">(definida em {{ $alerta['manutencao']->data_manutencao->format('d/m/Y') }}: {{ \Illuminate\Support\Str::limit($alerta['manutencao']->descricao, 50) }})</span>
                <span class="block text-xs">{{ implode(' ', $alerta['motivos']) }}</span>
            </x-ui.alert>
        @endforeach
    </div>
@endif
