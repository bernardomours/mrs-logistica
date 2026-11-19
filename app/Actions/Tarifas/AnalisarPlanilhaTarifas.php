<?php

namespace App\Actions\Tarifas;

use App\Models\Local;
use App\Models\TarifaRota;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Compara as linhas da planilha com as tarifas vigentes SEM gravar nada (pré-visualização).
 * Também é usada por `ImportarTarifas`, que reanalisa no servidor antes de aplicar.
 *
 * Ações por linha: `criar` (rota sem tarifa vigente), `atualizar` (valor diferente),
 * `sem_alteracao` (valor igual) e `erro`.
 *
 * @phpstan-type Linha array{linha: int, origem: string, destino: string, valor: ?string, valor_original: string}
 */
class AnalisarPlanilhaTarifas
{
    /**
     * @param  array<int, Linha>  $linhas
     * @return array{
     *     linhas: array<int, array<string, mixed>>,
     *     resumo: array{criar: int, atualizar: int, sem_alteracao: int, erro: int, locais_novos: int, a_encerrar: int},
     *     ausentes: array<int, array{id: int, rota: string}>
     * }
     */
    public function handle(array $linhas, ?int $clienteId, string $inicio, bool $encerrarAusentes = false): array
    {
        $inicio = Carbon::parse($inicio)->toDateString();

        $chaves = collect($linhas)->flatMap(fn ($l) => [Texto::chave($l['origem']), Texto::chave($l['destino'])])->filter()->unique()->values();
        $locais = Local::query()->whereIn('nome_chave', $chaves)->get(['id', 'nome', 'nome_chave', 'ativo'])->keyBy('nome_chave');

        $vigentes = $this->vigentes($clienteId, $inicio);
        $porRota = $vigentes->keyBy(fn (TarifaRota $t) => $t->origem->nome_chave.'|'.$t->destino->nome_chave);

        $vistas = [];
        $locaisNovos = [];
        $resultado = [];

        foreach ($linhas as $linha) {
            $origemChave = Texto::chave($linha['origem']);
            $destinoChave = Texto::chave($linha['destino']);
            $rota = $origemChave.'|'.$destinoChave;

            $item = $linha + ['acao' => 'erro', 'valor_atual' => null, 'mensagem' => null, 'locais_novos' => []];

            $erro = match (true) {
                $linha['origem'] === '' => 'Origem em branco.',
                $linha['destino'] === '' => 'Destino em branco.',
                mb_strlen($linha['origem']) > 150 || mb_strlen($linha['destino']) > 150 => 'Nome de origem ou destino com mais de 150 caracteres.',
                $origemChave === $destinoChave => 'Origem e destino são iguais.',
                $linha['valor'] === null => 'Valor inválido: "'.$linha['valor_original'].'".',
                (float) $linha['valor'] <= 0 => 'O valor precisa ser maior que zero.',
                isset($vistas[$rota]) => 'Rota repetida (já informada na linha '.$vistas[$rota].').',
                default => null,
            };

            if ($origemChave !== '' && $destinoChave !== '') {
                $vistas[$rota] ??= $linha['linha'];
            }

            if ($erro !== null) {
                $resultado[] = ['mensagem' => $erro] + $item;

                continue;
            }

            $novos = [];
            foreach ([[$linha['origem'], $origemChave], [$linha['destino'], $destinoChave]] as [$nome, $chave]) {
                if (! $locais->has($chave)) {
                    $novos[$chave] = $nome;
                    $locaisNovos[$chave] = $nome;
                }
            }
            $item['locais_novos'] = array_values($novos);

            $vigente = $porRota->get($rota);

            if ($vigente !== null) {
                $item['valor_atual'] = $vigente->valor_frete;
                $item['acao'] = bccomp($vigente->valor_frete, $linha['valor'], 2) === 0 ? 'sem_alteracao' : 'atualizar';
            } elseif ($locais->has($origemChave) && $locais->has($destinoChave) && $this->temTarifaFutura($locais[$origemChave]->id, $locais[$destinoChave]->id, $clienteId, $inicio)) {
                $item['mensagem'] = 'Já existe uma tarifa futura para esta rota; ajuste a data de vigência ou edite a tarifa.';
            } else {
                $item['acao'] = 'criar';
            }

            $resultado[] = $item;
        }

        $ausentes = $encerrarAusentes ? $this->ausentes($vigentes, $vistas, $inicio) : [];

        return [
            'linhas' => $resultado,
            'resumo' => [
                'criar' => $this->contar($resultado, 'criar'),
                'atualizar' => $this->contar($resultado, 'atualizar'),
                'sem_alteracao' => $this->contar($resultado, 'sem_alteracao'),
                'erro' => $this->contar($resultado, 'erro'),
                'locais_novos' => count($locaisNovos),
                'a_encerrar' => count($ausentes),
            ],
            'ausentes' => $ausentes,
        ];
    }

    /** @return Collection<int, TarifaRota> */
    private function vigentes(?int $clienteId, string $inicio)
    {
        return TarifaRota::query()
            ->vigentesEm($inicio)
            ->when($clienteId === null, fn ($q) => $q->whereNull('cliente_id'), fn ($q) => $q->where('cliente_id', $clienteId))
            ->with(['origem:id,nome,nome_chave', 'destino:id,nome,nome_chave'])
            ->get();
    }

    private function temTarifaFutura(int $origemId, int $destinoId, ?int $clienteId, string $inicio): bool
    {
        return CriarTarifa::existeSobreposicao([
            'origem_local_id' => $origemId,
            'destino_local_id' => $destinoId,
            'cliente_id' => $clienteId,
            'vigencia_inicio' => $inicio,
        ]);
    }

    /**
     * Tarifas vigentes que não aparecem na planilha e podem ser encerradas
     * (só as que começaram antes da nova vigência).
     *
     * @param  Collection<int, TarifaRota>  $vigentes
     * @param  array<string, int>  $vistas
     * @return array<int, array{id: int, rota: string}>
     */
    private function ausentes($vigentes, array $vistas, string $inicio): array
    {
        return $vigentes
            ->filter(fn (TarifaRota $t) => ! isset($vistas[$t->origem->nome_chave.'|'.$t->destino->nome_chave])
                && $t->vigencia_inicio->toDateString() < $inicio)
            ->map(fn (TarifaRota $t) => ['id' => $t->id, 'rota' => $t->origem->nome.' → '.$t->destino->nome])
            ->values()
            ->all();
    }

    /** @param  array<int, array<string, mixed>>  $linhas */
    private function contar(array $linhas, string $acao): int
    {
        return count(array_filter($linhas, fn ($l) => $l['acao'] === $acao));
    }
}
