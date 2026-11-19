<?php

namespace Tests\Feature\Operacao;

use App\Actions\Abastecimentos\AtualizarAbastecimento;
use App\Actions\Abastecimentos\CriarAbastecimento;
use App\Actions\Abastecimentos\ExcluirAbastecimento;
use App\Actions\Fechamentos\EstornarPagamento;
use App\Actions\Fechamentos\FecharSemana;
use App\Actions\Fechamentos\MarcarPago;
use App\Actions\Fechamentos\ReabrirSemana;
use App\Actions\Viagens\AtualizarViagem;
use App\Actions\Viagens\CancelarViagem;
use App\Actions\Viagens\CriarViagem;
use App\Actions\Viagens\ExcluirViagem;
use App\Enums\FechamentoStatus;
use App\Enums\ViagemStatus;
use App\Livewire\Fechamentos\Folha;
use App\Livewire\Fechamentos\Index;
use App\Models\Abastecimento;
use App\Models\FechamentoSemanal;
use App\Models\Filial;
use App\Models\Local;
use App\Models\Motorista;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Fechamento semanal (RN-11) com os números reais da planilha do motorista STENYO (CLAUDE.md §11).
 */
class FechamentoSemanalTest extends TestCase
{
    use RefreshDatabase;

    private Filial $filial;

    private Motorista $motorista;

    private Veiculo $veiculo;

    private Local $origem;

    private Local $destino;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');

