<?php

namespace Tests\Feature\Filiais;

use App\Livewire\Filiais\Create;
use App\Livewire\Filiais\Edit;
use App\Livewire\Filiais\Index;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FiliaisTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_permissao_nao_acessa(): void
    {
        $filial = Filial::factory()->create();
        $motorista = $this->usuarioComPapel('motorista');

        $this->actingAs($motorista)->get(route('filiais.index'))->assertForbidden();
        $this->actingAs($motorista)->get(route('filiais.create'))->assertForbidden();
        $this->actingAs($motorista)->get(route('filiais.edit', $filial))->assertForbidden();
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get(route('filiais.index'))->assertRedirect(route('login'));
    }

    public function test_admin_ve_todas_as_filiais_e_busca_funciona(): void
    {
        Filial::factory()->create(['nome' => 'Matriz Mossoró', 'codigo' => 'MOS']);
        Filial::factory()->create(['nome' => 'Base Fortaleza', 'codigo' => 'FOR']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)
            ->assertSee('Matriz Mossoró')->assertSee('Base Fortaleza')
            ->set('busca', 'forta')
            ->assertSee('Base Fortaleza')->assertDontSee('Matriz Mossoró')
            ->call('limparFiltros')
            ->assertSee('Matriz Mossoró');
    }

    public function test_usuario_so_ve_as_filiais_vinculadas(): void
    {
        $a = Filial::factory()->create(['nome' => 'Filial A']);
        $b = Filial::factory()->create(['nome' => 'Filial B']);
        $gerente = $this->usuarioComPapel('gerente_filial', [$a]);

        Livewire::actingAs($gerente)->test(Index::class)
            ->assertSee('Filial A')->assertDontSee('Filial B');

        $this->actingAs($gerente)->get(route('filiais.edit', $b))->assertForbidden();
    }

    public function test_filtro_de_situacao(): void
    {
        Filial::factory()->create(['nome' => 'Filial Viva']);
        Filial::factory()->inativa()->create(['nome' => 'Filial Parada']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)
            ->set('status', 'ativos')->assertSee('Filial Viva')->assertDontSee('Filial Parada')
            ->set('status', 'inativos')->assertSee('Filial Parada')->assertDontSee('Filial Viva');
    }

    public function test_cria_filial(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.nome', 'Filial Natal')
            ->set('form.codigo', 'nat')
            ->set('form.cnpj', '11.222.333/0001-81')
            ->set('form.cidade', 'Natal')
            ->set('form.uf', 'RN')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('filiais.index'));

        $filial = Filial::firstWhere('nome', 'Filial Natal');
        $this->assertSame('NAT', $filial->codigo);
        $this->assertSame('11222333000181', $filial->cnpj);
        $this->assertTrue($filial->ativo);
    }

    public function test_quem_cria_sem_ver_todas_passa_a_acessar_a_filial(): void
    {
        $existente = Filial::factory()->create();
        $gerente = $this->usuarioComPapel('gerente_filial', [$existente]);
        $gerente->givePermissionTo('filiais.criar');

        Livewire::actingAs($gerente)->test(Create::class)
            ->set('form.nome', 'Nova Base')->set('form.codigo', 'NB')
            ->call('salvar')->assertHasNoErrors();

        $nova = Filial::firstWhere('nome', 'Nova Base');
        $this->assertTrue($gerente->fresh()->filiais->contains($nova));
    }

    public function test_valida_campos(): void
    {
        $existente = Filial::factory()->create(['nome' => 'Duplicada', 'codigo' => 'DUP']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.nome', '')->set('form.codigo', '')
            ->call('salvar')
            ->assertHasErrors(['form.nome' => 'required', 'form.codigo' => 'required']);

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.nome', 'Duplicada')->set('form.codigo', 'DUP')
            ->set('form.cnpj', '11222333000182')->set('form.uf', 'XX')
            ->call('salvar')
            ->assertHasErrors(['form.nome', 'form.codigo', 'form.cnpj', 'form.uf']);

        $this->assertSame(1, Filial::count());
        $this->assertNotNull($existente);
    }

    public function test_edita_filial_sem_conflitar_com_ela_mesma(): void
    {
        $filial = Filial::factory()->create(['nome' => 'Filial Velha', 'codigo' => 'VEL']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Edit::class, ['filial' => $filial])
            ->assertSet('form.nome', 'Filial Velha')
            ->set('form.nome', 'Filial Renovada')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('filiais.index'));

        $this->assertSame('Filial Renovada', $filial->fresh()->nome);
        $this->assertSame('VEL', $filial->fresh()->codigo);
    }

    public function test_alterna_ativo_e_audita(): void
    {
        $filial = Filial::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $filial->id);
        $this->assertFalse($filial->fresh()->ativo);

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $filial->id);
        $this->assertTrue($filial->fresh()->ativo);

        $this->assertGreaterThanOrEqual(2, $filial->activitiesAsSubject()->count());
    }

    public function test_nao_desativa_filial_com_motoristas_ou_veiculos_ativos(): void
    {
        $filial = Filial::factory()->create();
        $motorista = Motorista::factory()->for($filial)->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $filial->id);
        $this->assertTrue($filial->fresh()->ativo);

        Livewire::actingAs($admin)->test(Edit::class, ['filial' => $filial])
            ->set('form.ativo', false)
            ->call('salvar')
            ->assertHasErrors('form.ativo');
        $this->assertTrue($filial->fresh()->ativo);

        $motorista->update(['ativo' => false]);
        Veiculo::factory()->for($filial)->create();

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $filial->id);
        $this->assertTrue($filial->fresh()->ativo, 'veículo ativo também impede a desativação');

        Veiculo::withoutGlobalScopes()->update(['ativo' => false]);
        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $filial->id);
        $this->assertFalse($filial->fresh()->ativo);
    }

    public function test_operacional_nao_pode_desativar(): void
    {
        $filial = Filial::factory()->create();
        $operacional = $this->usuarioComPapel('operacional', [$filial]);

        Livewire::actingAs($operacional)->test(Index::class)
            ->call('alternarAtivo', $filial->id)
            ->assertForbidden();

        $this->assertTrue($filial->fresh()->ativo);
    }
}
