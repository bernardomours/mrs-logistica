<?php

namespace Tests\Feature\Operacao;

use App\Actions\Abastecimentos\CriarAbastecimento;
use App\Actions\Fechamentos\FecharSemana;
use App\Actions\Fechamentos\MarcarPago;
use App\Actions\Motoristas\AtualizarMotorista;
use App\Actions\Motoristas\CriarAcessoDoMotorista;
use App\Actions\Motoristas\RedefinirSenhaDoMotorista;
use App\Actions\Viagens\CancelarViagem;
use App\Actions\Viagens\CriarViagem;
use App\Enums\ViagemStatus;
use App\Livewire\Auth\Login;
use App\Livewire\Motoristas\Index as MotoristasIndex;
use App\Livewire\Portal\Abastecimentos as PortalAbastecimentos;
use App\Livewire\Portal\DadosPessoais;
use App\Livewire\Portal\Dashboard;
use App\Models\Filial;
use App\Models\Local;
use App\Models\Manutencao;
use App\Models\Motorista;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Support\SemanaIso;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Portal do motorista (RN-21): login com CPF, painel somente leitura da semana e isolamento dos dados.
 */
class PortalDoMotoristaTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '52998224725';

    private Filial $filial;

    private Motorista $motorista;

    private Motorista $outro;

    private Veiculo $veiculo;

    private Veiculo $veiculoDoOutro;

    private Local $origem;

    private Local $destino;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');

        $this->filial = Filial::factory()->create();
        $this->motorista = Motorista::factory()->for($this->filial)->create(['nome' => 'Stenyo Silva', 'cpf' => self::CPF, 'comissao_percentual' => '6.00']);
        $this->outro = Motorista::factory()->for($this->filial)->create(['nome' => 'Fantasma Rival', 'cpf' => '11144477735']);
        $this->veiculo = Veiculo::factory()->for($this->filial)->create(['placa' => 'AAA1A11']);
        $this->veiculoDoOutro = Veiculo::factory()->for($this->filial)->create(['placa' => 'BBB2B22']);
        $this->origem = Local::factory()->create(['nome' => 'São Sabino/Apodi']);
        $this->destino = Local::factory()->create(['nome' => 'Natal/RN']);
        $this->admin = $this->usuarioComPapel('admin');
    }

    private function viagem(Motorista $motorista, string $data, string $frete, array $extra = []): void
    {
        app(CriarViagem::class)->handle($extra + [
            'motorista_id' => $motorista->id, 'veiculo_id' => $motorista->is($this->motorista) ? $this->veiculo->id : $this->veiculoDoOutro->id,
            'origem_local_id' => $this->origem->id, 'destino_local_id' => $this->destino->id, 'data_viagem' => $data, 'valor_frete' => $frete,
        ], $this->admin);
    }

    private function abastecer(Motorista $motorista, string $data, string $litros, string $odometro, ?string $valor = null): void
    {
        app(CriarAbastecimento::class)->handle([
            'veiculo_id' => $motorista->is($this->motorista) ? $this->veiculo->id : $this->veiculoDoOutro->id, 'motorista_id' => $motorista->id,
            'data_abastecimento' => $data, 'litros' => $litros, 'preco_litro' => $valor === null ? '6.8' : null, 'valor_total' => $valor, 'odometro_km' => $odometro,
        ], $this->admin);
    }

    /** Semana 40 da planilha para o motorista e um movimento paralelo do outro. */
    private function semearSemana40(): void
    {
        $this->viagem($this->motorista, '2026-09-29', '11170.27');
        $this->viagem($this->motorista, '2026-10-01', '4491.69');
        $this->viagem($this->motorista, '2026-10-03', '4491.69');
        $this->abastecer($this->motorista, '2026-09-29', '400', '661429', '2756.00');
        $this->abastecer($this->motorista, '2026-10-01', '300', '662353');
        $this->abastecer($this->motorista, '2026-10-03', '400', '663112');

        $this->viagem($this->outro, '2026-10-02', '9876.54');
        $this->abastecer($this->outro, '2026-10-02', '111', '5000', '1234.56');
    }

    private function criarAcesso(?Motorista $motorista = null): User
    {
        return app(CriarAcessoDoMotorista::class)->handle($motorista ?? $this->motorista, $this->admin);
    }

    // ----- Criar acesso: CPF como login e senha -----

    public function test_cria_o_acesso_com_cpf_como_login_e_senha(): void
    {
        $acesso = $this->criarAcesso();

        $this->assertSame(self::CPF, $acesso->cpf);
        $this->assertTrue(Hash::check(self::CPF, $acesso->password), 'a senha inicial é o CPF');
        $this->assertNotSame(self::CPF, $acesso->password, 'e fica guardada com hash');
        $this->assertTrue($acesso->ativo);
        $this->assertFalse($acesso->deve_trocar_senha);
        $this->assertTrue($acesso->hasRole('motorista'));
        $this->assertSame($acesso->id, $this->motorista->fresh()->user_id);
        $this->assertSame('Stenyo Silva', $acesso->name);
        $this->assertTrue(Activity::where('description', 'Acesso ao portal criado')->where('subject_id', $this->motorista->id)->exists());
        $this->assertFalse(Activity::query()->get()->contains(fn ($a) => str_contains(json_encode($a->properties), self::CPF) && $a->description === 'Acesso ao portal criado'), 'a senha não vai para a auditoria');
    }

    public function test_pode_exigir_troca_da_senha_no_primeiro_acesso_por_configuracao(): void
    {
        config(['frota.portal_forcar_troca_senha' => true]);
        $acesso = $this->criarAcesso();

        $this->assertTrue($acesso->deve_trocar_senha);
        $this->actingAs($acesso)->get(route('portal.dashboard'))->assertRedirect(route('senha.trocar'));
    }

    public function test_regras_do_criar_acesso(): void
    {
        $this->criarAcesso();

        $erro = function (callable $acao) {
            try {
                $acao();
                $this->fail('Deveria recusar');
            } catch (ValidationException $e) {
                return $e->errors()['acesso'][0];
            }
        };

        $this->assertStringContainsString('já tem acesso', $erro(fn () => $this->criarAcesso()));

        $inativo = Motorista::factory()->for($this->filial)->inativo()->create();
        $this->assertStringContainsString('Ative o motorista', $erro(fn () => $this->criarAcesso($inativo)));

        User::factory()->create(['cpf' => '11144477735']);
        $this->assertStringContainsString('Já existe um usuário com este CPF', $erro(fn () => $this->criarAcesso($this->outro)));
    }

    public function test_so_cria_acesso_quem_tem_permissao_e_acesso_a_filial(): void
    {
        $gerente = $this->usuarioComPapel('gerente_filial', [$this->filial]);
        $deOutraFilial = $this->usuarioComPapel('admin');
        $deOutraFilial->syncRoles([]);
        $deOutraFilial->givePermissionTo('usuarios.criar');
        $deOutraFilial->filiais()->sync([Filial::factory()->create()->id]);

        foreach ([$gerente, $deOutraFilial] as $usuario) {
            try {
                app(CriarAcessoDoMotorista::class)->handle($this->motorista, $usuario);
                $this->fail('Deveria negar');
            } catch (AuthorizationException) {
                $this->assertNull($this->motorista->fresh()->user_id);
            }
        }
    }

    public function test_botoes_da_lista_de_motoristas(): void
    {
        Livewire::actingAs($this->admin)->test(MotoristasIndex::class)
            ->assertSee('Criar acesso')
            ->call('criarAcesso', $this->motorista->id)
            ->assertSee('Redefinir senha');

        $this->assertNotNull($this->motorista->fresh()->user_id);

        // Gerente de filial não tem `usuarios.criar`: não vê o botão e a chamada direta é negada.
        $gerente = $this->usuarioComPapel('gerente_filial', [$this->filial]);
        Livewire::actingAs($gerente)->test(MotoristasIndex::class)
            ->assertDontSee('Criar acesso')
            ->call('criarAcesso', $this->outro->id)->assertForbidden();
        $this->assertNull($this->outro->fresh()->user_id);
    }

    public function test_redefinir_volta_a_senha_para_o_cpf(): void
    {
        $acesso = $this->criarAcesso();
        $acesso->forceFill(['password' => 'OutraSenha9'])->save();
        $this->assertFalse(Hash::check(self::CPF, $acesso->fresh()->password));

        app(RedefinirSenhaDoMotorista::class)->handle($this->motorista, $this->admin);

        $this->assertTrue(Hash::check(self::CPF, $acesso->fresh()->password));
        $this->assertTrue(Activity::where('description', 'Senha do portal redefinida para o CPF')->exists());

        $this->expectException(ValidationException::class);
        app(RedefinirSenhaDoMotorista::class)->handle($this->outro, $this->admin);
    }

    public function test_desativar_o_motorista_bloqueia_o_login_e_reativar_libera(): void
    {
        $acesso = $this->criarAcesso();

        Livewire::actingAs($this->admin)->test(MotoristasIndex::class)->call('alternarAtivo', $this->motorista->id);
        $this->assertFalse($acesso->fresh()->ativo);

        Livewire::test(Login::class)->set('login', self::CPF)->set('password', self::CPF)->call('entrar')->assertHasErrors('login');
        $this->assertNotSame($acesso->id, auth()->id(), 'o motorista inativo não entra');

        Livewire::actingAs($this->admin)->test(MotoristasIndex::class)->call('alternarAtivo', $this->motorista->id);
        $this->assertTrue($acesso->fresh()->ativo);
    }

    public function test_trocar_o_cpf_do_motorista_troca_o_login(): void
    {
        $acesso = $this->criarAcesso();
        $this->actingAs($this->admin);

        app(AtualizarMotorista::class)->handle($this->motorista, [
            'filial_id' => $this->filial->id, 'nome' => 'Stenyo S.', 'cpf' => '39053344705', 'comissao_percentual' => '6.00', 'ativo' => true,
        ]);

        $this->assertSame('39053344705', $acesso->fresh()->cpf);
        $this->assertSame('Stenyo S.', $acesso->fresh()->name);
        $this->assertTrue(Hash::check(self::CPF, $acesso->fresh()->password), 'a senha não muda sozinha');

        User::factory()->create(['cpf' => '11144477735']);
        $this->expectException(ValidationException::class);
        app(AtualizarMotorista::class)->handle($this->motorista, [
            'filial_id' => $this->filial->id, 'nome' => 'Stenyo S.', 'cpf' => '11144477735', 'comissao_percentual' => '6.00', 'ativo' => true,
        ]);
    }

    // ----- Login -----

    public function test_motorista_entra_com_cpf_com_ou_sem_mascara_e_cai_no_portal(): void
    {
        $acesso = $this->criarAcesso();

        foreach ([self::CPF, '529.982.247-25'] as $digitado) {
            Livewire::test(Login::class)->set('login', $digitado)->set('password', self::CPF)
                ->call('entrar')->assertHasNoErrors()->assertRedirect(route('portal.dashboard'));
            $this->assertAuthenticatedAs($acesso);
            auth()->logout();
        }

        Livewire::test(Login::class)->set('login', self::CPF)->set('password', 'errada')->call('entrar')->assertHasErrors('login');
    }

    public function test_motorista_nao_usa_o_painel_administrativo(): void
    {
        $acesso = $this->criarAcesso();

        $this->actingAs($acesso)->get(route('dashboard'))->assertRedirect(route('portal.dashboard'));
        foreach (['viagens.index', 'abastecimentos.index', 'motoristas.index', 'fechamentos.index', 'manutencoes.index', 'tarifas.index', 'veiculos.index', 'filiais.index'] as $rota) {
            $this->actingAs($acesso)->get(route($rota))->assertForbidden();
        }
        // Sem filial, o motorista nem "enxerga" o cadastro: 404 (não vaza que existe).
        $this->actingAs($acesso)->get(route('fechamentos.folha', [$this->motorista, '2026-W40']))->assertNotFound();
        $this->actingAs($acesso)->get(route('fechamentos.excel', [$this->motorista, '2026-W40']))->assertNotFound();
    }

    public function test_usuario_sem_vinculo_com_motorista_nao_entra_no_portal(): void
    {
        $this->actingAs($this->admin)->get(route('portal.dashboard'))->assertForbidden();
        $this->actingAs($this->usuarioComPapel('gestor'))->get(route('portal.dashboard'))->assertForbidden();
    }

    // ----- Painel -----

    public function test_painel_mostra_a_semana_atual_por_padrao(): void
    {
        $acesso = $this->criarAcesso();
        $this->viagem($this->motorista, '2026-11-16', '4000.00');
        $this->abastecer($this->motorista, '2026-11-17', '300', '1000', '2040.00');

        Livewire::actingAs($acesso)->test(Dashboard::class)
            ->assertSee('Olá')->assertSee('Stenyo')
            ->assertSee('Semana 47/2026')->assertSee('16/11/2026 a 22/11/2026')->assertDontSee('Ir para a semana atual')
            ->assertSee('Comissão até agora')->assertSee('240,00')   // 6% de 4.000
            ->assertSee('Em andamento')
            ->assertSee('São Sabino/Apodi')->assertSee('Natal/RN')
            ->assertSee('2.040,00');
    }

    public function test_painel_de_uma_semana_passada_reproduz_os_numeros_da_planilha(): void
    {
        $this->semearSemana40();
        $acesso = $this->criarAcesso();

        Livewire::actingAs($acesso)->test(Dashboard::class)->set('semana', '2026-W40')
            ->assertSee('1.209,22')          // comissão (soma das três viagens)
            ->assertSee('20.153,65')         // frete bruto das próprias viagens
            ->assertSee('7.516,00')          // abastecido
            ->assertSee('1.683')             // km
            ->assertSee('2,49 km/l')         // média simples da semana
            ->assertSee('11.170,27')->assertSee('670,22');
    }

    public function test_painel_nao_mostra_liquido_manutencao_nem_dados_de_outro_motorista(): void
    {
        $this->semearSemana40();
        Manutencao::factory()->daFilial($this->filial)->create(['veiculo_id' => $this->veiculo->id, 'data_manutencao' => '2026-10-02', 'valor_total' => '7777.77', 'descricao' => 'Retifica do motor']);
        $acesso = $this->criarAcesso();

        $tela = Livewire::actingAs($acesso)->test(Dashboard::class)->set('semana', '2026-W40');

        $tela->assertDontSee('11.428,43')            // líquido da empresa
            ->assertDontSee('Líquido')
            ->assertDontSee('7.777,77')->assertDontSee('Retifica')      // manutenção
            ->assertDontSee('Fantasma')->assertDontSee('9.876,54')      // outro motorista
            ->assertDontSee('1.234,56')->assertDontSee('BBB')
            ->assertDontSee('Tarifa');
    }

    public function test_dados_do_outro_motorista_aparecem_so_para_o_dono(): void
    {
        $this->semearSemana40();
        $acessoOutro = $this->criarAcesso($this->outro);

        Livewire::actingAs($acessoOutro)->test(Dashboard::class)->set('semana', '2026-W40')
            ->assertSee('9.876,54')->assertSee('1.234,56')
            ->assertDontSee('20.153,65')->assertDontSee('11.170,27')->assertDontSee('Stenyo');
    }

    public function test_url_nao_consegue_trocar_o_motorista(): void
    {
        $this->semearSemana40();
        $acessoOutro = $this->criarAcesso($this->outro);

        $resposta = $this->actingAs($acessoOutro)->get(route('portal.dashboard', ['semana' => '2026-W40', 'motorista' => $this->motorista->id, 'motorista_id' => $this->motorista->id]));

        $resposta->assertOk()->assertSee('9.876,54', false)->assertDontSee('20.153,65', false)->assertDontSee('Stenyo', false);
    }

    public function test_semana_futura_vira_a_atual_e_o_seletor_navega_para_tras(): void
    {
        $acesso = $this->criarAcesso();

        $tela = Livewire::actingAs($acesso)->test(Dashboard::class)->set('semana', '2027-W10');
        $tela->assertSee('Semana 47/2026')->assertDontSee('Ir para a semana atual');   // futura vira a atual

        $tela->call('irParaSemana', '2026-W46')->assertSet('semana', '2026-W46')
            ->assertSee('Semana 46/2026')->assertSee('09/11/2026 a 15/11/2026')->assertSee('Ir para a semana atual')
            ->call('irParaSemana', '2026-W22')->assertSee('Semana 22/2026')                 // semanas antigas continuam acessíveis
            ->call('irParaSemana', 'lixo')->assertSet('semana', '')->assertSee('Semana 47/2026');
    }

    public function test_cancelada_nao_aparece_e_pendente_nao_soma(): void
    {
        $acesso = $this->criarAcesso();
        $this->viagem($this->motorista, '2026-11-16', '1000.00');
        $this->viagem($this->motorista, '2026-11-17', '8888.00', ['status' => ViagemStatus::Planejada->value]);
        $this->viagem($this->motorista, '2026-11-18', '7777.00');
        $cancelada = Viagem::query()->whereDate('data_viagem', '2026-11-18')->firstOrFail();
        app(CancelarViagem::class)->handle($cancelada, $this->admin);

        $tela = Livewire::actingAs($acesso)->test(Dashboard::class)
            ->assertSee('Planejada')->assertSee('8.888,00')    // a planejada aparece na lista...
            ->assertDontSee('7.777,00');                       // ...a cancelada não

        // ...mas só a concluída soma: 6% de 1.000 (a planejada de 8.888 não entra no total).
        $resumo = $tela->viewData('resumo');
        $this->assertSame('60.00', $resumo->comissao);
        $this->assertSame('1000.00', $resumo->frete);
        $this->assertSame(1, $resumo->qtdViagens);
        $this->assertCount(2, $resumo->viagens);
    }

    public function test_semana_fechada_e_paga_mostram_o_valor_definitivo_e_a_situacao(): void
    {
        $this->semearSemana40();
        $acesso = $this->criarAcesso();
        $semana = SemanaIso::de(2026, 40);
        $fechamento = app(FecharSemana::class)->handle($this->motorista, $semana, $this->admin);

        $tela = Livewire::actingAs($acesso)->test(Dashboard::class)->set('semana', '2026-W40');
        $tela->assertSee('Comissão a receber')->assertSee('aguardando pagamento')->assertSee('1.209,22');

        app(MarcarPago::class)->handle($fechamento, $this->admin, now()->subDay());

        Livewire::actingAs($acesso)->test(Dashboard::class)->set('semana', '2026-W40')
            ->assertSee('Comissão recebida')->assertSee('Paga em 17/11/2026');
    }

    public function test_mostra_o_total_aguardando_pagamento_de_semanas_fechadas(): void
    {
        $this->semearSemana40();
        $acesso = $this->criarAcesso();
        app(FecharSemana::class)->handle($this->motorista, SemanaIso::de(2026, 40), $this->admin);

        Livewire::actingAs($acesso)->test(Dashboard::class)   // semana atual, em andamento
            ->assertSee('Aguardando pagamento (semanas fechadas)')->assertSee('1.209,22');
    }

    public function test_portal_so_tem_leitura(): void
    {
        $acesso = $this->criarAcesso();
        $so = fn (string $classe) => array_values(array_diff(get_class_methods($classe), get_class_methods(Component::class), ['__construct']));

        // Os componentes só expõem ações de navegação (trocar de período): nenhuma escreve no banco.
        $this->assertEqualsCanonicalizing(['mount', 'definirVisao', 'irParaMes', 'irParaSemana', 'render'], $so(Dashboard::class));
        $this->assertEqualsCanonicalizing(['mount', 'irParaSemana', 'render'], $so(PortalAbastecimentos::class));
        $this->assertEqualsCanonicalizing(['mount', 'render'], $so(DadosPessoais::class));

        $this->actingAs($acesso)->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_pagina_do_portal_tem_o_cabecalho_com_sair_e_senha(): void
    {
        $acesso = $this->criarAcesso();

        $this->actingAs($acesso)->get(route('portal.dashboard'))->assertOk()
            ->assertSee('Sair')->assertSee(route('senha.trocar'), false)
            ->assertDontSee('Faturamento', false);
    }

    // ----- Menu lateral e filial -----

    public function test_menu_lateral_tem_as_tres_opcoes_a_filial_e_o_botao_de_abrir_e_fechar(): void
    {
        $this->filial->update(['nome' => 'Filial Mossoró', 'cidade' => 'Mossoró', 'uf' => 'RN']);
        $acesso = $this->criarAcesso();

        $this->actingAs($acesso)->get(route('portal.dashboard'))->assertOk()
            ->assertSee('Dashboard')->assertSee('Abastecimentos')->assertSee('Dados pessoais')
            ->assertSee(route('portal.abastecimentos'), false)->assertSee(route('portal.dados'), false)
            ->assertSee('Filial Mossoró')->assertSee('Mossoró/RN')
            ->assertSee('Abrir ou fechar o menu')->assertSee('Trocar senha');

        // A mesma barra aparece nas outras páginas, com o item atual marcado.
        $this->actingAs($acesso)->get(route('portal.dados'))->assertOk()->assertSee('Filial Mossoró')->assertSee('aria-current="page"', false);
        $this->actingAs($acesso)->get(route('portal.abastecimentos'))->assertOk()->assertSee('Filial Mossoró');
    }

    public function test_so_o_motorista_com_vinculo_entra_nas_tres_paginas(): void
    {
        $acesso = $this->criarAcesso();
        $gestor = $this->usuarioComPapel('gestor');

        foreach (['portal.dashboard', 'portal.abastecimentos', 'portal.dados'] as $rota) {
            $this->actingAs($this->admin)->get(route($rota))->assertForbidden();
            $this->actingAs($gestor)->get(route($rota))->assertForbidden();
            $this->actingAs($acesso)->get(route($rota))->assertOk();
        }

        auth()->logout();

        $this->get(route('portal.abastecimentos'))->assertRedirect(route('login'));
    }

    // ----- Dashboard: mês -----

    public function test_dashboard_do_mes_soma_as_viagens_e_abastecimentos_do_mes(): void
    {
        $this->semearSemana40();
        $acesso = $this->criarAcesso();

        $tela = Livewire::actingAs($acesso)->test(Dashboard::class)
            ->call('definirVisao', 'mes')->set('mes', '2026-10')
            ->assertSee('Outubro de 2026')->assertSee('Comissão do mês')
            ->assertSee('539,00')                 // 269,50 + 269,50 (a viagem de 29/09 é de setembro)
            ->assertSee('8.983,38')               // frete das duas viagens de outubro
            ->assertSee('4.760,00')               // 2.040,00 + 2.720,00 abastecidos em outubro
            ->assertSee('1.683')                  // km: 924 + 759
            ->assertSee('Média do mês')
            ->assertSee('Semana 40')
            ->assertDontSee('11.170,27')->assertDontSee('670,22')                   // viagem de setembro
            ->assertDontSee('9.876,54')->assertDontSee('Fantasma');                 // outro motorista

        $mes = $tela->viewData('mesResumo');
        $this->assertSame(2, $mes->qtdViagens);
        $this->assertSame('539.00', $mes->comissao);
        $this->assertEqualsWithDelta(2.4888, (float) $mes->mediaSimples, 0.0001);

        // Setembro tem só a viagem de 29/09.
        $tela->set('mes', '2026-09')->assertSee('Setembro de 2026')->assertSee('11.170,27')->assertSee('670,22')->assertDontSee('8.983,38');
    }

    public function test_comissao_do_mes_e_repartida_pela_situacao_da_semana(): void
    {
        $this->semearSemana40();
        $acesso = $this->criarAcesso();
        $semana = SemanaIso::de(2026, 40);

        $mes = fn () => Livewire::actingAs($acesso)->test(Dashboard::class)->set('visao', 'mes')->set('mes', '2026-10')->viewData('mesResumo');

        $aberto = $mes();
        $this->assertSame(['0.00', '0.00', '539.00'], [$aberto->comissaoRecebida, $aberto->comissaoAReceber, $aberto->comissaoEmAndamento]);
        $this->assertSame('em_andamento', $aberto->semanas[0]['situacao']);

        $fechamento = app(FecharSemana::class)->handle($this->motorista, $semana, $this->admin);
        $fechado = $mes();
        $this->assertSame(['0.00', '539.00', '0.00'], [$fechado->comissaoRecebida, $fechado->comissaoAReceber, $fechado->comissaoEmAndamento]);
        $this->assertSame('fechado', $fechado->semanas[0]['situacao']);

        app(MarcarPago::class)->handle($fechamento, $this->admin, now()->subDay());
        $pago = $mes();
        $this->assertSame(['539.00', '0.00', '0.00'], [$pago->comissaoRecebida, $pago->comissaoAReceber, $pago->comissaoEmAndamento]);
        $this->assertSame('pago', $pago->semanas[0]['situacao']);

        Livewire::actingAs($acesso)->test(Dashboard::class)->set('visao', 'mes')->set('mes', '2026-10')
            ->assertSee('Recebida')->assertSee('A receber')->assertSee('Paga');
    }

    public function test_mes_futuro_ou_invalido_vira_o_mes_atual_e_visao_invalida_vira_semana(): void
    {
        $acesso = $this->criarAcesso();

        Livewire::actingAs($acesso)->test(Dashboard::class)
            ->call('definirVisao', 'mes')->assertSet('visao', 'mes')
            ->assertSee('Novembro de 2026')
            ->set('mes', '2027-03')->assertSee('Novembro de 2026')
            ->set('mes', 'lixo')->assertSee('Novembro de 2026')
            ->call('irParaMes', '2026-08')->assertSet('mes', '2026-08')->assertSee('Agosto de 2026')
            ->call('irParaMes', '2026-13')->assertSet('mes', '')
            ->call('definirVisao', 'qualquer')->assertSet('visao', 'semana')->assertSee('Semana 47');
    }

    public function test_dashboard_abre_na_semana_e_tem_o_seletor_de_semana_e_de_mes(): void
    {
        $acesso = $this->criarAcesso();

        Livewire::actingAs($acesso)->test(Dashboard::class)
            ->assertSee('Semana 47/2026')->assertSee('16/11/2026 a 22/11/2026')
            ->assertSee('Semana')->assertSee('Mês')
            ->call('irParaSemana', '2026-W46')->assertSee('Semana 46/2026')->assertSee('Ir para a semana atual');
    }

    // ----- Abastecimentos -----

    public function test_abastecimentos_mostra_a_semana_atual_e_troca_de_semana(): void
    {
        $this->semearSemana40();
        $acesso = $this->criarAcesso();
        $this->abastecer($this->motorista, '2026-11-17', '300', '670000', '2040.00');

        Livewire::actingAs($acesso)->test(PortalAbastecimentos::class)
            ->assertSee('Semana 47/2026')->assertSee('2.040,00')->assertSee('670.000,0')
            ->assertDontSee('7.516,00')
            ->call('irParaSemana', '2026-W40')->assertSet('semana', '2026-W40')
            ->assertSee('7.516,00')                 // total abastecido da semana 40
            ->assertSee('661.429,0')->assertSee('663.112,0')->assertSee('2.756,00')
            ->assertSee('R$ 6,890')                 // preço do litro
            ->assertSee('1.683,0')                  // km rodados no total
            ->assertSee('TOTAL')
            ->assertDontSee('1.234,56')->assertDontSee('Fantasma');
    }

    public function test_abastecimentos_de_outro_motorista_nao_aparecem_e_semana_futura_vira_a_atual(): void
    {
        $this->semearSemana40();
        $acessoOutro = $this->criarAcesso($this->outro);

        Livewire::actingAs($acessoOutro)->test(PortalAbastecimentos::class)->set('semana', '2026-W40')
            ->assertSee('1.234,56')->assertSee('5.000,0')->assertDontSee('7.516,00')->assertDontSee('661.429,0');

        Livewire::actingAs($acessoOutro)->test(PortalAbastecimentos::class)->set('semana', '2027-W10')
            ->assertSee('Semana 47/2026')->assertSee('Nenhum abastecimento nesta semana.');
    }

    // ----- Dados pessoais -----

    public function test_dados_pessoais_mostram_o_cadastro_a_filial_e_o_veiculo_ligado(): void
    {
        $this->filial->update(['nome' => 'Filial Mossoró', 'cidade' => 'Mossoró', 'uf' => 'RN', 'telefone' => '(84) 3333-0000']);
        $this->motorista->update([
            'telefone' => '(84) 99999-1111', 'cnh_numero' => '01234567890', 'cnh_categoria' => 'E', 'cnh_validade' => '2030-05-20',
            'data_admissao' => '2024-02-10', 'observacoes' => 'nota interna secreta',
        ]);
        $this->veiculo->update([
            'motorista_padrao_id' => $this->motorista->id, 'tipo' => 'cavalo', 'marca' => 'Volvo', 'modelo' => 'FH 540',
            'ano_fabricacao' => 2020, 'capacidade_tanque_l' => '600', 'renavam' => '99887766554',
        ]);
        $this->veiculoDoOutro->update(['motorista_padrao_id' => $this->outro->id]);
        $acesso = $this->criarAcesso();

        Livewire::actingAs($acesso)->test(DadosPessoais::class)
            ->assertSee('Stenyo Silva')->assertSee('529.982.247-25')->assertSee('(84) 99999-1111')
            ->assertSee('01234567890')->assertSee('categoria E')->assertSee('20/05/2030')
            ->assertSee('10/02/2024')->assertSee('6,00%')->assertSee('Cadastro ativo')
            ->assertSee('Filial Mossoró')->assertSee('Mossoró/RN')->assertSee('(84) 3333-0000')
            ->assertSee('Meu veículo')->assertSee('AAA-1A11')->assertSee('Cavalo mecânico')->assertSee('Volvo FH 540')
            ->assertSee('2020')->assertSee('600 litros')
            ->assertDontSee('Seu cadastro não tem um veículo fixo')
            ->assertDontSee('BBB-2B22')->assertDontSee('Fantasma')                 // veículo e nome de outro motorista
            ->assertDontSee('nota interna secreta')->assertDontSee('99887766554')  // observações e RENAVAM não vão para o motorista
            ->assertDontSee('Vencida');
    }

    public function test_dados_pessoais_avisam_cnh_vencida(): void
    {
        $this->motorista->update(['cnh_validade' => '2026-01-01']);

        Livewire::actingAs($this->criarAcesso())->test(DadosPessoais::class)->assertSee('01/01/2026')->assertSee('Vencida');
    }

    public function test_sem_veiculo_no_cadastro_mostra_o_ultimo_usado(): void
    {
        $this->viagem($this->motorista, '2026-11-10', '1000.00');
        $this->viagem($this->outro, '2026-11-12', '1000.00');
        $acesso = $this->criarAcesso();

        Livewire::actingAs($acesso)->test(DadosPessoais::class)
            ->assertSee('Seu cadastro não tem um veículo fixo')->assertSee('AAA-1A11')->assertDontSee('BBB-2B22');
    }

    public function test_sem_nenhum_veiculo_ou_com_veiculo_inativo_avisa_que_nao_ha(): void
    {
        $this->veiculo->update(['motorista_padrao_id' => $this->motorista->id, 'ativo' => false]);

        Livewire::actingAs($this->criarAcesso())->test(DadosPessoais::class)
            ->assertSee('Nenhum veículo está ligado ao seu cadastro')->assertDontSee('AAA-1A11');
    }
}
