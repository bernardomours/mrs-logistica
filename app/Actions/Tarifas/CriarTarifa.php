<?php

namespace App\Actions\Tarifas;

use App\Models\TarifaRota;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CriarTarifa
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(array $dados): TarifaRota
    {
        self::validarVigencia($dados);

        return TarifaRota::create($dados);
    }

    /**
     * Sem sobreposição de vigência para o mesmo (cliente, origem, destino) — CLAUDE.md §5.2.
     *
     * @param  array<string, mixed>  $dados
     */
    public static function validarVigencia(array $dados, ?TarifaRota $atual = null): void
    {
        if (self::existeSobreposicao($dados, $atual)) {
            throw ValidationException::withMessages([
                'vigencia_inicio' => 'Já existe uma tarifa vigente neste período para esta rota e cliente.',
            ]);
        }
    }

    /**
     * Já existe tarifa da mesma rota e cliente cujo período cruza o informado?
     *
     * @param  array<string, mixed>  $dados
     */
    public static function existeSobreposicao(array $dados, ?TarifaRota $atual = null): bool
    {
        $inicio = Carbon::parse($dados['vigencia_inicio'])->toDateString();
        $fim = ! empty($dados['vigencia_fim']) ? Carbon::parse($dados['vigencia_fim'])->toDateString() : null;

        return TarifaRota::query()
            ->where('origem_local_id', $dados['origem_local_id'])
            ->where('destino_local_id', $dados['destino_local_id'])
            ->when(
                empty($dados['cliente_id']),
                fn ($q) => $q->whereNull('cliente_id'),
                fn ($q) => $q->where('cliente_id', $dados['cliente_id'])
            )
            ->when($atual !== null, fn ($q) => $q->whereKeyNot($atual->getKey()))
            ->when($fim !== null, fn ($q) => $q->whereDate('vigencia_inicio', '<=', $fim))
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>=', $inicio))
            ->exists();
    }
}
