<?php

namespace Tests\Feature\Operacao;

use App\Actions\Abastecimentos\AtualizarAbastecimento;
use App\Actions\Abastecimentos\CriarAbastecimento;
use App\Actions\Abastecimentos\ExcluirAbastecimento;
use App\Livewire\Abastecimentos\Create;
use App\Livewire\Abastecimentos\Edit;
use App\Livewire\Abastecimentos\Index;
use App\Models\Abastecimento;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\Posto;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\TripeAbastecimento;
use App\Support\Dinheiro;
use App\Support\SemanaIso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AbastecimentosTest extends TestCase
{
    use RefreshDatabase;

    private Filial $filial;

    private Motorista $motorista;

    private Veiculo $veiculo;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filial = Filial::factory()->create();
        $this->motorista = Motorista::factory()->for($this->filial)->create(['nome' => 'Stenyo']);
        $this->veiculo = Veiculo::factory()->for($this->filial)->create(['placa' => 'ABC1D23', 'motorista_padrao_id' => $this->motorista->id]);
        $this->admin = $this->usuarioComPapel('admin');
    }

    /** @param  array<string, mixed>  $extra */
    private function lancar(array $extra = [], ?Veiculo $veiculo = null): Abastecimento
    {
        $veiculo ??= $this->veiculo;

        return app(CriarAbastecimento::class)->handle($extra + [
            'veiculo_id' => $veiculo->id, 'motorista_id' => $this->motorista->id,
            'data_abastecimento' => '2026-10-01', 'litros' => '400', 'preco_litro' => '6.8', 'odometro_km' => '1000.0',
        ], $this->admin);
    }

    // ----- Tripé litros × preço × valor (RN-06) -----

    public function test_tripe_calcula_o_campo_que_falta(): void
    {
        $tripe = new TripeAbastecimento;

        $this->assertSame(['litros' => '400.000', 'preco_litro' => '6.890', 'valor_total' => '2756.00'], $tripe->completar('400', '6.89', null));
        $this->assertSame(['litros' => '300.000', 'preco_litro' => '6.800', 'valor_total' => '2040.00'], $tripe->completar(null, '6.8', '2040.00'));
        $this->assertSame(['litros' => '340.000', 'preco_litro' => '6.800', 'valor_total' => '2312.00'], $tripe->completar('340', null, '2312.00'));
        $this->assertSame('367.647', $tripe->completar(null, '6.8', '2500.00')['litros']);
    }

    public function test_tripe_aceita_diferenca_de_ate_um_centavo_e_rejeita_mais(): void
    {
        $tripe = new TripeAbastecimento;

        // Caso real da planilha: 367,647 L × 6,80 = 2.500,00 e a planilha digitou 2.499,99.
        $this->assertSame('2499.99', $tripe->completar('367.647', '6.8', '2499.99')['valor_total']);

        $this->expectException(ValidationException::class);
        $tripe->completar('301', '6.8', '2047.32');
    }

    public function test_tripe_exige_dois_dos_tres_e_valores_positivos(): void
    {
        $tripe = new TripeAbastecimento;

        foreach ([['400', null, null], [null, null, '100.00'], [null, null, null]] as [$l, $p, $v]) {
            try {
                $tripe->completar($l, $p, $v);
                $this->fail('Deveria exigir dois campos');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('valor_total', $e->errors());
            }
        }

        $this->expectException(ValidationException::class);
        $tripe->completar('0', '6.8', null);
    }

    // ----- Cadeia de hodômetro com os dados reais da planilha (RN-07/RN-08) -----

    public function test_cadeia_de_hodometro_com_as_tres_semanas_da_planilha(): void
    {
        $linhas = [
            // [data, litros, preço, valor, odômetro]
            ['2026-09-29', '400', '6.89', null, '661429'],        // 1º: sem referência
            ['2026-10-01', '300', '6.8', null, '662353'],
            ['2026-10-03', '400', '6.8', null, '663112'],
            ['2026-10-07', '340', '6.8', null, '663872'],
            ['2026-10-10', '367.647', null, '2499.99', '664785'], // preço calculado
            ['2026-10-12', '368', null, '2502.41', '665548'],
            ['2026-10-13', '301', null, '2047.32', '666310'],     // a planilha tem preço quebrado: litros + valor bastam
            ['2026-10-16', '308.82', null, '2099.99', '667258'],
            ['2026-10-17', '300', '6.8', null, '667944.9'],
        ];

        foreach ($linhas as [$data, $litros, $preco, $valor, $odometro]) {
            $this->lancar(['data_abastecimento' => $data, 'litros' => $litros, 'preco_litro' => $preco, 'valor_total' => $valor, 'odometro_km' => $odometro]);
        }

        $porOdometro = Abastecimento::query()->orderBy('odometro_km')->get()->keyBy(fn ($a) => (string) (float) $a->odometro_km);

        $this->assertNull($porOdometro['661429']->km_rodados, 'sem hodômetro inicial a média é nula, nunca 1.653 km/l');
        $this->assertNull($porOdometro['661429']->media_km_l);

        $esperado = [
            '662353' => ['661429.0', '924.0', '3.0800'],
            '663112' => ['662353.0', '759.0', '1.8975'],
            '663872' => ['663112.0', '760.0', '2.2353'],
            '664785' => ['663872.0', '913.0', '2.4834'],
            '665548' => ['664785.0', '763.0', '2.0734'],
            '666310' => ['665548.0', '762.0', '2.5316'],   // a cadeia atravessa as semanas
            '667258' => ['666310.0', '948.0', '3.0697'],
            '667944.9' => ['667258.0', '686.9', '2.2897'],
        ];

        foreach ($esperado as $chave => [$anterior, $rodados, $media]) {
            $a = $porOdometro[$chave];
            $this->assertSame($anterior, $a->km_anterior, "km inicial de {$chave}");
            $this->assertSame($rodados, $a->km_rodados, "km rodados de {$chave}");
            $this->assertSame($media, $a->media_km_l, "média de {$chave}");
        }

        // Totais de abastecimento por semana (RN-04) conferem com a planilha.
        $valorDaSemana = fn (int $semana) => Dinheiro::total(
            Abastecimento::query()->daSemana(SemanaIso::de(2026, $semana))->pluck('valor_total')
        );
        $this->assertSame('7516.00', $valorDaSemana(40));
        $this->assertSame('4811.99', $valorDaSemana(41));
        $this->assertSame('8689.72', $valorDaSemana(42));

        // O preço calculado da linha com valor quebrado.
        $this->assertSame('6.802', $porOdometro['666310']->preco_litro);
    }

    public function test_hodometro_inicial_do_veiculo_ancora_o_primeiro_abastecimento(): void
    {
        $this->veiculo->update(['odometro_inicial' => '661429.0']);

        $primeiro = $this->lancar(['odometro_km' => '662353', 'litros' => '300', 'preco_litro' => '6.8']);

        $this->assertSame('661429.0', $primeiro->km_anterior);
        $this->assertSame('924.0', $primeiro->km_rodados);
        $this->assertSame('3.0800', $primeiro->media_km_l);
    }

    public function test_hodometro_abaixo_do_inicial_do_veiculo_e_rejeitado(): void
    {
        $this->veiculo->update(['odometro_inicial' => '5000.0']);

        $this->expectException(ValidationException::class);
        $this->lancar(['odometro_km' => '4000']);
    }

    public function test_primeiro_abastecimento_aceita_km_inicial_digitado_e_ele_e_substituido_quando_surge_um_anterior(): void
    {
        $segundo = $this->lancar(['odometro_km' => '2000', 'km_anterior' => '1500']);

        $this->assertSame('1500.0', $segundo->km_anterior);
        $this->assertSame('500.0', $segundo->km_rodados);

        $this->lancar(['odometro_km' => '1800', 'data_abastecimento' => '2026-09-01']);

        $segundo->refresh();
        $this->assertSame('1800.0', $segundo->km_anterior, 'passou a ter um abastecimento anterior de verdade');
        $this->assertSame('200.0', $segundo->km_rodados);
    }

    public function test_lancamento_fora_de_ordem_recalcula_o_seguinte(): void
    {
        $fim = $this->lancar(['odometro_km' => '3000']);
        $this->assertNull($fim->km_anterior);

        $this->lancar(['odometro_km' => '1000']);
        $this->assertSame('1000.0', $fim->fresh()->km_anterior);
        $this->assertSame('2000.0', $fim->fresh()->km_rodados);

        $meio = $this->lancar(['odometro_km' => '2200']);
        $this->assertSame('1200.0', $meio->km_rodados);
        $this->assertSame('2200.0', $fim->fresh()->km_anterior);
        $this->assertSame('800.0', $fim->fresh()->km_rodados);
    }

    public function test_excluir_recalcula_o_seguinte_e_a_exclusao_e_logica(): void
    {
        $a = $this->lancar(['odometro_km' => '1000']);
        $b = $this->lancar(['odometro_km' => '1500']);
        $c = $this->lancar(['odometro_km' => '2100']);

        app(ExcluirAbastecimento::class)->handle($b, $this->admin);

        $this->assertSoftDeleted($b);
        $this->assertSame('1000.0', $c->fresh()->km_anterior);
        $this->assertSame('1100.0', $c->fresh()->km_rodados);
        $this->assertNotNull($a->fresh());
    }

    public function test_editar_hodometro_e_trocar_de_veiculo_recalcula_as_duas_cadeias(): void
    {
        $outro = Veiculo::factory()->for($this->filial)->create();
        $a = $this->lancar(['odometro_km' => '1000']);
        $b = $this->lancar(['odometro_km' => '1500']);
        $c = $this->lancar(['odometro_km' => '2100']);

        app(AtualizarAbastecimento::class)->handle($b, [
            'veiculo_id' => $outro->id, 'motorista_id' => $this->motorista->id, 'data_abastecimento' => '2026-10-01',
            'litros' => '400', 'preco_litro' => '6.8', 'odometro_km' => '900',
        ], $this->admin);

        $this->assertSame('1000.0', $c->fresh()->km_anterior, 'o veículo antigo perdeu um abastecimento');
        $this->assertSame($outro->id, $b->fresh()->veiculo_id);
        $this->assertNull($b->fresh()->km_anterior);
        $this->assertNotNull($a);
    }

    public function test_hodometro_repetido_ou_incoerente_e_rejeitado(): void
    {
        $this->lancar(['odometro_km' => '1000']);

        $falha = function (array $extra) {
            try {
                $this->lancar($extra);
                $this->fail('Deveria falhar');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('odometro_km', $e->errors());
            }
        };

        $falha(['odometro_km' => '1000']);                                  // repetido
        $this->assertSame(1, Abastecimento::count());
    }

    public function test_regras_de_filial_e_situacao_de_motorista_e_veiculo(): void
    {
        $outraFilial = Filial::factory()->create();
        $veiculoDeOutra = Veiculo::factory()->for($outraFilial)->create();
        $inativo = Veiculo::factory()->for($this->filial)->inativo()->create();

        foreach ([['veiculo' => $veiculoDeOutra, 'campo' => 'veiculo_id'], ['veiculo' => $inativo, 'campo' => 'veiculo_id']] as $caso) {
            try {
                $this->lancar([], $caso['veiculo']);
                $this->fail('Deveria falhar');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($caso['campo'], $e->errors());
            }
        }

        $this->assertSame(0, Abastecimento::count());
    }

    public function test_media_so_entra_nas_agregacoes_com_tanque_cheio(): void
    {
        $this->lancar(['odometro_km' => '1000']);
        $cheio = $this->lancar(['odometro_km' => '1800', 'tanque_cheio' => true]);
        $parcial = $this->lancar(['odometro_km' => '2600', 'tanque_cheio' => false]);

        $this->assertNotNull($parcial->media_km_l, 'a média por abastecimento continua calculada');

        $ids = Abastecimento::query()->comMediaValida()->pluck('id')->all();
        $this->assertSame([$cheio->id], $ids);
    }

    public function test_alertas_de_media_fora_da_faixa_e_de_litros_acima_do_tanque(): void
    {
        $this->veiculo->update(['capacidade_tanque_l' => '300.00']);
        $this->lancar(['odometro_km' => '1000']);
        $normal = $this->lancar(['odometro_km' => '1800', 'litros' => '300', 'preco_litro' => '6.8']);   // 2,67 km/l
        $fora = $this->lancar(['odometro_km' => '9800', 'litros' => '100', 'preco_litro' => '6.8']);     // 80 km/l

        $this->assertSame([], $normal->load('veiculo')->alertas());
        $this->assertCount(1, $fora->load('veiculo')->alertas());

        $acima = $this->lancar(['odometro_km' => '10000', 'litros' => '350', 'preco_litro' => '6.8']);
        $this->assertContains('Litros acima da capacidade do tanque.', $acima->load('veiculo')->alertas());
    }

    // ----- Telas -----

    public function test_sem_permissao_nao_acessa(): void
    {
        $usuario = $this->usuarioComPapel('motorista', [$this->filial]);

        $this->actingAs($usuario)->get(route('abastecimentos.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('abastecimentos.create'))->assertForbidden();
    }

    public function test_formulario_calcula_o_terceiro_campo_do_tripe_e_a_previa_da_media(): void
    {
        $this->lancar(['odometro_km' => '1000']);

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->assertSet('form.motorista_id', (string) $this->motorista->id)
            ->set('form.litros', '400')
            ->set('form.preco_litro', '6,8')
            ->assertSet('form.valor_total', '2720,00')
            ->set('form.valor_total', '2.500,00')
            ->assertSet('form.litros', '367,647')
            ->set('form.odometro_km', '1800')
            ->assertSee('800,0 km')->assertSee('2,18 km/l');
    }

    public function test_ao_escolher_o_veiculo_o_km_inicial_ja_aparece_antes_do_km_final(): void
    {
        $this->lancar(['odometro_km' => '1000']);
        $this->lancar(['odometro_km' => '1800', 'data_abastecimento' => '2026-10-05']);

        Livewire::actingAs($this->admin)->test(Create::class)
            ->assertDontSee('1.800,0')
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->assertSee('1.800,0')                                // última leitura do veículo
            ->assertSee('Automático: hodômetro do abastecimento anterior')
            ->assertDontSee('1º abastecimento deste veículo');
    }

    public function test_veiculo_sem_nenhuma_leitura_pede_o_km_inicial_logo_ao_ser_escolhido(): void
    {
        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->assertSee('1º abastecimento deste veículo');

        $this->veiculo->update(['odometro_inicial' => '661429.0']);

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->assertSee('661.429,0')->assertDontSee('1º abastecimento deste veículo');
    }

    public function test_formulario_do_primeiro_abastecimento_pede_o_km_inicial(): void
    {
        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->set('form.odometro_km', '1500')
            ->assertSee('1º abastecimento deste veículo')
            ->set('form.km_anterior', '1000')
            ->assertSee('500,0 km');
    }

    public function test_cadastra_pela_tela_e_mostra_na_lista(): void
    {
        $posto = Posto::factory()->create(['nome' => 'Posto Iguana']);
        $this->lancar(['odometro_km' => '1000']);

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->set('form.posto_id', (string) $posto->id)
            ->set('form.data_abastecimento', '2026-10-03')
            ->set('form.litros', '400')->set('form.preco_litro', '6,8')
            ->set('form.odometro_km', '1759')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('abastecimentos.index'));

        $novo = Abastecimento::query()->where('odometro_km', '1759')->first();
        $this->assertSame('2720.00', $novo->valor_total);
        $this->assertSame('1.8975', $novo->media_km_l);
        $this->assertSame($posto->id, $novo->posto_id);

        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertSee('ABC-1D23')->assertSee('Posto Iguana')->assertSee('1,90 km/l')->assertSee('R$ 2.720,00');
    }

    public function test_valida_campos_da_tela(): void
    {
        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.data_abastecimento', '')
            ->call('salvar')
            ->assertHasErrors(['form.veiculo_id', 'form.motorista_id', 'form.data_abastecimento', 'form.odometro_km']);

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)->set('form.motorista_id', (string) $this->motorista->id)
            ->set('form.odometro_km', '1000')->set('form.litros', '400')
            ->call('salvar')
            ->assertHasErrors('form.valor_total');

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)->set('form.motorista_id', (string) $this->motorista->id)
            ->set('form.odometro_km', 'abc')->set('form.litros', '-5')->set('form.preco_litro', '6,8')
            ->call('salvar')
            ->assertHasErrors(['form.odometro_km', 'form.litros']);
    }

    public function test_hodometro_repetido_aparece_como_erro_na_tela(): void
    {
        $this->lancar(['odometro_km' => '1000']);

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)->set('form.motorista_id', (string) $this->motorista->id)
            ->set('form.odometro_km', '1000')->set('form.litros', '400')->set('form.preco_litro', '6,8')
            ->call('salvar')
            ->assertHasErrors('form.odometro_km');
    }

    public function test_edita_pela_tela(): void
    {
        $a = $this->lancar(['odometro_km' => '1000']);
        $b = $this->lancar(['odometro_km' => '1500', 'litros' => '300', 'preco_litro' => '6.8']);

        Livewire::actingAs($this->admin)->test(Edit::class, ['abastecimento' => $b])
            ->assertSet('form.odometro_km', '1500,0')
            ->assertSet('form.litros', '300,000')
            ->set('form.odometro_km', '1600')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('abastecimentos.index'));

        $this->assertSame('600.0', $b->fresh()->km_rodados);
        $this->assertSame('2.0000', $b->fresh()->media_km_l);
        $this->assertNotNull($a);
    }

    public function test_exclusao_exige_permissao_e_usuario_so_ve_a_propria_filial(): void
    {
        $a = $this->lancar(['odometro_km' => '1000']);
        $operacional = $this->usuarioComPapel('operacional', [$this->filial]);

        Livewire::actingAs($operacional)->test(Index::class)->call('excluir', $a->id)->assertForbidden();
        $this->assertNotSoftDeleted($a);

        $outra = Filial::factory()->create();
        $daOutra = Abastecimento::factory()->daFilial($outra)->create();
        $gerente = $this->usuarioComPapel('gerente_filial', [$this->filial]);

        $this->actingAs($gerente)->get(route('abastecimentos.edit', $a))->assertOk();
        $this->actingAs($gerente)->get(route('abastecimentos.edit', $daOutra))->assertNotFound();

        Livewire::actingAs($this->admin)->test(Index::class)->call('excluir', $a->id);
        $this->assertSoftDeleted($a);
    }

    public function test_lista_filtra_por_periodo_veiculo_e_totaliza(): void
    {
        $this->lancar(['odometro_km' => '1000', 'data_abastecimento' => '2026-10-01', 'litros' => '100', 'preco_litro' => '7']);
        $this->lancar(['odometro_km' => '1700', 'data_abastecimento' => '2026-10-20', 'litros' => '200', 'preco_litro' => '7']);

        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertSee('300,000 L')->assertSee('R$ 2.100,00')
            ->set('de', '2026-10-10')->assertSee('200,000 L')->assertDontSee('300,000 L')
            ->call('limparFiltros')
            ->set('busca', 'XXX0000')->assertSee('Nenhum abastecimento encontrado');
    }

    public function test_registra_auditoria_sem_as_colunas_derivadas(): void
    {
        $a = $this->lancar(['odometro_km' => '1000']);
        $this->lancar(['odometro_km' => '1800']);

        $a->update(['nota_fiscal' => '123']);

        $log = $a->activitiesAsSubject()->latest('id')->first();
        $this->assertArrayHasKey('nota_fiscal', $log->attribute_changes['attributes']);
        $this->assertArrayNotHasKey('media_km_l', $log->attribute_changes['attributes']);
    }
}
