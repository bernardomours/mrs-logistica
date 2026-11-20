<?php

namespace Tests\Feature\Operacao;

use App\Actions\Abastecimentos\CriarAbastecimento;
use App\Actions\Fechamentos\FecharSemana;
use App\Actions\Fechamentos\MarcarPago;
use App\Actions\Manutencoes\RegistrarManutencao;
use App\Actions\Viagens\CancelarViagem;
use App\Actions\Viagens\CriarViagem;
use App\Livewire\Dashboard\Index as Dashboard;
use App\Livewire\Relatorios\Index as RelatoriosIndex;
use App\Livewire\Relatorios\Show;
use App\Models\Abastecimento;
use App\Models\Filial;
use App\Models\Local;
use App\Models\Motorista;
use App\Models\TarifaRota;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\FilialAtual;
use App\Services\Indicadores;
use App\Services\Relatorios\Catalogo;
use App\Services\Relatorios\ComissoesPorMotorista;
use App\Services\Relatorios\ConsumoPorVeiculo;
use App\Services\Relatorios\CustoDeManutencao;
use App\Services\Relatorios\FaturamentoPorRota;
use App\Services\Relatorios\Inconsistencias;
use App\Services\Relatorios\ReceitasEDespesas;
use App\Support\PeriodoRelatorio;
use App\Support\SemanaIso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Fase 4: relatórios e dashboard, com os números reais da planilha do motorista STENYO (semanas 40 a 42).
 */
class RelatoriosTest extends TestCase
{
    use RefreshDatabase;

    private Filial $filial;

    private Motorista $motorista;

    private Veiculo $veiculo;

    private Local $origem;

    private Local $destino;

    private Local $outroDestino;

    private User $admin;

    private PeriodoRelatorio $planilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');

