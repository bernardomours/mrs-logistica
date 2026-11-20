<?php

namespace Tests\Feature\Administracao;

use App\Actions\Usuarios\AtualizarUsuario;
use App\Actions\Usuarios\ExcluirUsuario;
use App\Livewire\Usuarios\Create;
use App\Livewire\Usuarios\Edit;
use App\Livewire\Usuarios\Index;
use App\Models\Filial;
use App\Models\User;
use App\Models\Viagem;
use App\Services\GestaoDeUsuarios;
use Database\Seeders\PapeisEPermissoesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Tela de Usuários: criar, editar, ativar/desativar e excluir, com as proteções de administrador e filial. */
class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private Filial $filialA;

    private Filial $filialB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filialA = Filial::factory()->create(['nome' => 'Mossoró', 'codigo' => 'MOS']);
        $this->filialB = Filial::factory()->create(['nome' => 'Fortaleza', 'codigo' => 'FOR']);
    }

    /** Usuário com um papel personalizado que só tem as permissões dadas. */
    private function usuarioComPermissoes(array $permissoes, array $filiais = []): User
    {
        $this->semearPapeis();

        $papel = Role::create(['name' => 'rh-'.count(Role::all()), 'guard_name' => 'web']);
        $papel->givePermissionTo($permissoes);

        $usuario = User::factory()->create(['ativo' => true, 'deve_trocar_senha' => false]);
        $usuario->assignRole($papel);
        $usuario->filiais()->sync(array_map(fn (Filial $f) => $f->id, $filiais));

        return $usuario;
    }

    /** Papel personalizado com todas as permissões, menos as dadas (e menos ver todas as filiais e gerenciar papéis). */
    private function usuarioComTudoMenos(array $menos, array $filiais): User
    {
        $permissoes = array_diff(PapeisEPermissoesSeeder::permissoes(), $menos, ['filiais.ver_todas', 'papeis.gerenciar']);

        return $this->usuarioComPermissoes(array_values($permissoes), $filiais);
    }

    private function gerente(array $filiais): User
    {
        return $this->usuarioComTudoMenos([], $filiais);
    }

    private function dadosValidos(array $sobrescrever = []): array
    {
        return $sobrescrever + [
            'name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'cpf' => '', 'papel' => 'operacional',
            'filiais' => [(string) $this->filialA->id], 'filial_padrao_id' => (string) $this->filialA->id,
            'ativo' => true, 'senha' => 'Senha1234', 'deve_trocar_senha' => true,
        ];
    }

    private function preencher($componente, array $dados)
    {
        foreach ($dados as $campo => $valor) {
            $componente->set("form.{$campo}", $valor);
        }

        return $componente;
    }

    public function test_lista_mostra_a_equipe_e_esconde_usuarios_de_motorista(): void
    {
        $admin = $this->usuarioComPapel('admin');
        User::factory()->create(['name' => 'Beatriz Operadora'])->assignRole('operacional');
        User::factory()->create(['name' => 'Carlos Motorista'])->assignRole('motorista');

        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk()
            ->assertSee('Beatriz Operadora')->assertDontSee('Carlos Motorista')->assertSee('Novo usuário');
    }

    public function test_quem_nao_tem_permissao_nao_acessa(): void
    {
        $consulta = $this->usuarioComPapel('consulta');

        $this->actingAs($consulta)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($consulta)->get(route('usuarios.create'))->assertForbidden();
        $this->actingAs($consulta)->get(route('papeis.index'))->assertForbidden();
    }

    public function test_admin_cria_usuario_com_papel_filiais_e_senha_com_hash(): void
    {
        $admin = $this->usuarioComPapel('admin');

        $componente = Livewire::actingAs($admin)->test(Create::class);
        $this->preencher($componente, $this->dadosValidos(['cpf' => '529.982.247-25']))
            ->call('salvar')->assertHasNoErrors()->assertRedirect(route('usuarios.index'));

        $novo = User::where('email', 'ana@exemplo.com')->firstOrFail();
        $this->assertSame('Ana Souza', $novo->name);
        $this->assertSame('52998224725', $novo->cpf);
        $this->assertTrue($novo->hasRole('operacional'));
        $this->assertSame([$this->filialA->id], $novo->filiais()->pluck('filiais.id')->all());
        $this->assertSame($this->filialA->id, $novo->filial_padrao_id);
        $this->assertTrue($novo->ativo);
        $this->assertTrue($novo->deve_trocar_senha);
        $this->assertTrue(Hash::check('Senha1234', $novo->password));

        $registro = Activity::where('log_name', 'usuarios')->where('description', 'Usuário criado')->firstOrFail();
        $this->assertSame($admin->id, $registro->causer_id);
        $this->assertStringNotContainsString('Senha1234', json_encode($registro->properties));
    }

    public function test_validacoes_do_formulario(): void
    {
        $admin = $this->usuarioComPapel('admin');
        User::factory()->create(['email' => 'repetido@exemplo.com', 'cpf' => '52998224725']);

        $componente = Livewire::actingAs($admin)->test(Create::class);
        $this->preencher($componente, $this->dadosValidos(['email' => 'repetido@exemplo.com', 'cpf' => '529.982.247-25', 'senha' => 'curta']))
            ->call('salvar')->assertHasErrors(['form.email', 'form.cpf', 'form.senha']);

        $componente = Livewire::actingAs($admin)->test(Create::class);
        $this->preencher($componente, $this->dadosValidos(['name' => '', 'papel' => '', 'senha' => '']))
            ->call('salvar')->assertHasErrors(['form.name', 'form.papel', 'form.senha']);

        $componente = Livewire::actingAs($admin)->test(Create::class);
        $this->preencher($componente, $this->dadosValidos(['cpf' => '111.111.111-11']))
            ->call('salvar')->assertHasErrors(['form.cpf']);
    }

    public function test_filial_padrao_precisa_estar_entre_as_filiais_do_usuario(): void
    {
        $admin = $this->usuarioComPapel('admin');

        $componente = Livewire::actingAs($admin)->test(Create::class);
        $this->preencher($componente, $this->dadosValidos(['filial_padrao_id' => (string) $this->filialB->id]))
            ->call('salvar')->assertHasErrors(['form.filial_padrao_id']);

        $this->assertDatabaseMissing('users', ['email' => 'ana@exemplo.com']);
    }

    public function test_papel_de_motorista_nao_pode_ser_atribuido_pela_tela(): void
    {
        $admin = $this->usuarioComPapel('admin');

        $componente = Livewire::actingAs($admin)->test(Create::class);
        $this->preencher($componente, $this->dadosValidos(['papel' => 'motorista']))
            ->call('salvar')->assertHasErrors(['form.papel']);

        $this->assertDatabaseMissing('users', ['email' => 'ana@exemplo.com']);
    }

    public function test_quem_nao_e_admin_nao_concede_papel_com_mais_poder_que_o_seu(): void
    {
        $gerente = $this->gerente([$this->filialA]);

        foreach (['admin', 'gestor'] as $papel) {
            $componente = Livewire::actingAs($gerente)->test(Create::class);
            $this->preencher($componente, $this->dadosValidos(['papel' => $papel]))
                ->call('salvar')->assertHasErrors(['form.papel']);
        }

        $this->assertDatabaseMissing('users', ['email' => 'ana@exemplo.com']);
    }

    public function test_quem_nao_e_admin_so_vincula_filiais_que_acessa(): void
    {
        $gerente = $this->gerente([$this->filialA]);

        $componente = Livewire::actingAs($gerente)->test(Create::class);
        $this->preencher($componente, $this->dadosValidos(['papel' => 'consulta', 'filiais' => [(string) $this->filialB->id], 'filial_padrao_id' => '']))
            ->call('salvar')->assertHasErrors(['form.filiais']);
    }

    public function test_gerente_so_enxerga_e_edita_usuarios_das_suas_filiais(): void
    {
        $gerente = $this->gerente([$this->filialA]);
        $daA = $this->usuarioComPapel('consulta', [$this->filialA], ['name' => 'Usuario da A']);
        $daB = $this->usuarioComPapel('consulta', [$this->filialB], ['name' => 'Usuario da B']);
        $admin = $this->usuarioComPapel('admin', [], ['name' => 'Chefe Admin']);

        $this->actingAs($gerente)->get(route('usuarios.index'))->assertOk()
            ->assertSee('Usuario da A')->assertDontSee('Usuario da B')->assertDontSee('Chefe Admin');

        $this->actingAs($gerente)->get(route('usuarios.edit', $daA))->assertOk();
        $this->actingAs($gerente)->get(route('usuarios.edit', $daB))->assertNotFound();
        $this->actingAs($gerente)->get(route('usuarios.edit', $admin))->assertNotFound();
    }

    public function test_edicao_altera_papel_filiais_e_registra_a_mudanca_sem_senha(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA], ['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'Original123']);

        $componente = Livewire::actingAs($admin)->test(Edit::class, ['usuario' => $alvo])
            ->assertSet('form.papel', 'consulta');
        $this->preencher($componente, ['papel' => 'operacional', 'filiais' => [(string) $this->filialA->id, (string) $this->filialB->id], 'name' => 'Ana Maria'])
            ->call('salvar')->assertHasNoErrors()->assertRedirect(route('usuarios.index'));

        $alvo->refresh();
        $this->assertSame('Ana Maria', $alvo->name);
        $this->assertSame(['operacional'], $alvo->getRoleNames()->all());
        $this->assertEqualsCanonicalizing([$this->filialA->id, $this->filialB->id], $alvo->filiais()->pluck('filiais.id')->all());
        $this->assertTrue(Hash::check('Original123', $alvo->password), 'senha em branco mantém a atual');

        $registro = Activity::where('description', 'Usuário atualizado')->firstOrFail();
        $this->assertSame('consulta', $registro->properties['alteracoes']['papel']['de']);
        $this->assertSame('operacional', $registro->properties['alteracoes']['papel']['para']);
    }

    public function test_redefinir_a_senha_pelo_formulario(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA], ['password' => 'Original123']);

        $componente = Livewire::actingAs($admin)->test(Edit::class, ['usuario' => $alvo]);
        $this->preencher($componente, ['senha' => 'NovaSenha99', 'deve_trocar_senha' => true])->call('salvar')->assertHasNoErrors();

        $alvo->refresh();
        $this->assertTrue(Hash::check('NovaSenha99', $alvo->password));
        $this->assertTrue($alvo->deve_trocar_senha);

        $registro = Activity::where('description', 'Usuário atualizado')->firstOrFail();
        $this->assertSame('redefinida', $registro->properties['alteracoes']['senha']);
        $this->assertStringNotContainsString('NovaSenha99', json_encode($registro->properties));
    }

    public function test_quem_nao_e_admin_nao_altera_o_proprio_papel_nem_se_desativa(): void
    {
        $gerente = $this->gerente([$this->filialA]);
        $papelAntes = $gerente->getRoleNames()->first();

        $componente = Livewire::actingAs($gerente)->test(Edit::class, ['usuario' => $gerente]);
        $this->preencher($componente, ['papel' => 'consulta'])->call('salvar')->assertHasErrors(['form.papel']);

        $componente = Livewire::actingAs($gerente)->test(Edit::class, ['usuario' => $gerente]);
        $this->preencher($componente, ['ativo' => false])->call('salvar')->assertHasErrors(['form.ativo']);

        $this->assertSame($papelAntes, $gerente->fresh()->getRoleNames()->first());
        $this->assertTrue($gerente->fresh()->ativo);
    }

    public function test_admin_pode_mudar_o_proprio_papel_havendo_outro_admin_ativo(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $this->usuarioComPapel('admin');

        $componente = Livewire::actingAs($admin)->test(Edit::class, ['usuario' => $admin])
            ->assertSee('Administrador');
        $this->preencher($componente, ['papel' => 'consulta'])
            ->call('salvar')->assertHasNoErrors()->assertRedirect(route('dashboard'));

        $this->assertTrue($admin->fresh()->hasRole('consulta'));
        $this->assertFalse($admin->fresh()->hasRole('admin'));
    }

    public function test_admin_unico_nao_se_rebaixa_nem_se_desativa_pela_edicao(): void
    {
        $admin = $this->usuarioComPapel('admin');

        $componente = Livewire::actingAs($admin)->test(Edit::class, ['usuario' => $admin]);
        $this->preencher($componente, ['papel' => 'consulta'])->call('salvar')->assertHasErrors(['form.papel']);

        $componente = Livewire::actingAs($admin)->test(Edit::class, ['usuario' => $admin]);
        $this->preencher($componente, ['ativo' => false])->call('salvar')->assertHasErrors(['form.papel']);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
        $this->assertTrue($admin->fresh()->ativo);
    }

    public function test_gerente_nao_edita_nem_desativa_administrador(): void
    {
        $gerente = $this->gerente([$this->filialA]);
        $admin = $this->usuarioComPapel('admin');

        $this->actingAs($gerente);
        $this->assertFalse($gerente->can('update', $admin));
        $this->assertFalse($gerente->can('desativar', $admin));
        $this->assertFalse($gerente->can('delete', $admin));

        $this->expectException(AuthorizationException::class);
        app(AtualizarUsuario::class)->handle($gerente, $admin, $this->dadosValidos(['papel' => 'admin']) + []);
    }

    public function test_usuario_de_motorista_nao_e_gerenciado_pela_tela(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $motorista = $this->usuarioComPapel('motorista');

        $this->assertFalse($admin->can('update', $motorista));
        $this->assertFalse($admin->can('delete', $motorista));
        $this->actingAs($admin)->get(route('usuarios.edit', $motorista))->assertNotFound();
    }

    public function test_desativar_e_reativar_pela_lista(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA]);

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $alvo->id);
        $this->assertFalse($alvo->fresh()->ativo);

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $alvo->id);
        $this->assertTrue($alvo->fresh()->ativo);

        $this->assertSame(2, Activity::where('log_name', 'usuarios')->whereIn('description', ['Usuário desativado', 'Usuário reativado'])->count());
    }

    public function test_quem_nao_e_admin_nao_se_desativa(): void
    {
        $gerente = $this->gerente([$this->filialA]);

        Livewire::actingAs($gerente)->test(Index::class)->call('alternarAtivo', $gerente->id)->assertForbidden();

        $this->assertTrue($gerente->fresh()->ativo);
    }

    public function test_admin_pode_se_desativar_havendo_outro_admin_mas_nao_sendo_o_unico(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $admin->id)->assertDispatched('toast');
        $this->assertTrue($admin->fresh()->ativo, 'único administrador ativo não se desativa');

        $this->usuarioComPapel('admin');
        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $admin->id);
        $this->assertFalse($admin->fresh()->ativo);
    }

    public function test_desativar_exige_a_permissao_propria(): void
    {
        $editor = $this->usuarioComTudoMenos(['usuarios.desativar', 'usuarios.excluir'], [$this->filialA]);
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA]);

        Livewire::actingAs($editor)->test(Index::class)->call('alternarAtivo', $alvo->id)->assertForbidden();

        $componente = Livewire::actingAs($editor)->test(Edit::class, ['usuario' => $alvo]);
        $this->preencher($componente, ['ativo' => false])->call('salvar')->assertForbidden();
        $this->assertTrue($alvo->fresh()->ativo);
    }

    public function test_ultimo_administrador_ativo_e_protegido(): void
    {
        $gestao = app(GestaoDeUsuarios::class);
        $unico = $this->usuarioComPapel('admin');

        $this->assertTrue($gestao->eOUltimoAdministradorAtivo($unico));

        $outro = $this->usuarioComPapel('admin');
        $this->assertFalse($gestao->eOUltimoAdministradorAtivo($unico));

        $outro->update(['ativo' => false]);
        $this->assertTrue($gestao->eOUltimoAdministradorAtivo($unico));
        $this->assertFalse($gestao->eOUltimoAdministradorAtivo($outro), 'inativo não conta');
    }

    public function test_exclui_usuario_sem_historico(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA], ['name' => 'Cadastro Errado']);

        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $alvo->id);

        $this->assertDatabaseMissing('users', ['id' => $alvo->id]);
        $this->assertDatabaseMissing('filial_user', ['user_id' => $alvo->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $alvo->id, 'model_type' => User::class]);
        $this->assertTrue(Activity::where('description', 'Usuário excluído')->where('causer_id', $admin->id)->exists());
    }

    public function test_nao_exclui_usuario_com_historico(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('operacional', [$this->filialA]);
        Viagem::factory()->create(['created_by' => $alvo->id, 'filial_id' => $this->filialA->id]);

        $this->assertTrue(app(GestaoDeUsuarios::class)->temHistorico($alvo));

        $this->expectException(ValidationException::class);

        try {
            app(ExcluirUsuario::class)->handle($admin, $alvo);
        } finally {
            $this->assertDatabaseHas('users', ['id' => $alvo->id]);
        }
    }

    public function test_lista_avisa_quando_a_exclusao_e_recusada_por_historico(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('operacional', [$this->filialA]);
        Viagem::factory()->create(['created_by' => $alvo->id, 'filial_id' => $this->filialA->id]);

        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $alvo->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('users', ['id' => $alvo->id]);
    }

    public function test_usuario_que_ja_agiu_no_sistema_tem_historico_pela_auditoria(): void
    {
        $alvo = $this->usuarioComPapel('operacional');
        $this->assertFalse(app(GestaoDeUsuarios::class)->temHistorico($alvo));

        activity('teste')->causedBy($alvo)->log('fez algo');

        $this->assertTrue(app(GestaoDeUsuarios::class)->temHistorico($alvo));
    }

    public function test_nao_exclui_a_si_mesmo_nem_sem_permissao(): void
    {
        $gerente = $this->gerente([$this->filialA]);
        Livewire::actingAs($gerente)->test(Index::class)->call('excluir', $gerente->id)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $gerente->id]);

        $editor = $this->usuarioComTudoMenos(['usuarios.desativar', 'usuarios.excluir'], [$this->filialA]);
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA]);
        Livewire::actingAs($editor)->test(Index::class)->call('excluir', $alvo->id)->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $alvo->id]);
    }

    public function test_admin_pode_se_excluir_havendo_outro_admin_mas_nao_sendo_o_unico(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $admin->id)->assertDispatched('toast');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        $this->usuarioComPapel('admin');
        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $admin->id);
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
    }

    public function test_acoes_de_excluir_so_aparecem_para_quem_pode(): void
    {
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA], ['name' => 'Alvo Visivel']);

        Livewire::actingAs($this->usuarioComPapel('admin'))->test(Index::class)->assertSee('Alvo Visivel')->assertSee('Excluir');
        Livewire::actingAs($this->usuarioComPermissoes(['usuarios.ver'], [$this->filialA]))->test(Index::class)
            ->assertSee('Alvo Visivel')->assertDontSee('Excluir')->assertDontSee('Novo usuário');
        $this->assertNotNull($alvo);
    }

    public function test_filtros_de_busca_papel_e_situacao(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $this->usuarioComPapel('operacional', [], ['name' => 'Zelia Operacional', 'email' => 'zelia@exemplo.com']);
        $this->usuarioComPapel('consulta', [], ['name' => 'Caio Consulta', 'ativo' => false]);

        Livewire::actingAs($admin)->test(Index::class)
            ->set('busca', 'zelia')->assertSee('Zelia Operacional')->assertDontSee('Caio Consulta')
            ->call('limparFiltros')
            ->set('papel', 'consulta')->assertSee('Caio Consulta')->assertDontSee('Zelia Operacional')
            ->call('limparFiltros')
            ->set('status', 'inativos')->assertSee('Caio Consulta')->assertDontSee('Zelia Operacional');
    }

    public function test_usuario_desativado_pela_tela_nao_entra_mais(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA]);

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $alvo->id);

        $this->actingAs($alvo->fresh())->get('/painel')->assertRedirect(route('login'));
    }

    public function test_menu_mostra_usuarios_e_papeis_so_para_quem_tem_permissao(): void
    {
        $this->actingAs($this->usuarioComPapel('admin'))->get(route('dashboard'))
            ->assertSee(route('usuarios.index'), false)->assertSee(route('papeis.index'), false);

        $this->actingAs($this->usuarioComPapel('operacional'))->get(route('dashboard'))
            ->assertDontSee(route('usuarios.index'), false)->assertDontSee(route('papeis.index'), false);
    }

    public function test_banco_de_dados_nao_perde_a_filial_ao_trocar_papel_para_quem_ve_todas(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $alvo = $this->usuarioComPapel('consulta', [$this->filialA]);

        $componente = Livewire::actingAs($admin)->test(Edit::class, ['usuario' => $alvo]);
        $this->preencher($componente, ['papel' => 'gestor'])->call('salvar')->assertHasNoErrors();

        $this->assertTrue($alvo->fresh()->hasRole('gestor'));
        $this->assertSame(1, DB::table('filial_user')->where('user_id', $alvo->id)->count());
    }
}
