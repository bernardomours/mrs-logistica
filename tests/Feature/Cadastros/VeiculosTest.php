<?php

namespace Tests\Feature\Cadastros;

use App\Enums\VeiculoTipo;
use App\Livewire\Veiculos\Create;
use App\Livewire\Veiculos\Edit;
use App\Livewire\Veiculos\Index;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VeiculosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_permissao_nao_acessa(): void
    {
        $usuario = $this->usuarioComPapel('motorista');

        $this->actingAs($usuario)->get(route('veiculos.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('veiculos.create'))->assertForbidden();
    }

    public function test_lista_e_busca_por_placa(): void
    {
        Veiculo::factory()->create(['placa' => 'ABC1D23', 'marca' => 'Volvo']);
        Veiculo::factory()->create(['placa' => 'XYZ9876', 'marca' => 'Scania']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)
            ->assertSee('ABC-1D23')->assertSee('XYZ-9876')
            ->set('busca', 'scania')->assertSee('XYZ-9876')->assertDontSee('ABC-1D23');
    }

    public function test_usuario_so_ve_veiculos_da_sua_filial(): void
    {
        $a = Filial::factory()->create();
        $b = Filial::factory()->create();
        Veiculo::factory()->for($a)->create(['placa' => 'AAA1A11']);
        $daB = Veiculo::factory()->for($b)->create(['placa' => 'BBB2B22']);
        $gerente = $this->usuarioComPapel('gerente_filial', [$a]);

        Livewire::actingAs($gerente)->test(Index::class)->assertSee('AAA-1A11')->assertDontSee('BBB-2B22');
        $this->actingAs($gerente)->get(route('veiculos.edit', $daB))->assertNotFound();
    }

    public function test_cadastra_veiculo_normalizando_placa_e_numeros(): void
    {
        $filial = Filial::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.filial_id', (string) $filial->id)
            ->set('form.placa', 'abc-1d23')
            ->set('form.tipo', VeiculoTipo::Cavalo->value)
            ->set('form.odometro_inicial', '661.429,5')
            ->set('form.capacidade_tanque_l', '600')
            ->set('form.consumo_referencia_km_l', '2,5')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('veiculos.index'));

        $veiculo = Veiculo::withoutGlobalScopes()->firstWhere('placa', 'ABC1D23');
        $this->assertSame('661429.5', $veiculo->odometro_inicial);
        $this->assertSame('600.00', $veiculo->capacidade_tanque_l);
        $this->assertSame('2.50', $veiculo->consumo_referencia_km_l);
        $this->assertSame(VeiculoTipo::Cavalo, $veiculo->tipo);
        $this->assertSame('ABC-1D23', $veiculo->placa_formatada);
    }

    public function test_valida_placa_tipo_e_unicidade(): void
    {
        $filial = Filial::factory()->create();
        Veiculo::factory()->for($filial)->create(['placa' => 'ABC1D23']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.filial_id', (string) $filial->id)
            ->set('form.placa', '123')->set('form.tipo', 'foguete')
            ->call('salvar')->assertHasErrors(['form.placa', 'form.tipo'])
            ->set('form.placa', 'abc-1d23')->set('form.tipo', 'truck')
            ->call('salvar')->assertHasErrors('form.placa');
    }

    public function test_motorista_padrao_precisa_ser_da_mesma_filial(): void
    {
        $a = Filial::factory()->create();
        $b = Filial::factory()->create();
        $motoristaDaB = Motorista::factory()->for($b)->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.filial_id', (string) $a->id)
            ->set('form.placa', 'ABC1D23')->set('form.tipo', 'truck')
            ->set('form.motorista_padrao_id', (string) $motoristaDaB->id)
            ->call('salvar')
            ->assertHasErrors('form.motorista_padrao_id');

        $this->assertSame(0, Veiculo::withoutGlobalScopes()->count());
    }

    public function test_trocar_a_filial_limpa_o_motorista_padrao(): void
    {
        $a = Filial::factory()->create();
        $b = Filial::factory()->create();
        $motorista = Motorista::factory()->for($a)->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.filial_id', (string) $a->id)
            ->set('form.motorista_padrao_id', (string) $motorista->id)
            ->set('form.filial_id', (string) $b->id)
            ->assertSet('form.motorista_padrao_id', '');
    }

    public function test_edita_veiculo(): void
    {
        $filial = Filial::factory()->create();
        $motorista = Motorista::factory()->for($filial)->create();
        $veiculo = Veiculo::factory()->for($filial)->create(['placa' => 'ABC1D23']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Edit::class, ['veiculo' => $veiculo])
            ->assertSet('form.placa', 'ABC1D23')
            ->set('form.marca', 'Volvo')
            ->set('form.motorista_padrao_id', (string) $motorista->id)
            ->call('salvar')
            ->assertHasNoErrors();

        $veiculo->refresh();
        $this->assertSame('Volvo', $veiculo->marca);
        $this->assertSame($motorista->id, $veiculo->motorista_padrao_id);
    }

    public function test_ativa_e_desativa(): void
    {
        $veiculo = Veiculo::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Index::class)->call('alternarAtivo', $veiculo->id);

        $this->assertFalse($veiculo->fresh()->ativo);
    }
}