        $this->filial = Filial::factory()->create(['nome' => 'Filial Mossoró']);
        $this->motorista = Motorista::factory()->for($this->filial)->create(['nome' => 'Stenyo', 'comissao_percentual' => '6.00']);
        $this->veiculo = Veiculo::factory()->for($this->filial)->create(['placa' => 'ABC1D23']);
        $this->origem = Local::factory()->create(['nome' => 'São Sabino/Apodi']);
        $this->destino = Local::factory()->create(['nome' => 'Natal/RN']);
        $this->outroDestino = Local::factory()->create(['nome' => 'Crossdocking/Fortaleza']);
        $this->admin = $this->usuarioComPapel('admin');
        $this->planilha = PeriodoRelatorio::deAtalho('personalizado', '2026-09-28', '2026-10-18');
    }

    private function viagem(string $data, string $frete, ?Local $destino = null, ?Motorista $motorista = null, ?Veiculo $veiculo = null): void
    {
        app(CriarViagem::class)->handle([
            'motorista_id' => ($motorista ?? $this->motorista)->id, 'veiculo_id' => ($veiculo ?? $this->veiculo)->id,
            'origem_local_id' => $this->origem->id, 'destino_local_id' => ($destino ?? $this->destino)->id,
            'data_viagem' => $data, 'valor_frete' => $frete,
        ], $this->admin);
    }

    private function abastecer(string $data, string $litros, ?string $preco, ?string $valor, string $odometro, ?Veiculo $veiculo = null, ?Motorista $motorista = null): void
    {
        app(CriarAbastecimento::class)->handle([
            'veiculo_id' => ($veiculo ?? $this->veiculo)->id, 'motorista_id' => ($motorista ?? $this->motorista)->id, 'data_abastecimento' => $data,
            'litros' => $litros, 'preco_litro' => $preco, 'valor_total' => $valor, 'odometro_km' => $odometro,
        ], $this->admin);
    }

    /** As três semanas da planilha: 11 viagens (as 3 primeiras para Natal, o resto para o Crossdocking) e 9 abastecimentos. */
    private function semearPlanilha(): void
    {
        foreach ([['2026-09-29', '11170.27'], ['2026-10-01', '4491.69'], ['2026-10-03', '4491.69']] as [$data, $frete]) {
            $this->viagem($data, $frete);
        }
        foreach ([['2026-10-05', '4276.09'], ['2026-10-06', '4491.69'], ['2026-10-08', '2575.23'], ['2026-10-12', '4276.09'], ['2026-10-13', '4491.69'], ['2026-10-15', '4491.69'], ['2026-10-16', '4481.97'], ['2026-10-17', '4541.01']] as [$data, $frete]) {
            $this->viagem($data, $frete, $this->outroDestino);
        }
        foreach ([
            ['2026-09-29', '400', '6.89', null, '661429'], ['2026-10-01', '300', '6.8', null, '662353'], ['2026-10-03', '400', '6.8', null, '663112'],
            ['2026-10-07', '340', '6.8', null, '663872'], ['2026-10-10', '367.647', null, '2499.99', '664785'], ['2026-10-12', '368', null, '2502.41', '665548'],
            ['2026-10-13', '301', null, '2047.32', '666310'], ['2026-10-16', '308.82', null, '2099.99', '667258'], ['2026-10-17', '300', '6.8', null, '667944.9'],
        ] as [$data, $litros, $preco, $valor, $odometro]) {
            $this->abastecer($data, $litros, $preco, $valor, $odometro);
        }
    }

    private function linhas(string $classe, ?PeriodoRelatorio $periodo = null)
    {
        $this->actingAs($this->admin);

        return app($classe)->linhas($periodo ?? $this->planilha);
    }

    // ----- Período -----

    public function test_periodo_por_atalho_e_personalizado(): void
    {
        $p = fn (string $atalho, ?string $de = null, ?string $ate = null) => PeriodoRelatorio::deAtalho($atalho, $de, $ate);

        $this->assertSame('2026-11-01 a 2026-11-30', $p('mes_atual')->de().' a '.$p('mes_atual')->ate());
        $this->assertSame('2026-10-01 a 2026-10-31', $p('mes_anterior')->de().' a '.$p('mes_anterior')->ate());
        $this->assertSame('2026-11-16 a 2026-11-22', $p('semana_atual')->de().' a '.$p('semana_atual')->ate());
        $this->assertSame('2026-11-09 a 2026-11-15', $p('semana_anterior')->de().' a '.$p('semana_anterior')->ate());
        $this->assertSame('2026-10-20 a 2026-11-18', $p('ultimos_30')->de().' a '.$p('ultimos_30')->ate());
        $this->assertSame('2026-01-01 a 2026-12-31', $p('ano_atual')->de().' a '.$p('ano_atual')->ate());
        $this->assertSame('01/11/2026 a 30/11/2026', $p('inexistente')->rotulo(), 'atalho desconhecido cai no mês atual');

        $invertido = $p('personalizado', '2026-10-18', '2026-09-28');
        $this->assertSame('2026-09-28', $invertido->de());
        $this->assertSame('2026-10-18', $invertido->ate());

        $this->assertSame('2026-11-01', $p('personalizado', 'lixo', 'lixo')->de(), 'data inválida cai no mês atual');
        $limitado = $p('personalizado', '2020-01-01', '2026-01-01');
        $this->assertEquals(366, $limitado->inicio->diffInDays($limitado->fim), 'limite de um ano');
    }

    // ----- Relatórios com os números da planilha -----

    public function test_consumo_por_veiculo(): void
    {
        $this->semearPlanilha();

        $linha = $this->linhas(ConsumoPorVeiculo::class)->sole();

        $this->assertSame('ABC-1D23', $linha['veiculo']);
        $this->assertSame(9, $linha['abastecimentos']);
        $this->assertSame('3085.467', $linha['litros']);
        $this->assertSame('21017.71', $linha['valor']);
        $this->assertSame('6.812', $linha['preco_medio']);
        $this->assertSame('6515.9', $linha['km']);
        $this->assertSame('2.43', $linha['media'], 'Σ km 6.515,9 ÷ Σ litros 2.685,467 (o 1º abastecimento não tem média)');
        $this->assertSame('3.23', $linha['custo_km']);
    }

    public function test_consumo_so_considera_o_periodo_e_ignora_tanque_parcial_na_media(): void
    {
        $this->abastecer('2026-10-05', '100', '7', null, '1000');
        $this->abastecer('2026-10-06', '50', '7', null, '1100');
        $parcial = Abastecimento::query()->where('odometro_km', '1100')->firstOrFail();
        $parcial->forceFill(['tanque_cheio' => false])->saveQuietly();
        $this->abastecer('2026-10-07', '100', '7', null, '1300');
        $this->abastecer('2026-12-01', '999', '7', null, '9000');

        $linha = $this->linhas(ConsumoPorVeiculo::class, PeriodoRelatorio::deAtalho('personalizado', '2026-10-01', '2026-10-31'))->sole();

        $this->assertSame(3, $linha['abastecimentos']);
        $this->assertSame('250.000', $linha['litros']);
        $this->assertSame('2.00', $linha['media'], 'só o último (200 km ÷ 100 L): o parcial fica fora');
    }

    public function test_faturamento_por_rota_ordena_pelo_maior_frete(): void
    {
        $this->semearPlanilha();
        $this->viagem('2026-10-02', '9999.00', $this->destino);
        app(CancelarViagem::class)->handle(Viagem::query()->where('valor_frete', '9999.00')->firstOrFail(), $this->admin);

        $linhas = $this->linhas(FaturamentoPorRota::class);

        $this->assertCount(2, $linhas);
        $this->assertSame('São Sabino/Apodi → Crossdocking/Fortaleza', $linhas[0]['rota']);
        $this->assertSame(8, $linhas[0]['viagens']);
        $this->assertSame('33625.46', $linhas[0]['frete']);          // 8 viagens: 53.779,11 do período − 20.153,65 de Natal
        $this->assertSame('São Sabino/Apodi → Natal/RN', $linhas[1]['rota']);
        $this->assertSame('20153.65', $linhas[1]['frete'], 'a cancelada não soma');
        $this->assertSame('6717.88', $linhas[1]['frete_medio']);
        $this->assertSame('1209.22', $linhas[1]['comissao']);
    }

    public function test_comissoes_mostram_gerado_a_pagar_e_pago(): void
    {
        $this->semearPlanilha();
        $semana40 = app(FecharSemana::class)->handle($this->motorista, SemanaIso::de(2026, 40), $this->admin);
        app(MarcarPago::class)->handle($semana40, $this->admin);
        app(FecharSemana::class)->handle($this->motorista, SemanaIso::de(2026, 41), $this->admin);

        $linha = $this->linhas(ComissoesPorMotorista::class)->sole();

        $this->assertSame('Stenyo', $linha['motorista']);
        $this->assertSame(11, $linha['viagens']);
        $this->assertSame('53779.11', $linha['frete']);
        $this->assertSame('3226.75', $linha['comissao']);            // 1.209,22 + 680,58 + 1.336,95
        $this->assertSame('680.58', $linha['a_pagar']);              // semana 41 fechada, não paga
        $this->assertSame('1209.22', $linha['pago']);                // semana 40 paga
    }

    public function test_custo_de_manutencao_por_veiculo(): void
    {
        $registrar = fn (array $extra) => app(RegistrarManutencao::class)->handle($extra + [
            'veiculo_id' => $this->veiculo->id, 'data_manutencao' => '2026-10-05', 'tipo' => 'preventiva', 'descricao' => 'Serviço',
            'valor_pecas' => '800.00', 'valor_mao_obra' => '200.00',
        ], $this->admin);
        $registrar([]);
        $registrar(['tipo' => 'corretiva', 'valor_pecas' => '1500.00', 'valor_mao_obra' => '500.50']);
        $registrar(['data_manutencao' => '2026-12-10']);     // fora do período

        $linha = $this->linhas(CustoDeManutencao::class)->sole();

        $this->assertSame(2, $linha['manutencoes']);
        $this->assertSame(1, $linha['preventivas']);
        $this->assertSame(1, $linha['corretivas']);
        $this->assertSame('2300.00', $linha['pecas']);
        $this->assertSame('700.50', $linha['mao_de_obra']);
        $this->assertSame('3000.50', $linha['total']);
    }

    public function test_receitas_x_despesas_somam_fretes_contra_abastecimento_manutencao_e_comissao(): void
    {
        $this->semearPlanilha();
        $registrar = fn (Veiculo $veiculo, string $data, string $pecas, string $maoDeObra) => app(RegistrarManutencao::class)->handle([
            'veiculo_id' => $veiculo->id, 'data_manutencao' => $data, 'tipo' => 'corretiva', 'descricao' => 'Serviço',
            'valor_pecas' => $pecas, 'valor_mao_obra' => $maoDeObra,
        ], $this->admin);
        $registrar($this->veiculo, '2026-10-05', '800.00', '200.00');
        $registrar($this->veiculo, '2026-12-10', '999.00', '1.00');   // fora do período

        $outra = Filial::factory()->create(['nome' => 'Filial Fortaleza']);
        $motoristaB = Motorista::factory()->for($outra)->create(['comissao_percentual' => '10.00']);
        $veiculoB = Veiculo::factory()->for($outra)->create();
        $this->viagem('2026-10-02', '1000.00', null, $motoristaB, $veiculoB);
        $this->abastecer('2026-10-02', '100', '5', null, '500', $veiculoB, $motoristaB);
        $registrar($veiculoB, '2026-10-03', '150.00', '50.00');

        $admin = $this->linhas(ReceitasEDespesas::class);
        $this->assertCount(2, $admin);

        $a = $admin->firstWhere('filial', 'Filial Mossoró');
        $this->assertSame(11, $a['viagens']);
        $this->assertSame('53779.11', $a['receitas']);
        $this->assertSame('21017.71', $a['abastecimentos']);
        $this->assertSame('1000.00', $a['manutencoes']);
        $this->assertSame('3226.75', $a['comissoes']);
        $this->assertSame('25244.46', $a['despesas']);               // 21.017,71 + 1.000,00 + 3.226,75
        $this->assertSame('28534.65', $a['resultado']);              // 53.779,11 − 25.244,46

        $b = $admin->firstWhere('filial', 'Filial Fortaleza');
        $this->assertSame('1000.00', $b['receitas']);
        $this->assertSame('500.00', $b['abastecimentos']);
        $this->assertSame('200.00', $b['manutencoes']);
        $this->assertSame('100.00', $b['comissoes']);
        $this->assertSame('800.00', $b['despesas']);
        $this->assertSame('200.00', $b['resultado']);

        $gerente = $this->usuarioComPapel('gerente_filial', [$outra]);
        $this->actingAs($gerente);
        $so = app(ReceitasEDespesas::class)->linhas($this->planilha);
        $this->assertCount(1, $so, 'quem acessa só uma filial vê só ela');
        $this->assertSame('Filial Fortaleza', $so[0]['filial']);
    }

    public function test_receitas_x_despesas_ignoram_viagem_cancelada_e_mostram_prejuizo(): void
    {
        $this->viagem('2026-10-05', '1000.00');
        $this->viagem('2026-10-06', '5000.00');
        app(CancelarViagem::class)->handle(Viagem::query()->where('valor_frete', '5000.00')->sole(), $this->admin);
        $this->abastecer('2026-10-06', '300', '6', null, '1000');   // R$ 1.800,00

        $linha = $this->linhas(ReceitasEDespesas::class)->sole();

        $this->assertSame(1, $linha['viagens']);
        $this->assertSame('1000.00', $linha['receitas']);
        $this->assertSame('1860.00', $linha['despesas']);            // 1.800,00 + comissão de 60,00
        $this->assertSame('-860.00', $linha['resultado']);
    }

    public function test_receitas_x_despesas_trazem_cartoes_de_total_e_de_detalhe(): void
    {
        $this->viagem('2026-10-05', '1000.00');
        $this->abastecer('2026-10-06', '300', '6', null, '1000');   // R$ 1.800,00

        $cartoes = app(ReceitasEDespesas::class)->cartoes($this->linhas(ReceitasEDespesas::class));

        $this->assertSame(['Total de receitas', '1000.00'], [$cartoes['principais'][0]['rotulo'], $cartoes['principais'][0]['valor']]);
        $this->assertSame(['Total de despesas', '1860.00'], [$cartoes['principais'][1]['rotulo'], $cartoes['principais'][1]['valor']]);

        $detalhes = collect($cartoes['detalhes'])->keyBy('rotulo');
        $this->assertSame('1000.00', $detalhes['Total de frete']['valor']);
        $this->assertSame('1 viagem concluída', $detalhes['Total de frete']['detalhe']);
        $this->assertSame('60.00', $detalhes['Comissões de motoristas']['valor']);
        $this->assertSame('3,2% das despesas', $detalhes['Comissões de motoristas']['detalhe']);
        $this->assertSame('1800.00', $detalhes['Abastecimentos']['valor']);
        $this->assertSame('96,8% das despesas', $detalhes['Abastecimentos']['detalhe']);
        $this->assertSame('0.00', $detalhes['Manutenções']['valor']);
    }

    public function test_tela_de_receitas_x_despesas_mostra_os_cartoes_so_quando_ha_dados(): void
    {
        $this->viagem('2026-10-05', '1000.00');
        $this->abastecer('2026-10-06', '300', '6', null, '1000');

        Livewire::actingAs($this->admin)->test(Show::class, ['relatorio' => 'receitas-e-despesas'])
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertSee('Total de receitas')->assertSee('R$ 1.000,00')
            ->assertSee('Total de despesas')->assertSee('R$ 1.860,00')
            ->assertSee('Total de frete')->assertSee('Comissões de motoristas')->assertSee('Abastecimentos')->assertSee('Manutenções')
            ->set('de', '2026-01-01')->set('ate', '2026-01-31')
            ->assertSee('Nada para mostrar neste período')->assertDontSee('Total de receitas');

        // Os outros relatórios não ganham cartões.
        Livewire::actingAs($this->admin)->test(Show::class, ['relatorio' => 'consumo-por-veiculo'])
            ->assertDontSee('Total de receitas');
    }

    public function test_receitas_x_despesas_detalham_cada_lancamento_lado_a_lado(): void
    {
        $this->viagem('2026-10-05', '1000.00');                      // comissão 60,00
        $this->abastecer('2026-10-06', '300', '6', null, '1000');    // R$ 1.800,00
        app(RegistrarManutencao::class)->handle([
            'veiculo_id' => $this->veiculo->id, 'data_manutencao' => '2026-10-07', 'tipo' => 'corretiva', 'descricao' => 'Troca de embreagem',
            'valor_pecas' => '150.00', 'valor_mao_obra' => '50.00',
        ], $this->admin);

        [$receitas, $despesas] = app(ReceitasEDespesas::class)->detalhes($this->planilha, $this->linhas(ReceitasEDespesas::class));

        $this->assertSame('Receitas', $receitas['titulo']);
        $this->assertSame(1, $receitas['quantidade']);
        $this->assertSame('1000.00', $receitas['total']);
        $this->assertCount(1, $receitas['linhas']);
        $this->assertSame('Stenyo', $receitas['linhas'][0]['motorista']);
        $this->assertSame('São Sabino/Apodi → Natal/RN', $receitas['linhas'][0]['rota']);
        $this->assertSame('1000.00', $receitas['linhas'][0]['valor']);
        $this->assertFalse($receitas['truncado']);

        $this->assertSame('Despesas', $despesas['titulo']);
        $this->assertSame(3, $despesas['quantidade']);
        $this->assertSame('2060.00', $despesas['total']);            // 1.800,00 + 200,00 + 60,00
        $this->assertSame(['Manutenção', 'Abastecimento', 'Comissão'], $despesas['linhas']->pluck('tipo')->all());   // mais recente primeiro
        $this->assertSame(['200.00', '1800.00', '60.00'], $despesas['linhas']->pluck('valor')->all());
        $this->assertStringContainsString('Troca de embreagem', $despesas['linhas'][0]['descricao']);
        $this->assertStringContainsString('300,000 L', $despesas['linhas'][1]['descricao']);
        $this->assertStringContainsString('6,00% de 1.000,00', $despesas['linhas'][2]['descricao']);
        $this->assertSame(array_sum(array_map('floatval', $despesas['linhas']->pluck('valor')->all())), (float) $despesas['total']);
    }

    public function test_tela_mostra_receitas_e_despesas_lado_a_lado_com_o_detalhe(): void
    {
        $this->viagem('2026-10-05', '1000.00');
        $this->abastecer('2026-10-06', '300', '6', null, '1000');

        Livewire::actingAs($this->admin)->test(Show::class, ['relatorio' => 'receitas-e-despesas'])
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertSee('Receitas')->assertSee('Despesas')->assertSee('Resumo por filial')
            ->assertSee('São Sabino/Apodi → Natal/RN')->assertSee('Abastecimento')->assertSee('Comissão')
            ->assertSee('1 lançamento')->assertSee('2 lançamentos')
            ->assertDontSee('Mostrando os lançamentos mais recentes');
    }

    public function test_detalhe_funciona_quando_so_uma_das_origens_tem_lancamentos(): void
    {
        // Só viagem: sem abastecimento nem manutenção no período.
        $this->viagem('2026-10-05', '1000.00');
        [$receitas, $despesas] = app(ReceitasEDespesas::class)->detalhes($this->planilha, $this->linhas(ReceitasEDespesas::class));
        $this->assertCount(1, $receitas['linhas']);
        $this->assertSame(['Comissão'], $despesas['linhas']->pluck('tipo')->all());

        // Só abastecimento: sem viagem nem manutenção.
        Viagem::query()->delete();
        $this->abastecer('2026-10-06', '300', '6', null, '1000');
        [$receitas, $despesas] = app(ReceitasEDespesas::class)->detalhes($this->planilha, $this->linhas(ReceitasEDespesas::class));
        $this->assertTrue($receitas['linhas']->isEmpty());
        $this->assertSame(['Abastecimento'], $despesas['linhas']->pluck('tipo')->all());

        // Só manutenção.
        Abastecimento::query()->delete();
        app(RegistrarManutencao::class)->handle([
            'veiculo_id' => $this->veiculo->id, 'data_manutencao' => '2026-10-07', 'tipo' => 'preventiva', 'descricao' => 'Revisão',
            'valor_pecas' => '100.00', 'valor_mao_obra' => '50.00',
        ], $this->admin);
        [$receitas, $despesas] = app(ReceitasEDespesas::class)->detalhes($this->planilha, $this->linhas(ReceitasEDespesas::class));
        $this->assertTrue($receitas['linhas']->isEmpty());
        $this->assertSame(['Manutenção'], $despesas['linhas']->pluck('tipo')->all());

        // E a tela abre nesses casos.
        Livewire::actingAs($this->admin)->test(Show::class, ['relatorio' => 'receitas-e-despesas'])
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertOk()->assertSee('Nenhuma viagem concluída no período.');
    }

    public function test_receitas_x_despesas_exigem_as_tres_permissoes_financeiras(): void
    {
        $catalogo = app(Catalogo::class);
        $relatorio = app(ReceitasEDespesas::class);

        $this->assertTrue($catalogo->podeVer($this->admin, $relatorio));
        $this->assertTrue($catalogo->podeVer($this->usuarioComPapel('financeiro', [$this->filial]), $relatorio));
        $this->assertFalse($catalogo->podeVer($this->usuarioComPapel('consulta', [$this->filial]), $relatorio));
        $this->assertFalse($catalogo->podeVer($this->usuarioComPapel('operacional', [$this->filial]), $relatorio));
    }

    public function test_inconsistencias_reunem_os_alertas_da_rn_13(): void
    {
        $veiculo = Veiculo::factory()->for($this->filial)->create(['placa' => 'XYZ9K99', 'capacidade_tanque_l' => '500', 'odometro_inicial' => '1000']);
        foreach ([['10-05', '1250', '6.8'], ['10-06', '1500', '6.8'], ['10-07', '1750', '6.8'], ['10-08', '2000', '6.8'], ['10-09', '2250', '9.0'], ['10-10', '2900', '6.8']] as [$dia, $odo, $preco]) {
            $this->abastecer("2026-{$dia}", '100', $preco, null, $odo, $veiculo);
        }
        $this->abastecer('2026-10-11', '600', '6.8', null, '3000', $veiculo);   // 100 km com 600 L: média < 1 e acima do tanque

        TarifaRota::factory()->create(['origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id, 'valor_frete' => '4491.69']);
        $this->viagem('2026-10-05', '5000.00');                                   // frete ≠ tarifa
        // Viagem de um motorista que não abasteceu na semana: é normal, não vira alerta.
        $semAbastecer = Motorista::factory()->for($this->filial)->create(['nome' => 'Sem Combustível']);
        $this->viagem('2026-10-12', '1000.00', $this->outroDestino, $semAbastecer);

        $this->motorista->update(['cnh_validade' => '2026-01-01']);
        app(RegistrarManutencao::class)->handle([
            'veiculo_id' => $veiculo->id, 'data_manutencao' => '2026-09-01', 'tipo' => 'preventiva', 'descricao' => 'Óleo',
            'valor_pecas' => '100', 'valor_mao_obra' => '0', 'proxima_data' => '2026-11-01',
        ], $this->admin);

        $linhas = $this->linhas(Inconsistencias::class, PeriodoRelatorio::deAtalho('personalizado', '2026-10-01', '2026-10-31'));
        $porTipo = $linhas->groupBy('tipo')->map->count();

        $this->assertSame(2, $porTipo['Média fora da faixa']);
        $this->assertSame(1, $porTipo['Litros acima do tanque']);
        $this->assertSame(1, $porTipo['Preço do litro fora do padrão']);
        $this->assertSame(1, $porTipo['Frete diferente da tarifa']);
        $this->assertSame(1, $porTipo['CNH vencida']);
        $this->assertSame(1, $porTipo['Preventiva vencida']);
        $this->assertCount(7, $linhas);
        $this->assertArrayNotHasKey('Viagem sem abastecimento na semana', $porTipo->all());

        $preco = $linhas->firstWhere('tipo', 'Preço do litro fora do padrão');
        $this->assertStringContainsString('XYZ-9K99', $preco['referencia']);
        $this->assertStringContainsString('R$ 9,000/L', $preco['detalhe']);
        $this->assertStringContainsString('+32,4%', $preco['detalhe']);
    }

    public function test_inconsistencias_sem_ver_valores_nao_mostram_frete_da_tarifa(): void
    {
        TarifaRota::factory()->create(['origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id, 'valor_frete' => '4491.69']);
        $this->viagem('2026-10-05', '5000.00');
        $this->abastecer('2026-10-05', '100', '6.8', null, '1000');
        $periodo = PeriodoRelatorio::deAtalho('personalizado', '2026-10-01', '2026-10-31');

        $this->actingAs($this->usuarioComPapel('consulta', [$this->filial]));
        $this->assertEmpty(app(Inconsistencias::class)->linhas($periodo)->where('tipo', 'Frete diferente da tarifa'));

        $this->actingAs($this->admin);
        $this->assertCount(1, app(Inconsistencias::class)->linhas($periodo)->where('tipo', 'Frete diferente da tarifa'));
    }

    // ----- Permissões (RN-15) -----

    public function test_catalogo_so_mostra_o_que_o_perfil_pode_ver(): void
    {
        $catalogo = app(Catalogo::class);
        $chaves = fn (User $u) => $catalogo->visiveisPara($u)->keys()->all();

        $this->assertCount(6, $chaves($this->admin));
        $this->assertEqualsCanonicalizing(['consumo-por-veiculo', 'inconsistencias'], $chaves($this->usuarioComPapel('consulta', [$this->filial])));
        $this->assertSame([], $chaves($this->usuarioComPapel('operacional', [$this->filial])), 'operacional não tem relatorios.ver');
        $this->assertContains('comissoes', $chaves($this->usuarioComPapel('financeiro', [$this->filial])));
        $this->assertNotContains('comissoes', $chaves($this->usuarioComPapel('consulta', [$this->filial])));
    }

    public function test_acesso_as_telas_por_perfil(): void
    {
        $consulta = $this->usuarioComPapel('consulta', [$this->filial]);
        $motoristaApp = $this->usuarioComPapel('motorista', [$this->filial]);

        $this->actingAs($consulta)->get(route('relatorios.show', 'consumo-por-veiculo'))->assertOk();
        foreach (['faturamento-por-rota', 'comissoes', 'custo-de-manutencao', 'receitas-e-despesas'] as $chave) {
            $this->actingAs($consulta)->get(route('relatorios.show', $chave))->assertForbidden();
            $this->actingAs($consulta)->get(route('relatorios.excel', $chave))->assertForbidden();
        }
        $this->actingAs($this->admin)->get(route('relatorios.show', 'nao-existe'))->assertNotFound();
        $this->actingAs($this->admin)->get(route('relatorios.excel', 'nao-existe'))->assertNotFound();
        $this->actingAs($motoristaApp)->get(route('relatorios.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('relatorios.index'))->assertRedirect(route('login'));
    }

    public function test_exportar_exige_a_permissao_de_exportacao(): void
    {
        // `consulta` vê consumo na tela mas não exporta.
        $this->actingAs($this->usuarioComPapel('consulta', [$this->filial]))->get(route('relatorios.excel', 'consumo-por-veiculo'))->assertForbidden();
        $this->actingAs($this->usuarioComPapel('financeiro', [$this->filial]))->get(route('relatorios.excel', 'consumo-por-veiculo'))->assertOk();
        $this->actingAs($this->usuarioComPapel('financeiro', [$this->filial]))->get(route('relatorios.excel', 'comissoes'))->assertOk();
    }

    // ----- Telas -----

    public function test_hub_lista_os_relatorios_visiveis(): void
    {
        Livewire::actingAs($this->admin)->test(RelatoriosIndex::class)
            ->assertSee('Consumo por veículo')->assertSee('Comissões')->assertSee('Inconsistências')->assertSee('Receitas x Despesas');

        Livewire::actingAs($this->usuarioComPapel('consulta', [$this->filial]))->test(RelatoriosIndex::class)
            ->assertSee('Consumo por veículo')->assertDontSee('Comissões')->assertDontSee('Receitas x Despesas');
    }

    public function test_relatorio_na_tela_com_periodo_personalizado_e_total(): void
    {
        $this->semearPlanilha();
        $segundo = Veiculo::factory()->for($this->filial)->create(['placa' => 'DEF2G34']);
        $this->abastecer('2026-10-02', '100', '6', null, '5000', $segundo);

        Livewire::actingAs($this->admin)->test(Show::class, ['relatorio' => 'consumo-por-veiculo'])
            ->set('periodo', 'personalizado')
            ->assertSet('de', '2026-11-01')                       // parte do mês atual
            ->assertSee('Nada para mostrar neste período')
            ->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertSee('ABC-1D23')->assertSee('DEF-2G34')
            ->assertSee('21.017,71')->assertSee('3.185,467')->assertSee('TOTAL')
            ->assertSee('28/09/2026 a 18/10/2026');
    }

    public function test_exporta_o_relatorio_em_excel_com_total(): void
    {
        $this->semearPlanilha();
        $segundo = Veiculo::factory()->for($this->filial)->create(['placa' => 'DEF2G34']);
        $this->abastecer('2026-10-02', '100', '6', null, '5000', $segundo);

        $resposta = $this->actingAs($this->admin)->get(route('relatorios.excel', ['relatorio' => 'consumo-por-veiculo', 'periodo' => 'personalizado', 'de' => '2026-09-28', 'ate' => '2026-10-18']))->assertOk();

        $this->assertStringContainsString('consumo-por-veiculo-2026-09-28-a-2026-10-18.xlsx', $resposta->headers->get('content-disposition'));

        $arquivo = tempnam(sys_get_temp_dir(), 'rel').'.xlsx';
        file_put_contents($arquivo, $resposta->streamedContent());
        $aba = IOFactory::load($arquivo)->getActiveSheet();
        unlink($arquivo);

        $this->assertSame('Consumo por veículo', $aba->getCell('A1')->getValue());
        $this->assertSame('Período: 28/09/2026 a 18/10/2026', $aba->getCell('A2')->getValue());
        $this->assertSame('Veículo', $aba->getCell('A4')->getValue());
        $this->assertSame('ABC-1D23', $aba->getCell('A5')->getValue());
        $this->assertSame(21017.71, round((float) $aba->getCell('D5')->getValue(), 2));
        $this->assertSame('DEF-2G34', $aba->getCell('A6')->getValue());
        $this->assertSame('TOTAL', $aba->getCell('A7')->getValue());
        $this->assertSame(21617.71, round((float) $aba->getCell('D7')->getValue(), 2));
        $this->assertSame(10, (int) $aba->getCell('B7')->getValue());
    }

    // ----- Dashboard -----

    public function test_dashboard_mostra_os_indicadores_do_periodo_da_planilha(): void
    {
        $this->semearPlanilha();

        Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertSee('53.779,11')                // faturamento das três semanas
            ->assertSee('3.226,75')                 // comissões (com a semana 40 corrigida)
            ->assertSee('21.017,71')                // combustível
            ->assertSee('29.534,65')                // líquido
            ->assertSee('2,43')                     // média ponderada
            ->assertSee('Principais rotas')->assertSee('São Sabino/Apodi → Crossdocking/Fortaleza')
            ->assertSee('Consumo por veículo')->assertSee('ABC-1D23');
    }

    public function test_dashboard_avisa_de_semanas_sem_fechamento_e_some_depois_de_fechar(): void
    {
        $this->semearPlanilha();
        $this->actingAs($this->admin);
        $indicadores = app(Indicadores::class);

        $this->assertCount(3, $indicadores->semanasSemFechamento(), 'semanas 40, 41 e 42 do motorista');

        foreach ([40, 41, 42] as $semana) {
            app(FecharSemana::class)->handle($this->motorista, SemanaIso::de(2026, $semana), $this->admin);
        }
        $this->assertCount(0, $indicadores->semanasSemFechamento());

        Livewire::test(Dashboard::class)->assertDontSee('ainda sem fechamento');
    }

    public function test_dashboard_mostra_alerta_de_semana_pendente_e_de_preventiva(): void
    {
        $this->semearPlanilha();
        app(RegistrarManutencao::class)->handle([
            'veiculo_id' => $this->veiculo->id, 'data_manutencao' => '2026-09-01', 'tipo' => 'preventiva', 'descricao' => 'Óleo',
            'valor_pecas' => '100', 'valor_mao_obra' => '0', 'proxima_data' => '2026-11-01',
        ], $this->admin);

        Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->assertSee('3')->assertSee('semana(s) de motorista com movimento ainda sem fechamento')
            ->assertSee('preventiva vencida');
    }

    public function test_dashboard_esconde_valores_de_quem_nao_pode_ve_los(): void
    {
        $this->semearPlanilha();
        $consulta = $this->usuarioComPapel('consulta', [$this->filial]);

        Livewire::actingAs($consulta)->test(Dashboard::class)
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertSee('21.017,71')                        // combustível é visível
            ->assertDontSee('53.779,11')->assertDontSee('3.226,75')->assertDontSee('29.534,65')
            ->assertDontSee('Principais rotas');
    }

    /** Filial B com uma única viagem concluída de R$ 1.000,00 na semana 41 (separada da planilha da filial A). */
    private function filialComUmaViagem(): Filial
    {
        $filialB = Filial::factory()->create(['nome' => 'Filial Fortaleza']);
        $motorista = Motorista::factory()->for($filialB)->create();
        $veiculo = Veiculo::factory()->for($filialB)->create();

        Viagem::factory()->create([
            'filial_id' => $filialB->id, 'motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id,
            'data_viagem' => '2026-10-07', 'valor_frete' => '1000.00', 'comissao_valor' => '60.00',
        ]);

        return $filialB;
    }

    public function test_dashboard_filtra_por_filial_e_acompanha_a_filial_ativa(): void
    {
        $this->semearPlanilha();
        $filialB = $this->filialComUmaViagem();

        $componente = Livewire::actingAs($this->admin)->test(Dashboard::class)
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertSet('filial', 'todas')
            ->assertSee('Todas as filiais')->assertSee('Filial Mossoró')->assertSee('Filial Fortaleza')
            ->assertSee('54.779,11');                       // planilha da A + R$ 1.000,00 da B

        $componente->set('filial', (string) $filialB->id)
            ->assertSee('1.000,00')->assertDontSee('54.779,11')->assertDontSee('53.779,11');
        $this->assertSame($filialB->id, app(FilialAtual::class)->selecionada());

        $componente->set('filial', (string) $this->filial->id)
            ->assertSee('53.779,11')->assertDontSee('54.779,11');

        $componente->set('filial', 'todas')->assertSee('54.779,11');
        $this->assertNull(app(FilialAtual::class)->selecionada());
    }

    public function test_dashboard_abre_na_filial_ativa_do_menu(): void
    {
        $this->semearPlanilha();
        $filialB = $this->filialComUmaViagem();
        $this->actingAs($this->admin);
        app(FilialAtual::class)->selecionar($filialB->id);

        Livewire::test(Dashboard::class)
            ->assertSet('filial', (string) $filialB->id)
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertSee('1.000,00')->assertDontSee('53.779,11');
    }

    public function test_filtro_de_filial_nunca_amplia_o_acesso(): void
    {
        $this->semearPlanilha();
        $filialB = $this->filialComUmaViagem();
        $gestorDaA = $this->usuarioComPapel('gerente_filial', [$this->filial]);

        Livewire::actingAs($gestorDaA)->test(Dashboard::class)
            ->set('periodo', 'personalizado')->set('de', '2026-09-28')->set('ate', '2026-10-18')
            ->assertDontSee('Filial Fortaleza')
            ->set('filial', (string) $filialB->id)
            ->assertSee('53.779,11')->assertDontSee('54.779,11');
    }

    public function test_usuario_sem_dashboard_ver_ve_so_a_boas_vindas(): void
    {
        $this->semearPlanilha();
        $semPapel = User::factory()->create(['ativo' => true, 'deve_trocar_senha' => false]);

        Livewire::actingAs($semPapel)->test(Dashboard::class)
            ->assertSee('Bem-vindo')->assertDontSee('Faturamento')->assertDontSee('53.779,11');
    }
}
