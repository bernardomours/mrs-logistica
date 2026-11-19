<?php

namespace App\Services;

use App\Models\Abastecimento;
use App\Models\FechamentoSemanal;
use App\Models\Scopes\FilialScope;
use App\Support\SemanaIso;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

/**
 * RN-11: semana fechada (ou paga) de um motorista trava os lançamentos dele naquela semana.
 * Consultada pelas Policies (para esconder botões) e pelas Actions (defesa em profundidade).
 * O resultado fica em memória durante a requisição; `FechamentoSemanal` o descarta ao salvar/excluir.
 */
class TravaDeSemana
{
    /** @var array<string, bool> */
    private array $fechadas = [];

    public function semanaFechada(int $motoristaId, DateTimeInterface|string $data): bool
    {
        $semana = SemanaIso::deData($data);
        $chave = $motoristaId.'|'.$semana->chave();

        return $this->fechadas[$chave] ??= FechamentoSemanal::withoutGlobalScope(FilialScope::class)
            ->where('motorista_id', $motoristaId)->daSemana($semana)->exists();
    }

    /** Recusa a operação se a semana da data estiver fechada para o motorista. */
    public function garantirAberta(int $motoristaId, DateTimeInterface|string $data, string $campo): void
    {
        if ($this->semanaFechada($motoristaId, $data)) {
            $semana = SemanaIso::deData($data);

            throw ValidationException::withMessages([
                $campo => "A semana {$semana->semana}/{$semana->ano} deste motorista está fechada. Reabra o fechamento para alterar lançamentos.",
            ]);
        }
    }

    /**
     * Inserir, mover ou excluir um abastecimento muda o km inicial e a média do abastecimento seguinte do
     * veículo (cadeia de hodômetro, RN-07). Se esse seguinte está em semana fechada, a operação é recusada.
     */
    public function garantirSucessorLivre(int $veiculoId, string $odometro, ?int $ignorarId = null): void
    {
        $sucessor = Abastecimento::withoutGlobalScope(FilialScope::class)
            ->where('veiculo_id', $veiculoId)->where('odometro_km', '>', $odometro)
            ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
            ->orderBy('odometro_km')->first(['id', 'motorista_id', 'data_abastecimento']);

        if ($sucessor !== null && $this->semanaFechada($sucessor->motorista_id, $sucessor->data_abastecimento)) {
            throw ValidationException::withMessages([
                'odometro_km' => 'Este lançamento alteraria o km e a média de um abastecimento de uma semana já fechada. Reabra esse fechamento primeiro.',
            ]);
        }
    }

    public function esquecer(): void
    {
        $this->fechadas = [];
    }
}
