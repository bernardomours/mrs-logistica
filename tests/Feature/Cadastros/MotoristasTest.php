<?php

namespace Tests\Feature\Cadastros;

use App\Livewire\Motoristas\Create;
use App\Livewire\Motoristas\Edit;
use App\Livewire\Motoristas\Index;
use App\Models\Filial;
use App\Models\Motorista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MotoristasTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_permissao_nao_acessa(): void
    {
        $motorista = Motorista::factory()->create();
        $usuario = $this->usuarioComPapel('motorista', [$motorista->filial]);

        $this->actingAs($usuario)->get(route('motoristas.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('motoristas.create'))->assertForbidden();
        $this->actingAs($usuario)->get(route('motoristas.edit', $motorista))->assertForbidden();
    }

    public function test_lista_busca_e_filtra_por_situacao(): void
    {
        Motorista::factory()->create(['nome' => 'Stenyo Silva', 'cpf' => '11144477735']);
        Motorista::factory()->inativo()->create(['nome' => 'João Parado']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)
            ->assertSee('Stenyo Silva')->assertSee('João Parado')
            ->set('busca', '111.444')->assertSee('Stenyo Silva')->assertDontSee('João Parado')
            ->call('limparFiltros')
            ->set('status', 'inativos')->assertSee('João Parado')->assertDontSee('Stenyo Silva');
    }

    public function test_usuario_so_ve_motoristas_das_suas_filiais(): void
    {
        $a = Filial::factory()->create();
        $b = Filial::factory()->create();
        $daA = Motorista::factory()->for($a)->create(['nome' => 'Motorista da A']);
        $daB = Motorista::factory()->for($b)->create(['nome' => 'Motorista da B']);
        $gerente = $this->usuarioComPapel('gerente_filial', [$a]);

        Livewire::actingAs($gerente)->test(Index::class)
            ->assertSee('Motorista da A')->assertDontSee('Motorista da B');

        $this->actingAs($gerente)->get(route('motoristas.edit', $daA))->assertOk();
        // Fora do escopo de filial o registro nem "existe" para o usuário (404, sem vazar a existência).
        $this->actingAs($gerente)->get(route('motoristas.edit', $daB))->assertNotFound();
    }

    public function test_cadastra_motorista_com_comissao_em_formato_brasileiro(): void
    {
        $filial = Filial::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.filial_id', (string) $filial->id)
            ->set('form.nome', 'Stenyo Silva')
            ->set('form.cpf', '111.444.777-35')
            ->set('form.comissao_percentual', '6,5')
            ->set('form.cnh_categoria', 'E')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('motoristas.index'));

        $motorista = Motorista::withoutGlobalScopes()->firstWhere('nome', 'Stenyo Silva');
        $this->assertSame('11144477735', $motorista->cpf);
        $this->assertSame('6.50', $motorista->comissao_percentual);
        $this->assertSame($filial->id, $motorista->filial_id);
        $this->assertTrue($motorista->ativo);
    }

    public function test_porcentagem_da_comissao_e_obrigatoria_e_limitada(): void
    {
        $filial = Filial::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        $base = fn () => Livewire::actingAs($admin)->test(Create::class)
            ->set('form.filial_id', (string) $filial->id)
            ->set('form.nome', 'Fulano')->set('form.cpf', '11144477735');

        $base()->call('salvar')->assertHasErrors(['form.comissao_percentual' => 'required']);
        $base()->set('form.comissao_percentual', '101')->call('salvar')->assertHasErrors('form.comissao_percentual');
        $base()->set('form.comissao_percentual', 'abc')->call('salvar')->assertHasErrors('form.comissao_percentual');
        $base()->set('form.comissao_percentual', '0')->call('salvar')->assertHasNoErrors();
    }

    public function test_valida_cpf_e_unicidade_mesmo_com_mascara(): void
    {
        $filial = Filial::factory()->create();
        Motorista::factory()->for($filial)->create(['cpf' => '11144477735']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.filial_id', (string) $filial->id)->set('form.nome', 'Outro')->set('form.comissao_percentual', '6')
            ->set('form.cpf', '111.444.777-35')->call('salvar')->assertHasErrors('form.cpf')
            ->set('form.cpf', '111.444.777-36')->call('salvar')->assertHasErrors('form.cpf');

        $this->assertSame(1, Motorista::withoutGlobalScopes()->count());
    }

    public function test_nao_cadastra_em_filial_sem_acesso(): void
    {
        $minha = Filial::factory()->create();
        $alheia = Filial::factory()->create();
        $gerente = $this->usuarioComPapel('gerente_filial', [$minha]);

        Livewire::actingAs($gerente)->test(Create::class)
            ->set('form.filial_id', (string) $alheia->id)
            ->set('form.nome', 'Fulano')->set('form.cpf', '11144477735')->set('form.comissao_percentual', '6')
            ->call('salvar')
            ->assertHasErrors('form.filial_id');

        $this->assertSame(0, Motorista::withoutGlobalScopes()->count());
    }

    public function test_edita_sem_conflitar_com_o_proprio_cpf_e_troca_de_filial(): void
    {
        $a = Filial::factory()->create();
        $b = Filial::factory()->create();
        $motorista = Motorista::factory()->for($a)->create(['nome' => 'Antigo', 'cpf' => '11144477735']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Edit::class, ['motorista' => $motorista])
            ->assertSet('form.nome', 'Antigo')
            ->assertSet('form.comissao_percentual', '6,00')
            ->set('form.nome', 'Novo Nome')
            ->set('form.filial_id', (string) $b->id)
            ->set('form.comissao_percentual', '7,25')
            ->call('salvar')
            ->assertHasNoErrors();

        $motorista->refresh();
        $this->assertSame('Novo Nome', $motorista->nome);
        $this->assertSame($b->id, $motorista->filial_id);
        $this->assertSame('7.25', $motorista->comissao_percentual);
    }

    public function test_ativa_e_desativa_com_permissao(): void
    {
        $motorista = Motorista::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $motorista->id);
        $this->assertFalse($motorista->fresh()->ativo);

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $motorista->id);
        $this->assertTrue($motorista->fresh()->ativo);
    }

    public function test_quem_so_visualiza_nao_desativa(): void
    {
        $filial = Filial::factory()->create();
        $motorista = Motorista::factory()->for($filial)->create();
        $consulta = $this->usuarioComPapel('consulta', [$filial]);

        Livewire::actingAs($consulta)->test(Index::class)
            ->call('alternarAtivo', $motorista->id)
            ->assertForbidden();

        $this->assertTrue($motorista->fresh()->ativo);
    }

    public function test_registra_auditoria_das_alteracoes(): void
    {
        $motorista = Motorista::factory()->create();

        $motorista->update(['comissao_percentual' => '8.00']);

        $this->assertGreaterThanOrEqual(1, $motorista->activitiesAsSubject()->count());
    }
}
