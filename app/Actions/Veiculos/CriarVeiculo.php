<?php

namespace App\Actions\Veiculos;

use App\Models\Motorista;
use App\Models\Veiculo;
use App\Services\FilialAtual;
use Illuminate\Validation\ValidationException;

class CriarVeiculo
{
    public function __construct(private readonly FilialAtual $filiais) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(array $dados): Veiculo
    {
        self::validarFilialEMotorista($this->filiais, $dados);

        return Veiculo::create($dados);
    }

    /**
     * RN-16/RN-19: o motorista padrão precisa ser da mesma filial do veículo.
     *
     * @param  array<string, mixed>  $dados
     */
    public static function validarFilialEMotorista(FilialAtual $filiais, array $dados): void
    {
        if (! $filiais->podeAcessar((int) $dados['filial_id'])) {
            throw ValidationException::withMessages(['filial_id' => 'Você não tem acesso a esta filial.']);
        }

        if (! empty($dados['motorista_padrao_id'])) {
            $mesmaFilial = Motorista::withoutGlobalScopes()
                ->whereKey($dados['motorista_padrao_id'])
                ->where('filial_id', $dados['filial_id'])
                ->exists();

            if (! $mesmaFilial) {
                throw ValidationException::withMessages([
                    'motorista_padrao_id' => 'O motorista padrão precisa ser da mesma filial do veículo.',
                ]);
            }
        }
    }
}
