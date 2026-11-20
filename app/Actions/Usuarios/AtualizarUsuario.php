<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\FilialAtual;
use App\Services\GestaoDeUsuarios;
use App\Support\PapeisDoSistema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atualiza dados, papel, filiais e situação de um usuário; opcionalmente redefine a senha.
 * Protege o último administrador ativo e impede o usuário de se desativar ou trocar o próprio papel.
 */
class AtualizarUsuario
{
    public function __construct(private readonly GestaoDeUsuarios $gestao, private readonly FilialAtual $filiais) {}

    /**
     * @param  array{name: string, email: string, cpf: ?string, papel: string, filiais: array<int, int>, filial_padrao_id: ?int, ativo: bool, senha: ?string, deve_trocar_senha: bool}  $dados
     */
    public function handle(User $ator, User $alvo, array $dados): User
    {
        if (! $ator->can('usuarios.editar') || ! $this->gestao->podeGerenciar($ator, $alvo)) {
            throw new AuthorizationException('Você não tem permissão para editar este usuário.');
        }

        $papelAtual = $alvo->getRoleNames()->first();
        $mudouPapel = $papelAtual !== $dados['papel'];
        $ehOProprio = $ator->is($alvo);

        // Administrador pode tudo, inclusive no próprio usuário (a trava do último administrador abaixo continua valendo).
        if ($ehOProprio && ! $ator->hasRole(PapeisDoSistema::ADMIN) && ($mudouPapel || ! $dados['ativo'])) {
            throw ValidationException::withMessages([
                $mudouPapel ? 'papel' : 'ativo' => 'Você não pode alterar o seu próprio papel nem se desativar.',
            ]);
        }

        if ($alvo->ativo && ! $dados['ativo'] && ! $ator->can('usuarios.desativar')) {
            throw new AuthorizationException('Você não tem permissão para desativar usuários.');
        }

        if ($this->gestao->eOUltimoAdministradorAtivo($alvo) && ($dados['papel'] !== PapeisDoSistema::ADMIN || ! $dados['ativo'])) {
            throw ValidationException::withMessages([
                'papel' => 'Este é o único administrador ativo. Cadastre outro administrador antes de alterá-lo.',
            ]);
        }

        $this->gestao->validarVinculos($ator, $dados['papel'], $dados['filiais'], $dados['filial_padrao_id'], $papelAtual);

        return DB::transaction(function () use ($ator, $alvo, $dados, $papelAtual, $mudouPapel, $ehOProprio) {
            $filiaisAntes = $alvo->filiais()->pluck('filiais.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $antes = $alvo->only(['name', 'email', 'cpf', 'ativo', 'deve_trocar_senha', 'filial_padrao_id']);

            $alvo->fill([
                'name' => $dados['name'],
                'email' => mb_strtolower($dados['email']),
                'cpf' => $dados['cpf'],
                'ativo' => $dados['ativo'],
                'deve_trocar_senha' => $dados['deve_trocar_senha'],
                'filial_padrao_id' => $dados['filial_padrao_id'],
            ]);

            $redefiniuSenha = filled($dados['senha'] ?? null);

            if ($redefiniuSenha) {
                $alvo->password = $dados['senha'];
            }

            $alvo->save();

            if ($mudouPapel) {
                $alvo->syncRoles([$dados['papel']]);
            }

            $filiaisDepois = collect($dados['filiais'])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $alvo->filiais()->sync($filiaisDepois);
            $this->filiais->esquecer();

            $mudancas = [];
            foreach ($antes as $campo => $valor) {
                if ($valor != $alvo->{$campo}) {
                    $mudancas[$campo] = ['de' => $valor, 'para' => $alvo->{$campo}];
                }
            }
            if ($mudouPapel) {
                $mudancas['papel'] = ['de' => $papelAtual, 'para' => $dados['papel']];
            }
            if ($filiaisAntes !== $filiaisDepois) {
                $mudancas['filiais'] = ['de' => $filiaisAntes, 'para' => $filiaisDepois];
            }
            if ($redefiniuSenha) {
                $mudancas['senha'] = 'redefinida';
            }

            if (($redefiniuSenha && ! $ehOProprio) || ! $alvo->ativo) {
                $this->gestao->encerrarSessoes($alvo);
            }

            activity('usuarios')->performedOn($alvo)->causedBy($ator)->withProperties(['alteracoes' => $mudancas])->log('Usuário atualizado');

            return $alvo;
        });
    }
}