        $this->filial = Filial::factory()->create();
        $this->motorista = Motorista::factory()->for($this->filial)->create(['nome' => 'Stenyo', 'comissao_percentual' => '6.00']);
        $this->veiculo = Veiculo::factory()->for($this->filial)->create(['placa' => 'ABC1D23', 'motorista_padrao_id' => $this->motorista->id]);
        $this->origem = Local::factory()->create(['nome' => 'São Sabino/Apodi']);
        $this->destino = Local::factory()->create(['nome' => 'Natal/RN']);
        $this->admin = $this->usuarioComPapel('admin');
    }

    private function viagem(string $data, string $frete, array $extra = []): Viagem
    {
        return app(CriarViagem::class)->handle($extra + [
            'motorista_id' => $this->motorista->id, 'veiculo_id' => $this->veiculo->id,
            'origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id,
            'data_viagem' => $data, 'valor_frete' => $frete,
        ], $this->admin);
    }

    private function abastecer(string $data, string $litros, ?string $preco, ?string $valor, string $odometro, array $extra = []): Abastecimento
    {
        return app(CriarAbastecimento::class)->handle($extra + [
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $this->motorista->id, 'data_abastecimento' => $data,
            'litros' => $litros, 'preco_litro' => $preco, 'valor_total' => $valor, 'odometro_km' => $odometro,
        ], $this->admin);
    }

    /** As semanas 40, 41 e 42 da planilha, lançadas pelo sistema. */
    private function semearPlanilha(): void
    {
        foreach ([
            ['2026-09-29', '11170.27'], ['2026-10-01', '4491.69'], ['2026-10-03', '4491.69'],
            ['2026-10-05', '4276.09'], ['2026-10-06', '4491.69'], ['2026-10-08', '2575.23'],
            ['2026-10-12', '4276.09'], ['2026-10-13', '4491.69'], ['2026-10-15', '4491.69'], ['2026-10-16', '4481.97'], ['2026-10-17', '4541.01'],
        ] as [$data, $frete]) {
            $this->viagem($data, $frete);
        }

        foreach ([
            ['2026-09-29', '400', '6.89', null, '661429'],
            ['2026-10-01', '300', '6.8', null, '662353'],
            ['2026-10-03', '400', '6.8', null, '663112'],
            ['2026-10-07', '340', '6.8', null, '663872'],
            ['2026-10-10', '367.647', null, '2499.99', '664785'],
            ['2026-10-12', '368', null, '2502.41', '665548'],
            ['2026-10-13', '301', null, '2047.32', '666310'],
            ['2026-10-16', '308.82', null, '2099.99', '667258'],
            ['2026-10-17', '300', '6.8', null, '667944.9'],
        ] as [$data, $litros, $preco, $valor, $odometro]) {
            $this->abastecer($data, $litros, $preco, $valor, $odometro);
        }
    }

    private function resumo(int $semana, ?Motorista $motorista = null)
    {
        return app(ResumoSemanal::class)->paraMotorista($motorista ?? $this->motorista, SemanaIso::de(2026, $semana));
    }

    private function fechar(int $semana, ?User $usuario = null): FechamentoSemanal
    {
        return app(FecharSemana::class)->handle($this->motorista, SemanaIso::de(2026, $semana), $usuario ?? $this->admin);
    }

    // ----- Resumo semanal (RN-03 a RN-09) -----

    public function test_resumo_reproduz_os_numeros_da_planilha_das_tres_semanas(): void
    {
        $this->semearPlanilha();

        $s40 = $this->resumo(40);
        $this->assertSame(3, $s40->qtdViagens);
        $this->assertSame('20153.65', $s40->faturamento);
        $this->assertSame('1209.22', $s40->comissao, 'a planilha somou só duas comissões (939,72) por erro');
        $this->assertSame('7516.00', $s40->abastecimento);
        $this->assertSame('11428.43', $s40->liquido);
        $this->assertSame('1209.22', $s40->valorAPagar);
        $this->assertSame('1100.000', $s40->litros);
        $this->assertSame('1683.0', $s40->km);
        $this->assertSame('2.4888', $s40->mediaSimples, 'o 1º abastecimento não tem média e fica de fora');
        $this->assertSame('2.4043', $s40->mediaPonderada, '1.683 km ÷ 700 L: o 1º abastecimento (sem média) fica de fora');
        $this->assertSame('em_andamento', $s40->situacao());

        $s41 = $this->resumo(41);
        $this->assertSame('11343.01', $s41->faturamento);
        $this->assertSame('680.58', $s41->comissao);
        $this->assertSame('4811.99', $s41->abastecimento);
        $this->assertSame('5850.44', $s41->liquido);
        $this->assertSame('2.3593', $s41->mediaSimples, 'média das médias sem arredondar antes (2,2353 e 2,4834 daria 2,3594)');

        $s42 = $this->resumo(42);
        $this->assertSame(5, $s42->qtdViagens);
        $this->assertSame('22282.45', $s42->faturamento);
        $this->assertSame('1336.95', $s42->comissao);
        $this->assertSame('8689.72', $s42->abastecimento);
        $this->assertSame('12255.78', $s42->liquido);
        $this->assertSame('2.4911', $s42->mediaSimples);
        $this->assertSame('2.4729', $s42->mediaPonderada, '3.159,9 km ÷ 1.277,82 L = 2,47288 (o guia trazia 2,4728, truncado)');
    }

    public function test_viagem_cancelada_nao_soma_e_abastecimento_parcial_soma_valor_mas_nao_entra_na_media(): void
    {
        $this->viagem('2026-10-12', '1000.00');
        $cancelada = $this->viagem('2026-10-13', '9999.00');
        app(CancelarViagem::class)->handle($cancelada, $this->admin);
        $this->viagem('2026-10-14', '500.00', ['status' => ViagemStatus::Planejada->value]);

        $this->abastecer('2026-10-12', '100', '7', null, '1000');
        $this->abastecer('2026-10-14', '50', '7', null, '1100', ['tanque_cheio' => false]);
        $this->abastecer('2026-10-16', '100', '7', null, '1300');

        $resumo = $this->resumo(42);

        $this->assertSame(1, $resumo->qtdViagens);
        $this->assertSame(1, $resumo->viagensPendentes);
        $this->assertSame(3, $resumo->viagens->count(), 'a folha lista tudo; só as concluídas somam');
        $this->assertSame('1000.00', $resumo->faturamento);
        $this->assertSame('1750.00', $resumo->abastecimento, 'o parcial entra no valor e nos litros');
        $this->assertSame('250.000', $resumo->litros);
        // Médias: 1º sem referência; o parcial (100 km/50 L) fica de fora; o último = 200 km / 100 L.
        $this->assertSame('2.0000', $resumo->mediaSimples);
    }

    public function test_semana_sem_abastecimento_com_media_nao_tem_media(): void
    {
        $this->abastecer('2026-10-12', '100', '7', null, '1000');

        $resumo = $this->resumo(42);

        $this->assertNull($resumo->mediaSimples);
        $this->assertNull($resumo->mediaPonderada);
        $this->assertSame('0.0', $resumo->km);
    }

    // ----- Fechar (RN-11.1 e RN-11.5) -----

    public function test_fechar_grava_o_snapshot_da_semana(): void
    {
        $this->semearPlanilha();

        $fechamento = $this->fechar(40);

        $this->assertSame(FechamentoStatus::Fechado, $fechamento->status);
        $this->assertSame($this->filial->id, $fechamento->filial_id);
        $this->assertSame(40, $fechamento->semana_iso);
        $this->assertSame(2026, $fechamento->ano_iso);
        $this->assertSame('2026-09-28', $fechamento->data_inicio->toDateString());
        $this->assertSame('2026-10-04', $fechamento->data_fim->toDateString());
        $this->assertSame(3, $fechamento->qtd_viagens);
        $this->assertSame('20153.65', $fechamento->total_faturamento);
        $this->assertSame('1209.22', $fechamento->total_comissao);
        $this->assertSame('1209.22', $fechamento->valor_a_pagar);
        $this->assertSame('7516.00', $fechamento->total_abastecimento);
        $this->assertSame('11428.43', $fechamento->total_liquido);
        $this->assertSame('1100.000', $fechamento->total_litros);
        $this->assertSame('1683.0', $fechamento->total_km);
        $this->assertSame('2.4888', $fechamento->media_km_l_simples);
        $this->assertSame('2.4043', $fechamento->media_km_l_ponderada);
        $this->assertSame($this->admin->id, $fechamento->fechado_por);
        $this->assertNotNull($fechamento->fechado_em);

        $this->assertSame('fechado', $this->resumo(40)->situacao());
        $this->assertSame('em_andamento', $this->resumo(41)->situacao(), 'só a semana fechada muda de situação');
    }

    public function test_semana_ja_fechada_nao_fecha_de_novo(): void
    {
        $this->semearPlanilha();
        $this->fechar(40);

        $this->expectException(ValidationException::class);
        $this->fechar(40);
    }

    public function test_nao_fecha_semana_futura_vazia_ou_com_viagem_pendente(): void
    {
        $tentar = function (int $semana, string $campo = 'semana') {
            try {
                $this->fechar($semana);
                $this->fail("Deveria recusar a semana {$semana}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($campo, $e->errors());

                return $e->errors()[$campo][0];
            }
        };

        $this->assertStringContainsString('futura', $tentar(50));
        $this->assertStringContainsString('não tem viagens nem abastecimentos', $tentar(42));

        $this->viagem('2026-10-12', '1000.00', ['status' => ViagemStatus::EmTransito->value]);
        $this->assertStringContainsString('planejada(s) ou em trânsito', $tentar(42));
    }

    public function test_a_semana_em_andamento_pode_ser_fechada_mas_a_futura_nao(): void
    {
        // 18/11/2026 é quarta-feira da semana 47.
        $this->viagem('2026-11-16', '1000.00');

        $fechamento = $this->fechar(47);

        $this->assertSame(47, $fechamento->semana_iso);
    }

    public function test_so_fecha_quem_tem_permissao_e_acesso_a_filial(): void
    {
        $this->semearPlanilha();
        $outraFilial = Filial::factory()->create();

        $operacional = $this->usuarioComPapel('operacional', [$this->filial]);
        $gestorDeOutraFilial = $this->usuarioComPapel('gerente_filial', [$outraFilial]);

        foreach ([$operacional, $gestorDeOutraFilial] as $usuario) {
            try {
                $this->fechar(40, $usuario);
                $this->fail('Deveria negar o fechamento');
            } catch (AuthorizationException) {
                $this->assertSame(0, FechamentoSemanal::count());
            }
        }

        $gerente = $this->usuarioComPapel('gerente_filial', [$this->filial]);
        $this->assertSame(FechamentoStatus::Fechado, $this->fechar(40, $gerente)->status);
    }

    // ----- Trava dos lançamentos (RN-11.2) -----

    public function test_semana_fechada_trava_viagens_do_motorista(): void
    {
        $this->semearPlanilha();
        $dentro = Viagem::query()->whereDate('data_viagem', '2026-10-01')->firstOrFail();
        $fora = Viagem::query()->whereDate('data_viagem', '2026-10-06')->firstOrFail();
        $this->fechar(40);

        $dados = fn (array $extra = []) => $extra + [
            'motorista_id' => $this->motorista->id, 'veiculo_id' => $this->veiculo->id,
            'origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id,
            'data_viagem' => '2026-10-02', 'valor_frete' => '1000.00',
        ];

        $recusa = function (callable $acao) {
            try {
                $acao();
                $this->fail('Deveria recusar: semana fechada');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('fechada', collect($e->errors())->flatten()->implode(' '));
            }
        };

        $recusa(fn () => $this->viagem('2026-10-02', '1000.00'));
        $recusa(fn () => app(AtualizarViagem::class)->handle($dentro, $dados(['data_viagem' => '2026-10-01', 'valor_frete' => '1.00']), $this->admin));
        $recusa(fn () => app(AtualizarViagem::class)->handle($fora, $dados(['data_viagem' => '2026-10-02']), $this->admin), 'mover para a semana fechada');
        $recusa(fn () => app(AtualizarViagem::class)->handle($dentro, $dados(['data_viagem' => '2026-10-07']), $this->admin), 'tirar da semana fechada');
        $recusa(fn () => app(CancelarViagem::class)->handle($dentro, $this->admin));
        $recusa(fn () => app(ExcluirViagem::class)->handle($dentro, $this->admin));

        // Outra semana e outro motorista seguem livres.
        $this->assertNotNull($this->viagem('2026-10-09', '1000.00'));
        $outro = Motorista::factory()->for($this->filial)->create();
        $this->assertNotNull(app(CriarViagem::class)->handle($dados(['motorista_id' => $outro->id, 'data_viagem' => '2026-10-02']), $this->admin));

        $this->assertSame('4491.69', $dentro->fresh()->valor_frete, 'a viagem travada não mudou');
        $this->assertSame(ViagemStatus::Concluida, $dentro->fresh()->status);
        $this->assertNull($dentro->fresh()->deleted_at);
    }

    public function test_semana_fechada_trava_abastecimentos_do_motorista(): void
    {
        $this->semearPlanilha();
        $dentro = Abastecimento::query()->where('odometro_km', '662353')->firstOrFail();
        $this->fechar(40);

        $dados = fn (array $extra = []) => $extra + [
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $this->motorista->id, 'data_abastecimento' => '2026-10-01',
            'litros' => '300', 'preco_litro' => '6.8', 'odometro_km' => '662353',
        ];

        $recusa = function (callable $acao) {
            try {
                $acao();
                $this->fail('Deveria recusar: semana fechada');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        };

        $recusa(fn () => $this->abastecer('2026-10-02', '10', '7', null, '662400'));
        $recusa(fn () => app(AtualizarAbastecimento::class)->handle($dentro, $dados(['litros' => '1']), $this->admin));
        $recusa(fn () => app(ExcluirAbastecimento::class)->handle($dentro, $this->admin));

        $this->assertSame('300.000', $dentro->fresh()->litros);
        $this->assertNull($dentro->fresh()->deleted_at);
    }

    public function test_lancamento_que_alteraria_a_cadeia_de_uma_semana_fechada_e_recusado(): void
    {
        $this->abastecer('2026-10-12', '100', '7', null, '1000');
        $this->abastecer('2026-10-19', '100', '7', null, '2000');
        $this->fechar(43);   // semana do abastecimento de 2.000 km

        // Entre os dois (semana 42, aberta): mudaria o km inicial e a média do abastecimento de 2.000 km (travado).
        try {
            $this->abastecer('2026-10-15', '100', '7', null, '1500');
            $this->fail('Deveria recusar: o seguinte está em semana fechada');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('odometro_km', $e->errors());
        }

        // Depois do último não mexe em ninguém fechado.
        $this->assertNotNull($this->abastecer('2026-10-20', '100', '7', null, '2500', ['motorista_id' => Motorista::factory()->for($this->filial)->create()->id]));
    }

    public function test_policies_escondem_acoes_em_semana_fechada(): void
    {
        $this->semearPlanilha();
        $viagem = Viagem::query()->whereDate('data_viagem', '2026-10-01')->firstOrFail();
        $abastecimento = Abastecimento::query()->where('odometro_km', '662353')->firstOrFail();
        $aberta = Viagem::query()->whereDate('data_viagem', '2026-10-06')->firstOrFail();
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $viagem));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $abastecimento));

        $this->fechar(40);

        foreach (['update', 'cancelar', 'delete'] as $habilidade) {
            $this->assertFalse(Gate::forUser($this->admin)->allows($habilidade, $viagem), "{$habilidade} da viagem");
        }
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $abastecimento));
        $this->assertFalse(Gate::forUser($this->admin)->allows('delete', $abastecimento));
        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $aberta));
    }

    // ----- Pagar, estornar e reabrir (RN-11.3 e RN-11.4) -----

    public function test_marcar_pago_so_a_partir_de_fechada_e_com_permissao(): void
    {
        $this->semearPlanilha();
        $fechamento = $this->fechar(40);

        $gestor = $this->usuarioComPapel('gestor');
        try {
            app(MarcarPago::class)->handle($fechamento, $gestor);
            $this->fail('Gestor não paga');
        } catch (AuthorizationException) {
            $this->assertSame(FechamentoStatus::Fechado, $fechamento->fresh()->status);
        }

        $financeiro = $this->usuarioComPapel('financeiro', [$this->filial]);
        $pago = app(MarcarPago::class)->handle($fechamento, $financeiro, now()->subDay());

        $this->assertSame(FechamentoStatus::Pago, $pago->status);
        $this->assertSame($financeiro->id, $pago->pago_por);
        $this->assertSame('2026-11-17', $pago->pago_em->toDateString());
        $this->assertSame('1209.22', $pago->valor_a_pagar);

        $this->expectException(ValidationException::class);
        app(MarcarPago::class)->handle($pago, $financeiro);
    }

    public function test_estornar_exige_motivo_e_volta_para_fechada(): void
    {
        $this->semearPlanilha();
        $fechamento = app(MarcarPago::class)->handle($this->fechar(40), $this->admin);

        try {
            app(EstornarPagamento::class)->handle($fechamento, $this->admin, '  ');
            $this->fail('Deveria exigir motivo');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motivo', $e->errors());
        }

        $estornada = app(EstornarPagamento::class)->handle($fechamento, $this->admin, 'Pagamento feito no valor errado');

        $this->assertSame(FechamentoStatus::Fechado, $estornada->status);
        $this->assertNull($estornada->pago_em);
        $this->assertNull($estornada->pago_por);

        $log = Activity::where('description', 'Pagamento estornado')->firstOrFail();
        $this->assertSame('Pagamento feito no valor errado', $log->properties['motivo']);

        $this->expectException(ValidationException::class);
        app(EstornarPagamento::class)->handle($estornada, $this->admin, 'Nada a estornar');
    }

    public function test_reabrir_exige_permissao_motivo_e_semana_nao_paga(): void
    {
        $this->semearPlanilha();
        $fechamento = $this->fechar(40);

        $financeiro = $this->usuarioComPapel('financeiro', [$this->filial]);
        try {
            app(ReabrirSemana::class)->handle($fechamento, $financeiro, 'Conferir valores');
            $this->fail('Financeiro não reabre');
        } catch (AuthorizationException) {
            $this->assertSame(1, FechamentoSemanal::count());
        }

        try {
            app(ReabrirSemana::class)->handle($fechamento, $this->admin, 'x');
            $this->fail('Deveria exigir motivo');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motivo', $e->errors());
        }

        $paga = app(MarcarPago::class)->handle($fechamento, $this->admin);
        try {
            app(ReabrirSemana::class)->handle($paga, $this->admin, 'Corrigir um abastecimento');
            $this->fail('Semana paga precisa de estorno antes');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('estorne', $e->errors()['semana'][0]);
        }

        app(EstornarPagamento::class)->handle($paga, $this->admin, 'Pagamento desfeito');
        app(ReabrirSemana::class)->handle($paga->fresh(), $this->admin, 'Corrigir um abastecimento');

        $this->assertSame(0, FechamentoSemanal::count(), 'o snapshot é removido');
        $this->assertSame('em_andamento', $this->resumo(40)->situacao());

        $log = Activity::where('description', 'Semana reaberta')->firstOrFail();
        $this->assertSame('Corrigir um abastecimento', $log->properties['motivo']);
        $this->assertSame($this->admin->id, $log->causer_id);
        $this->assertSame('1209.22', $log->properties['total_comissao']);
    }

    public function test_reabrir_destrava_os_lancamentos_e_permite_fechar_de_novo(): void
    {
        $this->semearPlanilha();
        $fechamento = $this->fechar(40);
        $viagem = Viagem::query()->whereDate('data_viagem', '2026-10-01')->firstOrFail();
        $this->actingAs($this->admin);
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $viagem));

        app(ReabrirSemana::class)->handle($fechamento, $this->admin, 'Frete lançado com valor errado');

        $this->assertTrue(Gate::forUser($this->admin)->allows('update', $viagem->fresh()), 'a trava em memória não pode ficar velha');
        app(CancelarViagem::class)->handle($viagem->fresh(), $this->admin);

        $novo = $this->fechar(40);
        $this->assertSame(2, $novo->qtd_viagens);
        $this->assertSame('15661.96', $novo->total_faturamento);
    }

    // ----- Telas -----

    public function test_folha_mostra_os_numeros_da_planilha_e_fecha_a_semana(): void
    {
        $this->semearPlanilha();

        $folha = Livewire::actingAs($this->admin)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W42'])
            ->assertSee('Stenyo')
            ->assertSee('22.282,45')
            ->assertSee('1.336,95')
            ->assertSee('8.689,72')
            ->assertSee('12.255,78')
            ->assertSee('Em andamento')
            ->assertSee('Fechar semana');

        $folha->call('fechar')->assertHasNoErrors()->assertSee('Fechada')->assertDontSee('Fechar semana');

        $this->assertSame(FechamentoStatus::Fechado, FechamentoSemanal::firstOrFail()->status);
    }

    public function test_folha_de_semana_fechada_mostra_o_snapshot_e_nao_o_valor_vivo(): void
    {
        $this->semearPlanilha();
        $this->fechar(40);

        // Alteração por fora das regras (ex.: ajuste direto no banco) não muda o que foi fechado.
        Viagem::query()->whereDate('data_viagem', '2026-10-01')->update(['valor_frete' => '1.00', 'comissao_valor' => '0.06']);

        Livewire::actingAs($this->admin)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W40'])
            ->assertSee('20.153,65')
            ->assertSee('11.428,43')
            ->assertSee('Viagens e abastecimentos deste motorista nesta semana estão travados');
    }

    public function test_folha_fluxo_pagar_estornar_e_reabrir_pela_tela(): void
    {
        $this->semearPlanilha();
        $this->fechar(40);
        $folha = Livewire::actingAs($this->admin)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W40']);

        $folha->set('pagoEm', '2026-11-17')->call('marcarPago')->assertHasNoErrors();
        $this->assertSame(FechamentoStatus::Pago, FechamentoSemanal::firstOrFail()->status);

        $folha->call('pedirMotivo', 'estornar')->set('motivo', '')->call('confirmarMotivo')->assertHasErrors('motivo');
        $folha->set('motivo', 'Valor pago errado')->call('confirmarMotivo')->assertHasNoErrors()->assertSet('acaoPendente', null);
        $this->assertSame(FechamentoStatus::Fechado, FechamentoSemanal::firstOrFail()->status);

        $folha->call('pedirMotivo', 'reabrir')->set('motivo', 'Corrigir abastecimento')->call('confirmarMotivo')->assertHasNoErrors();
        $this->assertSame(0, FechamentoSemanal::count());
    }

    public function test_folha_nao_deixa_pagar_com_data_futura(): void
    {
        $this->semearPlanilha();
        $this->fechar(40);

        Livewire::actingAs($this->admin)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W40'])
            ->set('pagoEm', '2026-12-31')->call('marcarPago')->assertHasErrors('pagoEm');

        $this->assertSame(FechamentoStatus::Fechado, FechamentoSemanal::firstOrFail()->status);
    }

    public function test_folha_mostra_o_erro_de_regra_ao_tentar_fechar_com_pendencia(): void
    {
        $this->viagem('2026-10-12', '1000.00', ['status' => ViagemStatus::EmTransito->value]);

        Livewire::actingAs($this->admin)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W42'])
            ->call('fechar')->assertHasErrors('semana')->assertSee('planejada(s) ou em trânsito');
    }

    public function test_folha_para_quem_nao_ve_valores_esconde_frete_comissao_e_liquido(): void
    {
        $this->semearPlanilha();
        $consulta = $this->usuarioComPapel('consulta', [$this->filial]);

        Livewire::actingAs($consulta)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W40'])
            ->assertSee('7.516,00')
            ->assertDontSee('20.153,65')
            ->assertDontSee('1.209,22')
            ->assertDontSee('11.428,43')
            ->assertDontSee('Fechar semana')
            ->assertDontSee('Exportar Excel');
    }

    public function test_folha_e_visao_respeitam_permissao_e_filial(): void
    {
        $this->semearPlanilha();
        $outraFilial = Filial::factory()->create();
        $deOutraFilial = $this->usuarioComPapel('gerente_filial', [$outraFilial]);
        $motoristaApp = $this->usuarioComPapel('motorista', [$this->filial]);
        $url = route('fechamentos.folha', [$this->motorista, '2026-W40']);

        $this->actingAs($deOutraFilial)->get($url)->assertNotFound();
        $this->actingAs($motoristaApp)->get($url)->assertForbidden();
        $this->actingAs($motoristaApp)->get(route('fechamentos.index'))->assertForbidden();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs($this->admin)->get(route('fechamentos.folha', [$this->motorista, '2026-W99']))->assertNotFound();
    }

    public function test_visao_da_semana_lista_motoristas_com_situacao_e_filtra(): void
    {
        $this->semearPlanilha();
        $outro = Motorista::factory()->for($this->filial)->create(['nome' => 'Joaquim']);
        $this->viagem('2026-10-02', '3000.00', ['motorista_id' => $outro->id]);
        $this->fechar(40);

        $semInteresse = Motorista::factory()->for($this->filial)->create(['nome' => 'Sem Movimento']);
        $deOutraFilial = Motorista::factory()->create(['nome' => 'Fantasma']);
        Viagem::factory()->create(['motorista_id' => $deOutraFilial->id, 'filial_id' => $deOutraFilial->filial_id, 'data_viagem' => '2026-10-02']);

        Livewire::actingAs($this->admin)->test(Index::class)->set('semana', '2026-W40')
            ->assertSee('Stenyo')->assertSee('Joaquim')->assertSee('Fantasma')
            ->assertDontSee('Sem Movimento')
            ->set('situacao', 'fechado')->assertSee('Stenyo')->assertDontSee('Joaquim')
            ->set('situacao', 'em_andamento')->assertSee('Joaquim')->assertDontSee('Stenyo')
            ->set('situacao', 'pago')->assertDontSee('Stenyo')->assertDontSee('Joaquim')
            ->set('situacao', 'todas')->set('busca', 'joaq')->assertSee('Joaquim')->assertDontSee('Stenyo');

        // Quem só acessa a filial A não vê a B.
        $gerente = $this->usuarioComPapel('gerente_filial', [$this->filial]);
        Livewire::actingAs($gerente)->test(Index::class)->set('semana', '2026-W40')
            ->assertSee('Stenyo')->assertDontSee('Fantasma');

        $this->assertNotNull($semInteresse);
    }

    public function test_visao_da_semana_navega_entre_semanas(): void
    {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertSee('Semana 46/2026')   // padrão: a semana anterior à atual (47)
            ->call('irParaSemana', '2026-W45')->assertSet('semana', '2026-W45')->assertSee('Semana 45/2026')
            ->call('irParaSemana', 'lixo')->assertSet('semana', '2026-W45');
    }

    // ----- Exportação Excel -----

    public function test_exporta_a_folha_no_layout_da_planilha(): void
    {
        $this->semearPlanilha();

        $resposta = $this->actingAs($this->admin)->get(route('fechamentos.excel', [$this->motorista, '2026-W40']))->assertOk();

        $this->assertStringContainsString('fechamento-stenyo-2026-S40.xlsx', $resposta->headers->get('content-disposition'));

        $arquivo = tempnam(sys_get_temp_dir(), 'fech').'.xlsx';
        file_put_contents($arquivo, $resposta->streamedContent());
        $aba = IOFactory::load($arquivo)->getActiveSheet();
        unlink($arquivo);

        $this->assertSame('MOT. STENYO', $aba->getCell('A1')->getValue());
        $this->assertSame('FATURAMENTO', $aba->getCell('E4')->getValue());
        $this->assertSame('Natal/RN', $aba->getCell('D5')->getValue());
        $this->assertSame(11170.27, (float) $aba->getCell('E5')->getValue());
        $this->assertSame(661429.0, (float) $aba->getCell('N5')->getValue());
        // Linha TOTAL e caixa-resumo.
        $this->assertSame('TOTAL', $aba->getCell('A8')->getValue());
        $this->assertSame(20153.65, round((float) $aba->getCell('E8')->getValue(), 2));
        $this->assertSame(1209.22, round((float) $aba->getCell('F8')->getValue(), 2));
        $this->assertSame(7516.0, round((float) $aba->getCell('J8')->getValue(), 2));
        $this->assertSame(2.4888, round((float) $aba->getCell('Q5')->getValue(), 4));
        $this->assertSame('SEMANA 40', $aba->getCell('A10')->getValue());
        $this->assertSame(11428.43, round((float) $aba->getCell('E11')->getValue(), 2));
        $this->assertSame('MÉDIA KM/L', $aba->getCell('A13')->getValue());
    }

    public function test_exportacao_exige_permissao_de_exportar_e_ver_valores(): void
    {
        $this->semearPlanilha();
        $url = route('fechamentos.excel', [$this->motorista, '2026-W40']);

        $this->actingAs($this->usuarioComPapel('consulta', [$this->filial]))->get($url)->assertForbidden();
        $this->actingAs($this->usuarioComPapel('operacional', [$this->filial]))->get($url)->assertForbidden();
        $this->actingAs($this->usuarioComPapel('financeiro', [$this->filial]))->get($url)->assertOk();
    }
}
