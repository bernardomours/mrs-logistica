<?php

namespace App\Actions\Manutencoes;

use App\Models\Manutencao;
use App\Models\User;
use App\Services\TravaDeSemana;

class AtualizarManutencao
{
    public function __construct(
        private readonly RegistrarManutencao $registrar,
        private readonly TravaDeSemana $trava,
    ) {}

    /**
     * Semana fechada trava a manutenção ligada ao motorista, na origem e no destino (RN-20/RN-11).
     *
     * @param  array<string, mixed>  $dados  mesmo formato de `RegistrarManutencao`
     */
    public function handle(Manutencao $manutencao, array $dados, User $usuario): Manutencao
    {
        [$veiculo, $motorista] = $this->registrar->vinculos($dados, $usuario, $manutencao);
        $valores = $this->registrar->valores($dados);
        $campos = $this->registrar->campos($dados);

        if ($manutencao->motorista_id !== null) {
            $this->trava->garantirAberta($manutencao->motorista_id, $manutencao->data_manutencao, 'data_manutencao');
        }

        if ($motorista !== null) {
            $this->trava->garantirAberta($motorista->id, $dados['data_manutencao'], 'data_manutencao');
        }

        $manutencao->fill($valores + $campos + [
            'filial_id' => $veiculo->filial_id,
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista?->id,
        ]);
        $manutencao->updated_by = $usuario->id;
        $manutencao->save();

        return $manutencao;
    }
}
