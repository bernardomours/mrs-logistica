<?php

namespace Tests\Feature\Administracao;

use App\Livewire\Papeis\Create;
use App\Livewire\Papeis\Edit;
use App\Livewire\Papeis\Index;
use App\Models\User;
use App\Support\CatalogoDePermissoes;
use App\Support\PapeisDoSistema;
use Database\Seeders\PapeisEPermissoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Tela de Papéis: criar, ajustar permissões e excluir, com os papéis admin e motorista protegidos. */
class PapeisTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_descreve_todas_as_permissoes_do_sistema_e_nada_a_mais(): void
    {
        $reais = PapeisEPermissoesSeeder::permissoes();
        $catalogo = array_keys(CatalogoDePermissoes::todas());

        $this->assertEqualsCanonicalizing($reais, $catalogo);
        $this->assertSame(count($catalogo), count(array_unique($catalogo)), 'permissão repetida em dois grupos');
    }

    public function test_lista_mostra_papeis_com_contagens_e_protecao(): void
    {
        $admin = $this->usuarioComPapel('admin');
        Role::create(['name' => 'supervisor', 'guard_name' => 'web']);

        $this->actingAs($admin)->get(route('papeis.index'))->assertOk()
            ->assertSee('Administrador')->assertSee('Gerente de filial')->assertSee('supervisor')
            ->assertSee('Personalizado')->assertSee('Protegido')->assertSee('Novo papel');
    }

    public function test_so_quem_gerencia_papeis_acessa(): void
    {
        $gestor = $this->usuarioComPapel('gestor');

        $this->actingAs($gestor)->get(route('papeis.index'))->assertForbidden();
        $this->actingAs($gestor)->get(route('papeis.create'))->assertForbidden();
    }

    public function test_cria_papel_personalizado(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.nome', 'Supervisor de Frota')
            ->set('form.permissoes', ['viagens.ver', 'abastecimentos.ver'])
            ->call('salvar')->assertHasNoErrors()->assertRedirect(route('papeis.index'));

        $papel = Role::findByName('Supervisor de Frota');
        $this->assertEqualsCanonicalizing(['viagens.ver', 'abastecimentos.ver'], $papel->permissions->pluck('name')->all());
        $this->assertTrue(Activity::where('log_name', 'papeis')->where('description', 'Papel criado')->exists());
    }

    public function test_nome_do_papel_e_validado(): void
    {
        $admin = $this->usuarioComPapel('admin');
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);

        foreach (['', 'admin', 'Administrador', 'MOTORISTA', 'supervisor', 'com <tag>', str_repeat('a', 61)] as $nome) {
            Livewire::actingAs($admin)->test(Create::class)
                ->set('form.nome', $nome)->call('salvar')->assertHasErrors(['form.nome']);
        }

        $this->assertSame(8, Role::count());
    }

    public function test_permissao_inexistente_e_recusada(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.nome', 'Hacker')->set('form.permissoes', ['viagens.ver', 'sistema.destruir'])
            ->call('salvar')->assertHasErrors(['form.permissoes']);

        $this->assertNull(Role::where('name', 'Hacker')->first());
        $this->assertNull(Permission::where('name', 'sistema.destruir')->first());
    }

    public function test_edita_permissoes_de_papel_padrao_sem_mudar_o_nome(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $consulta = Role::findByName('consulta');
        $this->assertTrue($consulta->hasPermissionTo('relatorios.ver'));

        Livewire::actingAs($admin)->test(Edit::class, ['papel' => $consulta])
            ->set('form.nome', 'Outro Nome')
            ->set('form.permissoes', ['viagens.ver', 'dashboard.ver'])
            ->call('salvar')->assertHasNoErrors()->assertRedirect(route('papeis.index'));

        $consulta = Role::find($consulta->id);
        $this->assertSame('consulta', $consulta->name);
        $this->assertEqualsCanonicalizing(['viagens.ver', 'dashboard.ver'], $consulta->permissions->pluck('name')->all());

        $registro = Activity::where('description', 'Papel atualizado')->firstOrFail();
        $this->assertContains('relatorios.ver', $registro->properties['removidas']);
    }

    public function test_alteracao_de_permissao_vale_na_hora_para_o_usuario(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $consulta = $this->usuarioComPapel('consulta');
        $this->assertTrue($consulta->can('relatorios.ver'));

        Livewire::actingAs($admin)->test(Edit::class, ['papel' => Role::findByName('consulta')])
            ->set('form.permissoes', ['dashboard.ver'])->call('salvar')->assertHasNoErrors();

        $this->assertFalse(User::find($consulta->id)->can('relatorios.ver'));
        $this->assertTrue(User::find($consulta->id)->can('dashboard.ver'));
    }

    public function test_renomeia_papel_personalizado(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $papel = Role::create(['name' => 'Antigo', 'guard_name' => 'web']);

        Livewire::actingAs($admin)->test(Edit::class, ['papel' => $papel])
            ->set('form.nome', 'Novo Nome')->call('salvar')->assertHasNoErrors();

        $this->assertSame('Novo Nome', $papel->fresh()->name);
    }

    public function test_administrador_e_motorista_nao_sao_editados_nem_excluidos(): void
    {
        $admin = $this->usuarioComPapel('admin');

        foreach (['admin', 'motorista'] as $nome) {
            $papel = Role::findByName($nome);
            $this->actingAs($admin)->get(route('papeis.edit', $papel))->assertForbidden();
            $this->assertFalse($admin->can('update', $papel));
            $this->assertFalse($admin->can('delete', $papel));
        }

        Livewire::actingAs($admin)->test(Index::class)->call('excluir', Role::findByName('admin')->id)->assertForbidden();
        $this->assertNotNull(Role::findByName('admin'));
    }

    public function test_papel_padrao_nao_e_excluido(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $gestor = Role::findByName('gestor');

        $this->assertFalse($admin->can('delete', $gestor));
        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $gestor->id)->assertForbidden();
        $this->assertNotNull(Role::findByName('gestor'));
    }

    public function test_exclui_papel_personalizado_sem_usuarios(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $papel = Role::create(['name' => 'Temporario', 'guard_name' => 'web']);

        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $papel->id);

        $this->assertNull(Role::where('name', 'Temporario')->first());
        $this->assertTrue(Activity::where('description', 'Papel excluído')->exists());
    }

    public function test_nao_exclui_papel_em_uso(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $papel = Role::create(['name' => 'EmUso', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($papel);

        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $papel->id)->assertDispatched('toast');

        $this->assertNotNull(Role::where('name', 'EmUso')->first());
    }

    public function test_quem_nao_e_admin_nao_concede_nem_retira_permissao_que_nao_tem(): void
    {
        $this->semearPapeis();
        $gerente = User::factory()->create(['ativo' => true, 'deve_trocar_senha' => false]);
        $gerente->assignRole(Role::create(['name' => 'gerente-de-papeis', 'guard_name' => 'web'])
            ->givePermissionTo(['papeis.gerenciar', 'viagens.ver']));

        Livewire::actingAs($gerente)->test(Create::class)
            ->set('form.nome', 'Ousado')->set('form.permissoes', ['viagens.ver', 'comissoes.ver'])
            ->call('salvar')->assertHasErrors(['form.permissoes']);

        Livewire::actingAs($gerente)->test(Create::class)
            ->set('form.nome', 'Modesto')->set('form.permissoes', ['viagens.ver'])
            ->call('salvar')->assertHasNoErrors();

        $consulta = Role::findByName('consulta');
        $antes = $consulta->permissions->pluck('name')->sort()->values()->all();

        Livewire::actingAs($gerente)->test(Edit::class, ['papel' => $consulta])
            ->set('form.permissoes', ['viagens.ver'])->call('salvar')->assertHasErrors(['form.permissoes']);

        $this->assertSame($antes, Role::findByName('consulta')->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_marcar_e_limpar_um_grupo_respeita_o_que_o_usuario_pode_conceder(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->call('marcarGrupo', 'viagens', true)
            ->assertSet('form.permissoes', array_keys(CatalogoDePermissoes::grupos()['viagens']['permissoes']))
            ->call('marcarGrupo', 'viagens', false)
            ->assertSet('form.permissoes', []);
    }

    public function test_seeder_nao_sobrescreve_papel_padrao_editado_mas_mantem_admin_completo(): void
    {
        $this->semearPapeis();

        $gestor = Role::findByName('gestor');
        $gestor->syncPermissions(['viagens.ver']);
        Role::findByName('admin')->revokePermissionTo('papeis.gerenciar');
        Role::findByName('motorista')->givePermissionTo('viagens.ver');

        $this->seed(PapeisEPermissoesSeeder::class);

        $this->assertSame(['viagens.ver'], Role::findByName('gestor')->permissions->pluck('name')->all());
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('papeis.gerenciar'));
        $this->assertSame(['portal.acessar'], Role::findByName('motorista')->permissions->pluck('name')->all());
    }

    public function test_rotulos_dos_papeis_padrao_em_portugues(): void
    {
        $this->assertSame('Administrador', PapeisDoSistema::rotulo('admin'));
        $this->assertSame('Gerente de filial', PapeisDoSistema::rotulo('gerente_filial'));
        $this->assertSame('Meu Papel', PapeisDoSistema::rotulo('Meu Papel'));
    }
}
