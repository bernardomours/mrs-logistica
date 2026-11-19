<?php

namespace Tests\Feature\Cadastros;

use App\Livewire\Postos;
use App\Models\Posto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PostosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_permissao_nao_acessa(): void
    {
        $usuario = $this->usuarioComPapel('motorista');

        $this->actingAs($usuario)->get(route('postos.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('postos.create'))->assertForbidden();
    }

    public function test_clientes_nao_tem_pagina_nem_permissao(): void
    {
        $this->assertFalse(Route::has('clientes.index'));
        $this->assertFalse(Permission::where('name', 'like', 'clientes.%')->exists());
    }

    public function test_lista_busca_por_cnpj_com_mascara_filtra_e_desativa(): void
    {
        $gau = Posto::factory()->create(['nome' => 'Posto Gau', 'cnpj' => '11222333000181']);
        Posto::factory()->inativo()->create(['nome' => 'Posto Velho', 'cnpj' => '45723174000110']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Postos\Index::class)
            ->assertSee('Posto Gau')->assertSee('Posto Velho')
            ->set('busca', '11.222.333')->assertSee('Posto Gau')->assertDontSee('Posto Velho')
            ->call('limparFiltros')
            ->set('status', 'ativos')->assertSee('Posto Gau')->assertDontSee('Posto Velho')
            ->call('alternarAtivo', $gau->id);

        $this->assertFalse($gau->fresh()->ativo);
    }

    public function test_operacional_cadastra_posto(): void
    {
        $operacional = $this->usuarioComPapel('operacional');

        Livewire::actingAs($operacional)->test(Postos\Create::class)
            ->set('form.nome', 'Posto Iguana')->set('form.cnpj', '11.222.333/0001-81')
            ->call('salvar')->assertHasNoErrors()->assertRedirect(route('postos.index'));

        $this->assertSame('11222333000181', Posto::first()->cnpj);
    }

    public function test_posto_gera_chave_e_bloqueia_nome_duplicado_ignorando_acento_e_caixa(): void
    {
        Posto::factory()->create(['nome' => 'Posto São Sabino']);
        $admin = $this->usuarioComPapel('admin');

        $this->assertSame('POSTO SAO SABINO', Posto::first()->nome_chave);

        Livewire::actingAs($admin)->test(Postos\Create::class)
            ->set('form.nome', 'POSTO SAO  SABINO')->call('salvar')->assertHasErrors('form.nome')
            ->set('form.nome', 'Posto Gau')->set('form.uf', 'XX')->call('salvar')->assertHasErrors('form.uf')
            ->set('form.uf', 'RN')->call('salvar')->assertHasNoErrors();

        $this->assertSame(2, Posto::count());
    }

    public function test_posto_valida_cnpj(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Postos\Create::class)
            ->set('form.nome', 'Posto X')->set('form.cnpj', '11222333000182')
            ->call('salvar')->assertHasErrors('form.cnpj');
    }

    public function test_posto_edita_mantendo_o_proprio_nome_e_atualiza_a_chave(): void
    {
        $posto = Posto::factory()->create(['nome' => 'Posto Iguana']);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Postos\Edit::class, ['posto' => $posto])
            ->set('form.cidade', 'Mossoró')->call('salvar')->assertHasNoErrors();

        Livewire::actingAs($admin)->test(Postos\Edit::class, ['posto' => $posto->fresh()])
            ->set('form.nome', 'Posto RCM Aparecida')->call('salvar')->assertHasNoErrors();

        $this->assertSame('POSTO RCM APARECIDA', $posto->fresh()->nome_chave);
    }
}
