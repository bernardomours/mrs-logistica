<?php

namespace Tests\Feature\Operacao;

use App\Actions\Abastecimentos\CriarAbastecimento;
use App\Actions\Fechamentos\FecharSemana;
use App\Actions\Manutencoes\AtualizarManutencao;
use App\Actions\Manutencoes\ExcluirManutencao;
use App\Actions\Manutencoes\RegistrarManutencao;
use App\Enums\ManutencaoTipo;
use App\Livewire\Fechamentos\Folha;
use App\Livewire\Manutencoes\Create;
use App\Livewire\Manutencoes\Edit;
use App\Livewire\Manutencoes\Index;
use App\Livewire\Veiculos\Show;
use App\Models\Abastecimento;
use App\Models\Filial;
use App\Models\Manutencao;
use App\Models\Motorista;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\AlertasDePreventiva;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ManutencoesTest extends TestCase
{
    use RefreshDatabase;

    private Filial $filial;

    private Motorista $motorista;

    private Veiculo $veiculo;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');

        $this->filial = Filial::factory()->create();
        $this->motorista = Motorista::factory()->for($this->filial)->create(['nome' => 'Stenyo']);
        $this->veiculo = Veiculo::factory()->for($this->filial)->create(['placa' => 'ABC1D23', 'odometro_inicial' => '100000.0']);
        $this->admin = $this->usuarioComPapel('admin');
    }

    /** @param  array<string, mixed>  $extra */
    private function dados(array $extra = []): array
    {
        return $extra + [
            'veiculo_id' => $this->veiculo->id, 'data_manutencao' => '2026-10-05', 'tipo' => 'preventiva',
            'descricao' => 'Troca de óleo e filtros', 'valor_pecas' => '800.00', 'valor_mao_obra' => '200.50',
        ];
    }

    private function registrar(array $extra = [], ?User $usuario = null): Manutencao
    {
        return app(RegistrarManutencao::class)->handle($this->dados($extra), $usuario ?? $this->admin);
    }

    private function rejeita(callable $acao, string $campo): string
    {
        try {
            $acao();
            $this->fail("Deveria rejeitar ({$campo})");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());

            return $e->errors()[$campo][0];
        }
    }

    // ----- Registro (RN-20) -----

    public function test_registra_com_total_calculado_filial_do_veiculo_e_auditoria(): void
    {
        $manutencao = $this->registrar(['oficina' => 'Oficina Central', 'odometro_km' => '103500.5']);

        $this->assertSame('1000.50', $manutencao->valor_total);
        $this->assertSame('800.00', $manutencao->valor_pecas);
        $this->assertSame($this->filial->id, $manutencao->filial_id);
        $this->assertSame(ManutencaoTipo::Preventiva, $manutencao->tipo);
        $this->assertSame($this->admin->id, $manutencao->created_by);
        $this->assertNull($manutencao->motorista_id);
        $this->assertSame('103500.5', $manutencao->odometro_km);

        $this->assertSame(1, Activity::where('log_name', 'manutencoes')->where('subject_id', $manutencao->id)->count());
    }

    public function test_total_ignora_o_que_vier_no_array_e_so_pecas_ou_so_mao_de_obra_servem(): void
    {
        $so = $this->registrar(['valor_pecas' => '0', 'valor_mao_obra' => '350.00', 'valor_total' => '1.00']);

        $this->assertSame('350.00', $so->valor_total);
    }

    public function test_regras_de_validacao_da_action(): void
    {
        $this->rejeita(fn () => $this->registrar(['valor_pecas' => '0', 'valor_mao_obra' => '0']), 'valor_pecas');
        $this->rejeita(fn () => $this->registrar(['valor_pecas' => '-5']), 'valor_pecas');
        $this->rejeita(fn () => $this->registrar(['tipo' => 'inexistente']), 'tipo');
        $this->rejeita(fn () => $this->registrar(['veiculo_id' => 9999]), 'veiculo_id');
        $this->rejeita(fn () => $this->registrar(['proxima_data' => '2026-10-01']), 'proxima_data');
        $this->rejeita(fn () => $this->registrar(['odometro_km' => '103000.0', 'proxima_km' => '103000.0']), 'proxima_km');

        $this->veiculo->update(['ativo' => false]);
        $this->rejeita(fn () => $this->registrar(), 'veiculo_id');
    }

    public function test_motorista_e_opcional_mas_precisa_ser_da_filial_do_veiculo_e_ativo(): void
    {
        $this->assertSame($this->motorista->id, $this->registrar(['motorista_id' => $this->motorista->id])->motorista_id);

        $deOutraFilial = Motorista::factory()->create();
        $this->assertStringContainsString('mesma filial', $this->rejeita(fn () => $this->registrar(['motorista_id' => $deOutraFilial->id]), 'motorista_id'));

        $inativo = Motorista::factory()->for($this->filial)->create(['ativo' => false]);
        $this->rejeita(fn () => $this->registrar(['motorista_id' => $inativo->id]), 'motorista_id');
    }

    public function test_usuario_sem_acesso_a_filial_do_veiculo_nao_registra(): void
    {
        $outra = Filial::factory()->create();
        $gerente = $this->usuarioComPapel('gerente_filial', [$outra]);

        $this->rejeita(fn () => $this->registrar([], $gerente), 'veiculo_id');
    }

    public function test_atualiza_recalcula_o_total_e_exclui_logicamente(): void
    {
        $manutencao = $this->registrar();

        $atualizada = app(AtualizarManutencao::class)->handle($manutencao, $this->dados(['valor_pecas' => '1000.00', 'valor_mao_obra' => '500.00', 'tipo' => 'corretiva']), $this->admin);

        $this->assertSame('1500.00', $atualizada->valor_total);
        $this->assertSame(ManutencaoTipo::Corretiva, $atualizada->tipo);
        $this->assertSame($this->admin->id, $atualizada->updated_by);

        app(ExcluirManutencao::class)->handle($atualizada, $this->admin);

        $this->assertSoftDeleted('manutencoes', ['id' => $manutencao->id]);
        $this->assertTrue(Activity::where('log_name', 'manutencoes')->where('subject_id', $manutencao->id)->where('event', 'deleted')->exists());
    }

    public function test_manutencao_nao_altera_a_cadeia_de_hodometro_nem_o_liquido_da_semana(): void
    {
        $abastecer = fn (string $data, string $odo) => app(CriarAbastecimento::class)->handle([
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $this->motorista->id, 'data_abastecimento' => $data,
            'litros' => '300', 'preco_litro' => '6.8', 'odometro_km' => $odo,
        ], $this->admin);

        $abastecer('2026-10-01', '100924');
        $segundo = $abastecer('2026-10-07', '101683');
        $antes = $this->resumo(41);

        // Manutenção com hodômetro entre os dois abastecimentos e na mesma semana.
        $this->registrar(['data_manutencao' => '2026-10-06', 'odometro_km' => '101200.0', 'motorista_id' => $this->motorista->id]);

        $segundo->refresh();
        $this->assertSame('100924.0', $segundo->km_anterior);
        $this->assertSame('759.0', $segundo->km_rodados);

        $depois = $this->resumo(41);
        $this->assertSame($antes->liquido, $depois->liquido);
        $this->assertSame($antes->abastecimento, $depois->abastecimento);
        $this->assertSame($antes->mediaSimples, $depois->mediaSimples);
        $this->assertSame('1000.50', $depois->totalManutencao, 'aparece como informação na semana');
        $this->assertCount(1, $depois->manutencoes);
    }

    private function resumo(int $semana)
    {
        return app(ResumoSemanal::class)->paraMotorista($this->motorista, SemanaIso::de(2026, $semana));
    }

    // ----- Trava de semana (RN-20 + RN-11) -----

    public function test_semana_fechada_trava_manutencao_do_motorista_mas_nao_a_do_veiculo_sozinho(): void
    {
        app(CriarAbastecimento::class)->handle([
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $this->motorista->id, 'data_abastecimento' => '2026-10-01',
            'litros' => '300', 'preco_litro' => '6.8', 'odometro_km' => '100924',
        ], $this->admin);
        $doMotorista = $this->registrar(['motorista_id' => $this->motorista->id, 'data_manutencao' => '2026-10-02']);
        $semMotorista = $this->registrar(['data_manutencao' => '2026-10-02']);

        app(FecharSemana::class)->handle($this->motorista, SemanaIso::de(2026, 40), $this->admin);
        $this->actingAs($this->admin);

        $this->rejeita(fn () => $this->registrar(['motorista_id' => $this->motorista->id, 'data_manutencao' => '2026-10-03']), 'data_manutencao');
        $this->rejeita(fn () => app(AtualizarManutencao::class)->handle($doMotorista, $this->dados(['motorista_id' => $this->motorista->id, 'data_manutencao' => '2026-10-02', 'descricao' => 'x']), $this->admin), 'data_manutencao');
        $this->rejeita(fn () => app(ExcluirManutencao::class)->handle($doMotorista, $this->admin), 'data_manutencao');

        $this->assertFalse(Gate::allows('update', $doMotorista));
        $this->assertFalse(Gate::allows('delete', $doMotorista));
        $this->assertTrue(Gate::allows('update', $semMotorista), 'sem motorista não há semana de acerto a travar');
        $this->assertNotNull($this->registrar(['data_manutencao' => '2026-10-03']));
    }

    // ----- Permissões -----

    public function test_permissoes_por_papel(): void
    {
        $manutencao = $this->registrar();

        $operacional = $this->usuarioComPapel('operacional', [$this->filial]);
        $consulta = $this->usuarioComPapel('consulta', [$this->filial]);
        $financeiro = $this->usuarioComPapel('financeiro', [$this->filial]);
        $motoristaApp = $this->usuarioComPapel('motorista', [$this->filial]);

        $this->actingAs($operacional);
        $this->assertTrue(Gate::allows('create', Manutencao::class));
        $this->assertTrue(Gate::allows('update', $manutencao));
        $this->assertFalse(Gate::allows('delete', $manutencao), 'operacional não exclui');

        $this->actingAs($financeiro);
        $this->assertTrue(Gate::allows('viewAny', Manutencao::class));
        $this->assertFalse(Gate::allows('create', Manutencao::class));

        $this->actingAs($consulta);
        $this->assertTrue(Gate::allows('viewAny', Manutencao::class));
        $this->assertFalse(Gate::allows('create', Manutencao::class));
        $this->assertFalse(Gate::allows('update', $manutencao));

        $this->actingAs($motoristaApp)->get(route('manutencoes.index'))->assertForbidden();
    }

    public function test_registro_de_outra_filial_responde_404(): void
    {
        $outra = Manutencao::factory()->daFilial(Filial::factory()->create())->create();
        $gerente = $this->usuarioComPapel('gerente_filial', [$this->filial]);

        $this->actingAs($gerente)->get(route('manutencoes.edit', $outra))->assertNotFound();
    }

    // ----- Alertas de preventiva (RN-13) -----

    private function alertas(?array $veiculos = null)
    {
        $this->actingAs($this->admin);

        return app(AlertasDePreventiva::class)->handle($veiculos);
    }

    private function abastecerFactory(string $odometro): void
    {
        Abastecimento::factory()->daFilial($this->filial)->create([
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $this->motorista->id, 'odometro_km' => $odometro,
        ]);
    }

    public function test_alerta_por_quilometragem(): void
    {
        $this->registrar(['odometro_km' => '100000.0', 'proxima_km' => '110000.0']);

        $this->assertCount(0, $this->alertas(), 'hodômetro em 100.000: ainda longe');

        $this->abastecerFactory('109500.0');
        $proxima = $this->alertas()->first();
        $this->assertSame('proxima', $proxima['situacao']);
        $this->assertStringContainsString('Faltam 500,0 km', $proxima['motivos'][0]);

        $this->abastecerFactory('110100.0');
        $vencida = $this->alertas()->first();
        $this->assertSame('vencida', $vencida['situacao']);
        $this->assertStringContainsString('Passou de 110.000,0 km', $vencida['motivos'][0]);
    }

    public function test_alerta_por_data(): void
    {
        $this->registrar(['proxima_data' => '2026-12-31']);
        $this->assertCount(0, $this->alertas(), 'faltam mais de 15 dias');

        $m = $this->registrar(['data_manutencao' => '2026-10-10', 'proxima_data' => '2026-11-28']);
        $this->assertSame('proxima', $this->alertas()->first()['situacao'], '10 dias');

        $m->update(['proxima_data' => '2026-11-18']);
        $this->assertStringContainsString('Vence hoje', $this->alertas()->first()['motivos'][0]);

        $m->update(['proxima_data' => '2026-11-10']);
        $vencida = $this->alertas()->first();
        $this->assertSame('vencida', $vencida['situacao']);
        $this->assertStringContainsString('Venceu em 10/11/2026', $vencida['motivos'][0]);
    }

    public function test_nova_manutencao_com_proxima_substitui_o_alerta_antigo_e_veiculo_inativo_nao_alerta(): void
    {
        $this->registrar(['data_manutencao' => '2026-09-01', 'proxima_data' => '2026-10-01']);
        $this->assertSame('vencida', $this->alertas()->first()['situacao']);

        $this->registrar(['data_manutencao' => '2026-10-15', 'proxima_data' => '2027-03-01']);
        $this->assertCount(0, $this->alertas(), 'a mais recente com "próxima" é que vale');

        $this->registrar(['data_manutencao' => '2026-10-20', 'proxima_data' => '2026-11-01']);
        $this->assertCount(1, $this->alertas());
        $this->veiculo->update(['ativo' => false]);
        $this->assertCount(0, $this->alertas());
    }

    public function test_alertas_podem_ser_limitados_a_um_veiculo(): void
    {
        $outro = Veiculo::factory()->for($this->filial)->create();
        $this->registrar(['proxima_data' => '2026-11-01']);
        $this->registrar(['veiculo_id' => $outro->id, 'proxima_data' => '2026-11-01']);

        $this->assertCount(2, $this->alertas());
        $this->assertCount(1, $this->alertas([$outro->id]));
    }

    // ----- Telas -----

    public function test_cadastra_pela_tela_e_mostra_o_total_e_o_aviso_de_hodometro(): void
    {
        $this->abastecerFactory('105000.0');

        $tela = Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)
            ->set('form.motorista_id', (string) $this->motorista->id)
            ->set('form.data_manutencao', '2026-10-05')
            ->set('form.tipo', 'corretiva')
            ->set('form.descricao', 'Troca de embreagem')
            ->set('form.valor_pecas', '1.200,50')
            ->set('form.valor_mao_obra', '300')
            ->set('form.odometro_km', '90.000,0');

        $tela->assertSee('1.500,50')->assertSee('menor que o último abastecimento');

        $tela->call('salvar')->assertHasNoErrors()->assertRedirect(route('manutencoes.index'));

        $m = Manutencao::firstOrFail();
        $this->assertSame('1500.50', $m->valor_total);
        $this->assertSame('90000.0', $m->odometro_km, 'o aviso não bloqueia');
        $this->assertSame($this->motorista->id, $m->motorista_id);
    }

    public function test_tela_valida_campos_e_mostra_erro_de_regra(): void
    {
        Livewire::actingAs($this->admin)->test(Create::class)
            ->call('salvar')->assertHasErrors(['form.veiculo_id', 'form.descricao']);

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)->set('form.descricao', 'Algo')
            ->call('salvar')->assertHasErrors(['form.valor_pecas']);

        $this->assertSame(0, Manutencao::count());
    }

    public function test_trocar_o_veiculo_limpa_motorista_de_outra_filial(): void
    {
        $outraFilial = Filial::factory()->create();
        $outroVeiculo = Veiculo::factory()->for($outraFilial)->create();

        Livewire::actingAs($this->admin)->test(Create::class)
            ->set('form.veiculo_id', (string) $this->veiculo->id)->set('form.motorista_id', (string) $this->motorista->id)
            ->set('form.veiculo_id', (string) $outroVeiculo->id)->assertSet('form.motorista_id', '');
    }

    public function test_edita_pela_tela(): void
    {
        $manutencao = $this->registrar();

        Livewire::actingAs($this->admin)->test(Edit::class, ['manutencao' => $manutencao])
            ->assertSet('form.valor_pecas', '800,00')->assertSet('form.tipo', 'preventiva')
            ->set('form.descricao', 'Revisão geral')->set('form.valor_mao_obra', '400')
            ->call('salvar')->assertHasNoErrors()->assertRedirect(route('manutencoes.index'));

        $this->assertSame('Revisão geral', $manutencao->fresh()->descricao);
        $this->assertSame('1200.00', $manutencao->fresh()->valor_total);
    }

    public function test_lista_filtra_totaliza_alerta_e_exclui(): void
    {
        // A oficina só aparece na linha da tabela (o banner de alerta repete a descrição).
        $this->registrar(['descricao' => 'Troca de óleo', 'oficina' => 'Oficina Alfa', 'proxima_data' => '2026-11-01']);
        $pneus = $this->registrar(['tipo' => 'pneus', 'descricao' => 'Quatro pneus', 'valor_pecas' => '4000', 'valor_mao_obra' => '0', 'data_manutencao' => '2026-09-10']);

        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertSee('Oficina Alfa')->assertSee('Quatro pneus')->assertSee('5.000,50')
            ->assertSee('preventiva vencida')
            ->set('status', 'pneus')->assertSee('Quatro pneus')->assertDontSee('Oficina Alfa')
            ->set('status', 'todos')->set('busca', 'óleo')->assertSee('Oficina Alfa')->assertDontSee('Quatro pneus')
            ->set('busca', '')->set('de', '2026-10-01')->assertDontSee('Quatro pneus')
            ->set('de', '')->call('excluir', $pneus->id)->assertDontSee('Quatro pneus');

        $this->assertSoftDeleted('manutencoes', ['id' => $pneus->id]);
    }

    public function test_lista_esconde_valores_de_quem_nao_pode_ve_los(): void
    {
        $this->registrar(['valor_pecas' => '1234.56', 'valor_mao_obra' => '0']);
        $consulta = $this->usuarioComPapel('consulta', [$this->filial]);

        Livewire::actingAs($consulta)->test(Index::class)
            ->assertSee('Troca de óleo e filtros')
            ->assertDontSee('1.234,56')
            ->assertDontSee('Nova manutenção');
    }

    public function test_ficha_do_veiculo_mostra_linha_do_tempo_alerta_e_respeita_filial(): void
    {
        $this->abastecerFactory('105000.0');
        $this->registrar(['descricao' => 'Troca de pastilhas', 'proxima_data' => '2026-11-01']);

        Livewire::actingAs($this->admin)->test(Show::class, ['veiculo' => $this->veiculo])
            ->assertSee('ABC-1D23')->assertSee('Troca de pastilhas')->assertSee('Abastecimento')
            ->assertSee('105.000,0 km')->assertSee('preventiva vencida')->assertSee('1.000,50');

        $consulta = $this->usuarioComPapel('consulta', [$this->filial]);
        Livewire::actingAs($consulta)->test(Show::class, ['veiculo' => $this->veiculo])
            ->assertSee('Troca de pastilhas')->assertDontSee('1.000,50');

        $deOutra = $this->usuarioComPapel('gerente_filial', [Filial::factory()->create()]);
        $this->actingAs($deOutra)->get(route('veiculos.show', $this->veiculo))->assertNotFound();
        $this->actingAs($this->admin)->get(route('veiculos.show', $this->veiculo))->assertOk();
        $this->actingAs($this->admin)->get(route('veiculos.create'))->assertOk();
    }

    public function test_folha_do_fechamento_mostra_a_manutencao_como_informativo(): void
    {
        app(CriarAbastecimento::class)->handle([
            'veiculo_id' => $this->veiculo->id, 'motorista_id' => $this->motorista->id, 'data_abastecimento' => '2026-10-01',
            'litros' => '300', 'preco_litro' => '6.8', 'odometro_km' => '100924',
        ], $this->admin);
        $this->registrar(['descricao' => 'Troca de correia', 'data_manutencao' => '2026-10-02']);
        $this->registrar(['descricao' => 'Fora da semana', 'data_manutencao' => '2026-10-20']);

        Livewire::actingAs($this->admin)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W40'])
            ->assertSee('Manutenção no período')->assertSee('Troca de correia')->assertSee('1.000,50')
            ->assertDontSee('Fora da semana');

        $consulta = $this->usuarioComPapel('consulta', [$this->filial]);
        Livewire::actingAs($consulta)->test(Folha::class, ['motorista' => $this->motorista, 'semana' => '2026-W40'])
            ->assertSee('Troca de correia')->assertDontSee('1.000,50');
    }

    public function test_acao_exige_permissao_ao_chamar_direto(): void
    {
        $this->expectException(AuthorizationException::class);

        Gate::forUser($this->usuarioComPapel('consulta', [$this->filial]))->authorize('create', Manutencao::class);
    }
}
