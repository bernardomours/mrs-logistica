<?php

namespace Tests\Feature\Operacao;

use App\Actions\Viagens\CriarViagem;
use App\Enums\ViagemStatus;
use App\Livewire\Viagens\Create;
use App\Livewire\Viagens\Edit;
use App\Livewire\Viagens\Index;
use App\Models\Filial;
use App\Models\Local;
use App\Models\Motorista;
use App\Models\TarifaRota;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Support\Dinheiro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ViagensTest extends TestCase
{
    use RefreshDatabase;

    private Filial $filial;

    private Motorista $motorista;

    private Veiculo $veiculo;

    private Local $origem;

    private Local $destino;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filial = Filial::factory()->create();
        $this->motorista = Motorista::factory()->for($this->filial)->create(['nome' => 'Stenyo', 'comissao_percentual' => '6.00']);
        $this->veiculo = Veiculo::factory()->for($this->filial)->create(['placa' => 'ABC1D23', 'motorista_padrao_id' => $this->motorista->id]);
        $this->origem = Local::factory()->create(['nome' => 'São Sabino/Apodi']);
        $this->destino = Local::factory()->create(['nome' => 'Natal/RN']);
    }

    /** @return array<string, mixed> */
    private function dados(array $extra = []): array
    {
        return $extra + [
            'motorista_id' => $this->motorista->id, 'veiculo_id' => $this->veiculo->id,
            'origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id,
            'data_viagem' => '2026-10-01', 'valor_frete' => '4491.69',
        ];
    }

    public function test_sem_permissao_nao_acessa(): void
    {
        $usuario = $this->usuarioComPapel('motorista', [$this->filial]);

        $this->actingAs($usuario)->get(route('viagens.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('viagens.create'))->assertForbidden();
    }

    public function test_comissao_e_calculada_por_viagem_com_o_percentual_do_motorista(): void
    {
        $admin = $this->usuarioComPapel('admin');

        $viagem = app(CriarViagem::class)->handle($this->dados(['valor_frete' => '11170.27']), $admin);

        $this->assertSame('6.00', $viagem->comissao_percentual);
        $this->assertSame('670.22', $viagem->comissao_valor);
        $this->assertSame($this->filial->id, $viagem->filial_id);
        $this->assertSame(ViagemStatus::Concluida, $viagem->status);
        $this->assertSame($admin->id, $viagem->created_by);
    }

    public function test_numeros_reais_da_planilha_semana_40(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $acao = app(CriarViagem::class);

        $acao->handle($this->dados(['data_viagem' => '2026-09-29', 'valor_frete' => '11170.27']), $admin);
        $acao->handle($this->dados(['data_viagem' => '2026-10-01', 'valor_frete' => '4491.69']), $admin);
        $acao->handle($this->dados(['data_viagem' => '2026-10-03', 'valor_frete' => '4491.69']), $admin);

        $viagens = Viagem::query()->concluidas()->get();
        $this->assertSame('20153.65', bcadd((string) $viagens->sum('valor_frete'), '0', 2));
        // A planilha somou só 2 comissões (939,72) por erro; o certo é 1.209,22.
        $this->assertSame('1209.22', Dinheiro::total($viagens->pluck('comissao_valor')));
        $this->assertSame(40, $viagens->first()->semana()->semana);
    }

    public function test_snapshot_da_comissao_nao_muda_quando_o_cadastro_do_motorista_muda(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $viagem = app(CriarViagem::class)->handle($this->dados(), $admin);

        $this->motorista->update(['comissao_percentual' => '10.00']);

        $this->assertSame('6.00', $viagem->fresh()->comissao_percentual);
        $this->assertSame('269.50', $viagem->fresh()->comissao_valor);
    }

    public function test_so_quem_tem_comissoes_editar_ajusta_o_percentual(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $operacional = $this->usuarioComPapel('operacional', [$this->filial]);

        $ajustada = app(CriarViagem::class)->handle($this->dados(['comissao_percentual' => '8.00']), $admin);
        $this->assertSame('8.00', $ajustada->comissao_percentual);
        $this->assertSame('359.34', $ajustada->comissao_valor);

        $ignorada = app(CriarViagem::class)->handle($this->dados(['comissao_percentual' => '50.00']), $operacional);
        $this->assertSame('6.00', $ignorada->comissao_percentual, 'sem comissoes.editar o percentual enviado é ignorado');
    }

    public function test_guarda_a_tarifa_vigente_como_referencia_e_sinaliza_divergencia(): void
    {
        $tarifa = TarifaRota::factory()->create([
            'origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id,
            'valor_frete' => '4491.69', 'vigencia_inicio' => '2026-01-01',
        ]);
        $admin = $this->usuarioComPapel('admin');

        $igual = app(CriarViagem::class)->handle($this->dados(), $admin);
        $diferente = app(CriarViagem::class)->handle($this->dados(['valor_frete' => '4481.97']), $admin);

        $this->assertSame($tarifa->id, $igual->tarifa_rota_id);
        $this->assertFalse($igual->divergeDaTarifa());
        $this->assertTrue($diferente->divergeDaTarifa());
    }

    public function test_regras_de_motorista_e_veiculo(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $outraFilial = Filial::factory()->create();
        $veiculoDeOutra = Veiculo::factory()->for($outraFilial)->create();
        $inativo = Motorista::factory()->for($this->filial)->inativo()->create();

        $falha = function (array $extra, string $campo) use ($admin) {
            try {
                app(CriarViagem::class)->handle($this->dados($extra), $admin);
                $this->fail("Deveria falhar em {$campo}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($campo, $e->errors());
            }
        };

        $falha(['veiculo_id' => $veiculoDeOutra->id], 'veiculo_id');           // RN-19: filiais diferentes
        $falha(['motorista_id' => $inativo->id], 'motorista_id');              // RN-17: inativo
        $falha(['destino_local_id' => $this->origem->id], 'destino_local_id'); // origem = destino
        $falha(['motorista_id' => 9999], 'motorista_id');

        $this->assertSame(0, Viagem::withoutGlobalScopes()->count());
    }

    public function test_nao_lanca_em_filial_sem_acesso(): void
    {
        $outra = Filial::factory()->create();
        $gerente = $this->usuarioComPapel('gerente_filial', [$outra]);

        $this->expectException(ValidationException::class);

        app(CriarViagem::class)->handle($this->dados(), $gerente);
    }

    public function test_formulario_sugere_frete_comissao_e_veiculo_padrao(): void
    {
        TarifaRota::factory()->create([
            'origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id,
            'valor_frete' => '4491.69', 'vigencia_inicio' => '2026-01-01',
        ]);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.motorista_id', (string) $this->motorista->id)
            ->assertSet('form.veiculo_id', (string) $this->veiculo->id)
            ->assertSet('form.comissao_percentual', '6,00')
            ->set('form.origem_local_id', (string) $this->origem->id)
            ->set('form.destino_local_id', (string) $this->destino->id)
            ->assertSet('form.valor_frete', '4491,69')
            ->assertSet('freteSugerido', '4.491,69')
            ->assertSee('Comissão desta viagem: R$ 269,50')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('viagens.index'));

        $viagem = Viagem::first();
        $this->assertSame('4491.69', $viagem->valor_frete);
        $this->assertSame('269.50', $viagem->comissao_valor);
        $this->assertNotNull($viagem->tarifa_rota_id);
    }

    public function test_formulario_nao_sobrescreve_valor_digitado_a_mao(): void
    {
        TarifaRota::factory()->create([
            'origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id,
            'valor_frete' => '4491.69', 'vigencia_inicio' => '2026-01-01',
        ]);
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.valor_frete', '5.000,00')
            ->set('form.origem_local_id', (string) $this->origem->id)
            ->set('form.destino_local_id', (string) $this->destino->id)
            ->assertSet('form.valor_frete', '5.000,00')
            ->assertSet('freteSugerido', '4.491,69');
    }

    public function test_trocar_o_motorista_limpa_o_veiculo_de_outra_filial(): void
    {
        $outraFilial = Filial::factory()->create();
        $motoristaDaOutra = Motorista::factory()->for($outraFilial)->create();
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.motorista_id', (string) $this->motorista->id)
            ->assertSet('form.veiculo_id', (string) $this->veiculo->id)
            ->set('form.motorista_id', (string) $motoristaDaOutra->id)
            ->assertSet('form.veiculo_id', '');
    }

    public function test_valida_campos_obrigatorios(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.data_viagem', '')
            ->call('salvar')
            ->assertHasErrors(['form.motorista_id', 'form.veiculo_id', 'form.origem_local_id', 'form.destino_local_id', 'form.valor_frete', 'form.data_viagem']);

        Livewire::actingAs($admin)->test(Create::class)
            ->set('form.motorista_id', (string) $this->motorista->id)->set('form.veiculo_id', (string) $this->veiculo->id)
            ->set('form.origem_local_id', (string) $this->origem->id)->set('form.destino_local_id', (string) $this->origem->id)
            ->set('form.valor_frete', 'abc')->set('form.comissao_percentual', '120')
            ->call('salvar')
            ->assertHasErrors(['form.destino_local_id', 'form.valor_frete', 'form.comissao_percentual']);
    }

    public function test_edita_viagem_recalcula_a_comissao_e_troca_de_motorista_usa_o_percentual_dele(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $viagem = app(CriarViagem::class)->handle($this->dados(), $admin);
        $outro = Motorista::factory()->for($this->filial)->create(['comissao_percentual' => '5.00']);

        Livewire::actingAs($admin)->test(Edit::class, ['viagem' => $viagem])
            ->assertSet('form.valor_frete', '4491,69')
            ->set('form.valor_frete', '5.000,00')
            ->call('salvar')->assertHasNoErrors();

        $this->assertSame('300.00', $viagem->fresh()->comissao_valor);
        $this->assertSame($admin->id, $viagem->fresh()->updated_by);

        Livewire::actingAs($admin)->test(Edit::class, ['viagem' => $viagem->fresh()])
            ->set('form.motorista_id', (string) $outro->id)
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->set('form.comissao_percentual', '')
            ->call('salvar')->assertHasNoErrors();

        $this->assertSame('5.00', $viagem->fresh()->comissao_percentual);
        $this->assertSame('250.00', $viagem->fresh()->comissao_valor);
    }

    public function test_cancelar_preserva_o_historico_e_tira_dos_totais(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $viagem = app(CriarViagem::class)->handle($this->dados(), $admin);

        Livewire::actingAs($admin)->test(Index::class)->call('cancelar', $viagem->id);

        $this->assertSame(ViagemStatus::Cancelada, $viagem->fresh()->status);
        $this->assertSame(0, Viagem::query()->concluidas()->count());
        $this->assertNotNull(Viagem::find($viagem->id));

        $this->actingAs($admin)->get(route('viagens.edit', $viagem))->assertForbidden();
    }

    public function test_excluir_e_logico_e_exige_permissao(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $viagem = app(CriarViagem::class)->handle($this->dados(), $admin);
        $operacional = $this->usuarioComPapel('operacional', [$this->filial]);

        Livewire::actingAs($operacional)->test(Index::class)->call('excluir', $viagem->id)->assertForbidden();
        $this->assertNotSoftDeleted($viagem);

        Livewire::actingAs($admin)->test(Index::class)->call('excluir', $viagem->id);
        $this->assertSoftDeleted($viagem);
    }

    public function test_lista_filtra_e_totaliza_so_o_concluido(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $acao = app(CriarViagem::class);
        $acao->handle($this->dados(['data_viagem' => '2026-10-01', 'valor_frete' => '1000.00']), $admin);
        $acao->handle($this->dados(['data_viagem' => '2026-10-20', 'valor_frete' => '2000.00']), $admin);
        $acao->handle($this->dados(['data_viagem' => '2026-10-21', 'valor_frete' => '500.00', 'status' => 'planejada']), $admin);

        $teste = Livewire::actingAs($admin)->test(Index::class)
            ->assertSee('R$ 3.000,00')->assertSee('R$ 180,00');

        $teste->set('de', '2026-10-15')->assertSee('R$ 2.000,00')->assertDontSee('R$ 3.000,00')
            ->call('limparFiltros')
            ->set('status', 'planejada')->assertSee('Planejada')
            ->set('busca', 'Apodi')->assertSee('São Sabino/Apodi')
            ->set('busca', 'inexistente')->assertSee('Nenhuma viagem encontrada');
    }

    public function test_usuario_so_ve_viagens_da_sua_filial(): void
    {
        $admin = $this->usuarioComPapel('admin');
        $minha = app(CriarViagem::class)->handle($this->dados(), $admin);

        $outraFilial = Filial::factory()->create();
        $daOutra = Viagem::factory()->daFilial($outraFilial)->create();
        $nomeDaOutra = $daOutra->motorista->nome;
        $gerente = $this->usuarioComPapel('gerente_filial', [$this->filial]);

        Livewire::actingAs($gerente)->test(Index::class)->assertSee('Stenyo')->assertDontSee($nomeDaOutra);
        $this->actingAs($gerente)->get(route('viagens.edit', $minha))->assertOk();
        $this->actingAs($gerente)->get(route('viagens.edit', $daOutra))->assertNotFound();
    }

    public function test_quem_nao_ve_comissoes_nao_recebe_esses_dados(): void
    {
        $admin = $this->usuarioComPapel('admin');
        app(CriarViagem::class)->handle($this->dados(), $admin);
        $operacional = $this->usuarioComPapel('operacional', [$this->filial]);

        Livewire::actingAs($operacional)->test(Index::class)
            ->assertSee('R$ 4.491,69')
            ->assertDontSee('Comissão dos motoristas')->assertDontSee('269,50');

        Livewire::actingAs($operacional)->test(Create::class)
            ->assertDontSee('Porcentagem da comissão');
    }

    public function test_quem_so_consulta_nao_ve_o_valor_do_frete(): void
    {
        $admin = $this->usuarioComPapel('admin');
        app(CriarViagem::class)->handle($this->dados(), $admin);
        $consulta = User::factory()->create();
        $consulta->assignRole('consulta');
        $consulta->givePermissionTo('viagens.ver');
        $consulta->filiais()->sync([$this->filial->id]);

        Livewire::actingAs($consulta)->test(Index::class)
            ->assertSee('Stenyo')->assertDontSee('4.491,69')->assertDontSee('Frete (faturamento)');
    }
}
