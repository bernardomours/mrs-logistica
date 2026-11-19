<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Tarifas\CriarTarifa;
use App\Enums\LocalTipo;
use App\Livewire\Tarifas;
use App\Models\Cliente;
use App\Models\Local;
use App\Models\TarifaRota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class LocaisTarifasTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_permissao_nao_acessa_tarifas(): void
    {
        $usuario = $this->usuarioComPapel('motorista');

        $this->actingAs($usuario)->get(route('tarifas.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('tarifas.create'))->assertForbidden();
        $this->actingAs($usuario)->get(route('tarifas.importar'))->assertForbidden();
        $this->actingAs($usuario)->get(route('tarifas.modelo'))->assertForbidden();
    }

    public function test_locais_nao_tem_pagina_propria(): void
    {
        $this->assertFalse(Route::has('locais.index'));
        $this->assertFalse(Route::has('locais.create'));
    }

    public function test_botao_novo_local_cadastra_e_avisa_a_tela_de_origem(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\NovoLocal::class)
            ->call('abrir', 'origem')
            ->assertSet('aberto', true)
            ->set('form.nome', 'São Sabino/Apodi')
            ->set('form.tipo', LocalTipo::Fazenda->value)
            ->set('form.uf', 'RN')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('aberto', false)
            ->assertDispatched('local-criado', id: Local::first()->id, alvo: 'origem');

        $local = Local::first();
        $this->assertSame('SAO SABINO/APODI', $local->nome_chave);
        $this->assertSame(LocalTipo::Fazenda, $local->tipo);
        $this->assertTrue($local->ativo);
    }

    public function test_novo_local_valida_nome_duplicado_tipo_e_uf(): void
    {
        Local::factory()->create(['nome' => 'Natal/RN']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\NovoLocal::class)
            ->call('abrir')
            ->set('form.nome', 'NATAL/RN')->call('salvar')->assertHasErrors('form.nome')
            ->set('form.nome', '')->call('salvar')->assertHasErrors(['form.nome' => 'required'])
            ->set('form.nome', 'Outro')->set('form.tipo', 'planeta')->set('form.uf', 'ZZ')
            ->call('salvar')->assertHasErrors(['form.tipo', 'form.uf']);

        $this->assertSame(1, Local::count());
    }

    public function test_novo_local_exige_permissao(): void
    {
        $semPermissao = $this->usuarioComPapel('consulta');
        $financeiro = $this->usuarioComPapel('financeiro');

        Livewire::actingAs($semPermissao)->test(Tarifas\NovoLocal::class)->call('abrir')->assertForbidden();
        Livewire::actingAs($financeiro)->test(Tarifas\NovoLocal::class)->call('abrir')->assertSet('aberto', true);
    }

    public function test_tarifa_seleciona_o_local_recem_criado(): void
    {
        $local = Local::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\Create::class)
            ->dispatch('local-criado', id: $local->id, alvo: 'destino')
            ->assertSet('form.destino_local_id', (string) $local->id)
            ->assertSet('form.origem_local_id', '')
            ->dispatch('local-criado', id: $local->id, alvo: 'origem')
            ->assertSet('form.origem_local_id', (string) $local->id);
    }

    public function test_botoes_da_tela_de_tarifas_respeitam_a_permissao(): void
    {
        $financeiro = $this->usuarioComPapel('financeiro');
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($financeiro)->test(Tarifas\Index::class)
            ->assertSee('Novo local')->assertSee('Importar planilha')->assertSee('Nova tarifa');

        Livewire::actingAs($admin)->test(Tarifas\Index::class)->assertSee('Importar planilha');
    }

    public function test_cadastra_tarifa_com_valor_em_formato_brasileiro(): void
    {
        $origem = Local::factory()->create();
        $destino = Local::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\Create::class)
            ->set('form.origem_local_id', (string) $origem->id)
            ->set('form.destino_local_id', (string) $destino->id)
            ->set('form.valor_frete', '4.491,69')
            ->set('form.vigencia_inicio', '2026-01-01')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tarifas.index'));

        $tarifa = TarifaRota::first();
        $this->assertSame('4491.69', $tarifa->valor_frete);
        $this->assertNull($tarifa->cliente_id);
        $this->assertNull($tarifa->vigencia_fim);
    }

    public function test_tarifa_valida_campos(): void
    {
        $local = Local::factory()->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\Create::class)
            ->set('form.vigencia_inicio', '')
            ->call('salvar')
            ->assertHasErrors(['form.origem_local_id', 'form.destino_local_id', 'form.valor_frete', 'form.vigencia_inicio']);

        Livewire::actingAs($admin)->test(Tarifas\Create::class)
            ->set('form.origem_local_id', (string) $local->id)->set('form.destino_local_id', (string) $local->id)
            ->set('form.valor_frete', '0')->set('form.vigencia_inicio', '2026-02-01')->set('form.vigencia_fim', '2026-01-01')
            ->call('salvar')
            ->assertHasErrors(['form.destino_local_id', 'form.valor_frete', 'form.vigencia_fim']);
    }

    public function test_tarifa_nao_pode_sobrepor_vigencia_da_mesma_rota_e_cliente(): void
    {
        $origem = Local::factory()->create();
        $destino = Local::factory()->create();
        TarifaRota::factory()->create([
            'origem_local_id' => $origem->id, 'destino_local_id' => $destino->id,
            'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-06-30',
        ]);
        $admin = $this->usuarioComPapel('admin');

        $tentar = fn (string $inicio, ?string $fim) => Livewire::actingAs($admin)->test(Tarifas\Create::class)
            ->set('form.origem_local_id', (string) $origem->id)->set('form.destino_local_id', (string) $destino->id)
            ->set('form.valor_frete', '5000')
            ->set('form.vigencia_inicio', $inicio)->set('form.vigencia_fim', $fim ?? '')
            ->call('salvar');

        $tentar('2026-06-30', null)->assertHasErrors('form.vigencia_inicio');
        $tentar('2025-12-01', '2026-01-01')->assertHasErrors('form.vigencia_inicio');
        $tentar('2026-03-01', '2026-03-31')->assertHasErrors('form.vigencia_inicio');
        $tentar('2026-07-01', null)->assertHasNoErrors();
        $this->assertSame(2, TarifaRota::count());
    }

    public function test_a_tela_de_tarifa_nao_tem_cliente_mas_o_modelo_continua_aceitando(): void
    {
        $origem = Local::factory()->create();
        $destino = Local::factory()->create();
        $cliente = Cliente::factory()->create();
        TarifaRota::factory()->create(['origem_local_id' => $origem->id, 'destino_local_id' => $destino->id]);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\Create::class)->assertDontSee('Cliente');

        // Reserva para o futuro: uma tarifa de cliente específico convive com a genérica da mesma rota.
        app(CriarTarifa::class)->handle([
            'cliente_id' => $cliente->id, 'origem_local_id' => $origem->id, 'destino_local_id' => $destino->id,
            'valor_frete' => '9000.00', 'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => null,
        ]);

        $this->assertSame(2, TarifaRota::count());
    }

    public function test_edita_tarifa_sem_conflitar_com_ela_mesma(): void
    {
        $tarifa = TarifaRota::factory()->create(['valor_frete' => '4491.69']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\Edit::class, ['tarifa' => $tarifa])
            ->assertSet('form.valor_frete', '4491,69')
            ->set('form.valor_frete', '4.500,00')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tarifas.index'));

        $this->assertSame('4500.00', $tarifa->fresh()->valor_frete);
    }

    public function test_exclui_tarifa_logicamente_e_filtra_por_vigencia(): void
    {
        $vigente = TarifaRota::factory()->create(['vigencia_inicio' => '2020-01-01']);
        $encerrada = TarifaRota::factory()->create(['vigencia_inicio' => '2020-01-01', 'vigencia_fim' => '2020-12-31']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Tarifas\Index::class)
            ->set('vigencia', 'vigentes')->assertSee($vigente->origem->nome)->assertDontSee($encerrada->origem->nome)
            ->set('vigencia', 'encerradas')->assertSee($encerrada->origem->nome)->assertDontSee($vigente->origem->nome)
            ->call('excluir', $encerrada->id);

        $this->assertSoftDeleted($encerrada);
        $this->assertNotSoftDeleted($vigente);
    }

    public function test_scope_de_vigencia_para_a_sugestao_de_frete(): void
    {
        $origem = Local::factory()->create();
        $destino = Local::factory()->create();
        $antiga = TarifaRota::factory()->create(['origem_local_id' => $origem->id, 'destino_local_id' => $destino->id, 'vigencia_inicio' => '2025-01-01', 'vigencia_fim' => '2025-12-31']);
        $atual = TarifaRota::factory()->create(['origem_local_id' => $origem->id, 'destino_local_id' => $destino->id, 'vigencia_inicio' => '2026-01-01']);

        $this->assertTrue(TarifaRota::vigentesEm('2026-10-01')->whereKey($atual->id)->exists());
        $this->assertFalse(TarifaRota::vigentesEm('2026-10-01')->whereKey($antiga->id)->exists());
        $this->assertTrue(TarifaRota::vigentesEm('2025-12-31')->whereKey($antiga->id)->exists());
    }

    public function test_financeiro_gerencia_tarifas_mas_operacional_nao_acessa(): void
    {
        $tarifa = TarifaRota::factory()->create();
        $financeiro = $this->usuarioComPapel('financeiro');
        $operacional = $this->usuarioComPapel('operacional');

        $this->actingAs($financeiro)->get(route('tarifas.edit', $tarifa))->assertOk();
        $this->actingAs($operacional)->get(route('tarifas.index'))->assertForbidden();
    }
}
