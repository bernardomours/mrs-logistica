<?php

namespace Tests\Feature\Operacao;

use App\Livewire\Fechamentos\Index as FechamentosIndex;
use App\Livewire\Motoristas\Index as MotoristasIndex;
use App\Livewire\Motoristas\RelatorioSemanal;
use App\Models\Abastecimento;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** PDF do fechamento semanal: por motorista (modal em Motoristas) e de todos os motoristas de uma semana. */
class RelatorioSemanalPdfTest extends TestCase
{
    use RefreshDatabase;

    private const SEMANA = '2026-W42'; // 12/10 a 18/10/2026

    private Filial $filial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-18 10:00:00');
        $this->filial = Filial::factory()->create();
    }

    /** Motorista com uma viagem de R$ 4.491,69 (comissão 6% = 269,50) e um abastecimento de R$ 2.720,00 na semana 42. */
    private function motoristaComMovimento(?Filial $filial = null, string $nome = 'Stenyo'): Motorista
    {
        $filial ??= $this->filial;
        $motorista = Motorista::factory()->for($filial)->create(['nome' => $nome, 'comissao_percentual' => '6.00']);
        $veiculo = Veiculo::factory()->for($filial)->create();

        Viagem::factory()->create([
            'filial_id' => $filial->id, 'motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id,
            'data_viagem' => '2026-10-13', 'valor_frete' => '4491.69', 'comissao_valor' => '269.50',
        ]);
        Abastecimento::factory()->create([
            'filial_id' => $filial->id, 'motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id,
            'data_abastecimento' => '2026-10-14', 'valor_total' => '2720.00',
        ]);

        return $motorista;
    }

    public function test_pdf_do_motorista_e_gerado_com_nome_do_arquivo_e_conteudo_pdf(): void
    {
        $motorista = $this->motoristaComMovimento();

        $resposta = $this->actingAs($this->usuarioComPapel('financeiro', [$this->filial]))
            ->get(route('fechamentos.pdf', [$motorista, self::SEMANA]))->assertOk();

        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString('fechamento-stenyo-2026-S42.pdf', $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_folha_do_pdf_traz_os_totais_da_semana(): void
    {
        $motorista = $this->motoristaComMovimento();
        $resumo = app(ResumoSemanal::class)->paraMotorista($motorista, SemanaIso::daChave(self::SEMANA));

        $html = view('pdf.fechamento', ['resumos' => collect([$resumo]), 'emitidoPor' => 'Maria', 'emitidoEm' => now(), 'logo' => ''])->render();

        $this->assertStringContainsString('Fechamento semanal — Stenyo', $html);
        $this->assertStringContainsString('Semana 42/2026', $html);
        $this->assertStringContainsString('12/10/2026 a 18/10/2026', $html);
        $this->assertStringContainsString('EM ANDAMENTO', $html);
        $this->assertStringContainsString('R$ 4.491,69', $html);   // faturamento
        $this->assertStringContainsString('R$ 269,50', $html);     // comissão
        $this->assertStringContainsString('R$ 2.720,00', $html);   // abastecimento
        $this->assertStringContainsString('R$ 1.502,19', $html);   // líquido = 4.491,69 − 269,50 − 2.720,00
        $this->assertStringContainsString('emitido em 18/11/2026 10:00 por Maria', $html);
    }

    public function test_pdf_do_motorista_sem_lancamentos_na_semana_responde_404(): void
    {
        $motorista = $this->motoristaComMovimento();

        $this->actingAs($this->usuarioComPapel('financeiro', [$this->filial]))
            ->get(route('fechamentos.pdf', [$motorista, '2026-W30']))->assertNotFound();
    }

    public function test_pdf_exige_exportar_e_ver_valores(): void
    {
        $motorista = $this->motoristaComMovimento();
        $url = route('fechamentos.pdf', [$motorista, self::SEMANA]);

        $this->actingAs($this->usuarioComPapel('consulta', [$this->filial]))->get($url)->assertForbidden();
        $this->actingAs($this->usuarioComPapel('operacional', [$this->filial]))->get($url)->assertForbidden();
        $this->actingAs($this->usuarioComPapel('gestor', [$this->filial]))->get($url)->assertOk();
    }

    public function test_motorista_de_outra_filial_nao_gera_pdf(): void
    {
        $outraFilial = Filial::factory()->create();
        $deOutra = $this->motoristaComMovimento($outraFilial, 'De Outra');

        $this->actingAs($this->usuarioComPapel('financeiro', [$this->filial]))
            ->get(route('fechamentos.pdf', [$deOutra, self::SEMANA]))->assertNotFound();
    }

    public function test_pdf_de_todos_junta_so_os_motoristas_das_filiais_do_usuario(): void
    {
        $stenyo = $this->motoristaComMovimento(null, 'Stenyo');
        $maria = $this->motoristaComMovimento(null, 'Maria');
        $this->motoristaComMovimento(Filial::factory()->create(), 'De Outra Filial');
        Motorista::factory()->for($this->filial)->create(['nome' => 'Sem Movimento']);

        $usuario = $this->usuarioComPapel('financeiro', [$this->filial]);
        $this->actingAs($usuario);

        $nomes = app(ResumoSemanal::class)->motoristasComMovimento(SemanaIso::daChave(self::SEMANA))->pluck('nome')->all();
        $this->assertSame(['Maria', 'Stenyo'], $nomes);

        $resposta = $this->get(route('fechamentos.pdf-semana', self::SEMANA))->assertOk();
        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString('fechamento-semana-2026-S42.pdf', $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
        $this->assertNotNull($stenyo);
        $this->assertNotNull($maria);
    }

    public function test_pdf_de_todos_sem_movimento_responde_404_e_exige_permissao(): void
    {
        $this->motoristaComMovimento();

        $this->actingAs($this->usuarioComPapel('financeiro', [$this->filial]))
            ->get(route('fechamentos.pdf-semana', '2026-W30'))->assertNotFound();

        $this->actingAs($this->usuarioComPapel('consulta', [$this->filial]))
            ->get(route('fechamentos.pdf-semana', self::SEMANA))->assertForbidden();
        $this->actingAs($this->usuarioComPapel('operacional', [$this->filial]))
            ->get(route('fechamentos.pdf-semana', self::SEMANA))->assertForbidden();
    }

    public function test_modal_abre_na_semana_anterior_e_mostra_o_link_do_pdf(): void
    {
        $motorista = $this->motoristaComMovimento();

        Livewire::actingAs($this->usuarioComPapel('financeiro', [$this->filial]))->test(RelatorioSemanal::class)
            ->call('abrir', $motorista->id)
            ->assertSet('aberto', true)
            ->assertSet('semana', '2026-W46')
            ->assertSee('Este motorista não tem viagens')
            ->assertDontSee('Baixar PDF')
            ->set('semana', self::SEMANA)
            ->assertSee('Baixar PDF')
            ->assertSee(route('fechamentos.pdf', [$motorista, self::SEMANA]))
            ->assertSee('1 viagem concluída')
            ->call('fechar')->assertSet('aberto', false);
    }

    public function test_modal_recusa_quem_nao_pode_exportar(): void
    {
        $motorista = $this->motoristaComMovimento();

        Livewire::actingAs($this->usuarioComPapel('operacional', [$this->filial]))->test(RelatorioSemanal::class)
            ->call('abrir', $motorista->id)->assertForbidden();
    }

    public function test_botoes_aparecem_so_para_quem_pode_exportar(): void
    {
        $this->motoristaComMovimento();

        Livewire::actingAs($this->usuarioComPapel('financeiro', [$this->filial]))->test(MotoristasIndex::class)
            ->assertSee('Relatório semanal');
        Livewire::actingAs($this->usuarioComPapel('operacional', [$this->filial]))->test(MotoristasIndex::class)
            ->assertDontSee('Relatório semanal');

        Livewire::actingAs($this->usuarioComPapel('financeiro', [$this->filial]))->test(FechamentosIndex::class, ['semana' => self::SEMANA])
            ->assertSee('PDF de todos os motoristas')
            ->assertSee(route('fechamentos.pdf-semana', self::SEMANA));
        Livewire::actingAs($this->usuarioComPapel('consulta', [$this->filial]))->test(FechamentosIndex::class, ['semana' => self::SEMANA])
            ->assertDontSee('PDF de todos os motoristas');
    }

    public function test_botao_de_todos_nao_aparece_em_semana_sem_movimento(): void
    {
        $this->motoristaComMovimento();

        Livewire::actingAs($this->usuarioComPapel('financeiro', [$this->filial]))->test(FechamentosIndex::class, ['semana' => '2026-W30'])
            ->assertDontSee('PDF de todos os motoristas');
    }
}
