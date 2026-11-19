<?php

namespace App\Actions\Tarifas;

use App\Models\Local;
use App\Models\TarifaRota;
use App\Models\User;
use App\Support\Texto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aplica a planilha de tarifas. A análise é refeita aqui no servidor (nunca confia na pré-visualização).
 *
 *  - rota sem tarifa vigente  -> cria a tarifa (e os locais que ainda não existem);
 *  - valor diferente          -> encerra a vigente no dia anterior e cria a nova (mantém o histórico);
 *    se a vigente começou na própria data informada, só troca o valor;
 *  - valor igual              -> não faz nada (reimportar o mesmo arquivo é seguro);
 *  - opcional: encerra as tarifas vigentes que não estão na planilha.
 */
class ImportarTarifas
{
    public function __construct(private readonly AnalisarPlanilhaTarifas $analisador) {}

    /**
     * @param  array<int, array{linha: int, origem: string, destino: string, valor: ?string, valor_original: string}>  $linhas
     * @return array{criadas: int, atualizadas: int, encerradas: int, sem_alteracao: int, erros: int, locais_criados: int}
     */
    public function handle(array $linhas, ?int $clienteId, string $inicio, bool $encerrarAusentes = false, ?User $usuario = null): array
    {
        $inicio = Carbon::parse($inicio)->toDateString();
        $analise = $this->analisador->handle($linhas, $clienteId, $inicio, $encerrarAusentes);

        $resumo = ['criadas' => 0, 'atualizadas' => 0, 'encerradas' => 0, 'sem_alteracao' => $analise['resumo']['sem_alteracao'], 'erros' => $analise['resumo']['erro'], 'locais_criados' => 0];

        DB::transaction(function () use ($analise, $clienteId, $inicio, &$resumo) {
            $locais = [];
            $resolver = function (string $nome) use (&$locais, &$resumo): Local {
                $chave = Texto::chave($nome);

                if (isset($locais[$chave])) {
                    return $locais[$chave];
                }

                $local = Local::query()->where('nome_chave', $chave)->first();

                if ($local === null) {
                    $local = Local::create(['nome' => $nome, 'ativo' => true]);
                    $resumo['locais_criados']++;
                } elseif (! $local->ativo) {
                    $local->update(['ativo' => true]);
                }

                return $locais[$chave] = $local;
            };

            foreach ($analise['linhas'] as $linha) {
                if (! in_array($linha['acao'], ['criar', 'atualizar'], true)) {
                    continue;
                }

                $origem = $resolver($linha['origem']);
                $destino = $resolver($linha['destino']);
                $base = ['cliente_id' => $clienteId, 'origem_local_id' => $origem->id, 'destino_local_id' => $destino->id];

                if ($linha['acao'] === 'criar') {
                    app(CriarTarifa::class)->handle($base + ['valor_frete' => $linha['valor'], 'vigencia_inicio' => $inicio, 'vigencia_fim' => null]);
                    $resumo['criadas']++;

                    continue;
                }

                $vigente = TarifaRota::query()
                    ->vigentesEm($inicio)
                    ->where('origem_local_id', $origem->id)->where('destino_local_id', $destino->id)
                    ->when($clienteId === null, fn ($q) => $q->whereNull('cliente_id'), fn ($q) => $q->where('cliente_id', $clienteId))
                    ->firstOrFail();

                if ($vigente->vigencia_inicio->toDateString() === $inicio) {
                    $vigente->update(['valor_frete' => $linha['valor']]);
                } else {
                    $fimAntigo = $vigente->vigencia_fim?->toDateString();
                    $vigente->update(['vigencia_fim' => Carbon::parse($inicio)->subDay()->toDateString()]);
                    app(CriarTarifa::class)->handle($base + ['valor_frete' => $linha['valor'], 'vigencia_inicio' => $inicio, 'vigencia_fim' => $fimAntigo]);
                }

                $resumo['atualizadas']++;
            }

            if ($analise['ausentes'] !== []) {
                $fim = Carbon::parse($inicio)->subDay()->toDateString();
                $resumo['encerradas'] = TarifaRota::query()->whereKey(array_column($analise['ausentes'], 'id'))->update(['vigencia_fim' => $fim]);
            }
        });

        activity('tarifas')
            ->causedBy($usuario)
            ->withProperties($resumo + ['vigencia_inicio' => $inicio, 'cliente_id' => $clienteId])
            ->log('Importação de planilha de tarifas');

        return $resumo;
    }
}
