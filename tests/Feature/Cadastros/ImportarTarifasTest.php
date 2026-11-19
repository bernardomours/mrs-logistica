<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Tarifas\ImportarTarifas;
use App\Livewire\Tarifas\Importar;
use App\Models\Cliente;
use App\Models\Local;
use App\Models\TarifaRota;
use App\Services\PlanilhaTarifas;
use App\Support\Texto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as LeitorXlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ImportarTarifasTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<int, array<int, mixed>>  $linhas */
    private function planilha(array $linhas, string $nome = 'tarifas.xlsx'): UploadedFile
    {
        $planilha = new Spreadsheet;
        $planilha->getActiveSheet()->fromArray($linhas);

        $tmp = tempnam(sys_get_temp_dir(), 'xls');
        (new Xlsx($planilha))->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        unlink($tmp);

        return UploadedFile::fake()->createWithContent($nome, $bytes);
    }

    private function tarifa(string $origem, string $destino, string $valor, string $inicio = '2026-01-01', ?string $fim = null, ?Cliente $cliente = null): TarifaRota
    {
        $o = Local::firstOrCreate(['nome_chave' => Texto::chave($origem)], ['nome' => $origem]);
        $d = Local::firstOrCreate(['nome_chave' => Texto::chave($destino)], ['nome' => $destino]);

        return TarifaRota::factory()->create([
            'origem_local_id' => $o->id, 'destino_local_id' => $d->id, 'valor_frete' => $valor,
            'vigencia_inicio' => $inicio, 'vigencia_fim' => $fim, 'cliente_id' => $cliente?->id,
        ]);
    }

    private function importar(array $linhas, array $parametros = [])
    {
        $admin = $this->usuarioComPapel('admin');
        $teste = Livewire::actingAs($admin)->test(Importar::class)
            ->set('arquivo', $this->planilha($linhas))
            ->set('vigencia_inicio', $parametros['inicio'] ?? '2026-11-01');

        if (! empty($parametros['encerrar'])) {
            $teste->set('encerrar_ausentes', true);
        }

        return $teste;
    }

    public function test_so_quem_pode_importar_acessa_a_tela_e_o_modelo(): void
    {
        $this->actingAs($this->usuarioComPapel('operacional'))->get(route('tarifas.importar'))->assertForbidden();
        $this->actingAs($this->usuarioComPapel('financeiro'))->get(route('tarifas.importar'))->assertOk();
    }

    public function test_baixa_a_planilha_modelo(): void
    {
        $resposta = $this->actingAs($this->usuarioComPapel('financeiro'))->get(route('tarifas.modelo'));

        $resposta->assertOk()->assertDownload('modelo-tarifas-de-rota.xlsx');

        $tmp = tempnam(sys_get_temp_dir(), 'mod');
        file_put_contents($tmp, $resposta->streamedContent());
        $linhas = (new PlanilhaTarifas)->ler($tmp);
        unlink($tmp);

        $this->assertSame('SÃO SABINO/APODI', $linhas[0]['origem']);
        $this->assertSame('4491.69', $linhas[0]['valor']);
    }

    public function test_le_cabecalho_valores_numericos_e_texto_em_formato_brasileiro(): void
    {
        $arquivo = $this->planilha([
            ['Origem', 'Destino', 'Valor'],
            ['São Sabino/Apodi', 'Natal/RN', 4491.69],
            ['Argo Fruit', 'Natal/RN', 'R$ 11.170,27'],
            [null, null, null],
            ['Macacos/Quixeré', 'Natal/RN', 'abc'],
        ]);

        $linhas = (new PlanilhaTarifas)->ler($arquivo->getRealPath());

        $this->assertCount(3, $linhas);
        $this->assertSame(2, $linhas[0]['linha']);
        $this->assertSame('4491.69', $linhas[0]['valor']);
        $this->assertSame('11170.27', $linhas[1]['valor']);
        $this->assertNull($linhas[2]['valor']);
        $this->assertSame(5, $linhas[2]['linha']);
    }

    public function test_le_csv_com_ponto_e_virgula(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('t.csv', "Origem;Destino;Valor\nAAA;BBB;1.234,50\nCCC;DDD;99\n");

        $linhas = (new PlanilhaTarifas)->ler($arquivo->getRealPath());

        $this->assertCount(2, $linhas);
        $this->assertSame('1234.50', $linhas[0]['valor']);
        $this->assertSame('99.00', $linhas[1]['valor']);
    }

    public function test_analise_classifica_cada_linha_sem_gravar_nada(): void
    {
        $this->tarifa('São Sabino/Apodi', 'Natal/RN', '4491.69');
        $this->tarifa('Argo Fruit', 'Natal/RN', '11000.00');
        $localsAntes = Local::count();
        $tarifasAntes = TarifaRota::count();

        $teste = $this->importar([
            ['Origem', 'Destino', 'Valor'],
            ['SAO SABINO/APODI', 'natal/rn', 4491.69],       // igual (ignora acento e caixa)
            ['Argo Fruit', 'Natal/RN', 11170.27],            // valor mudou
            ['Macacos/Quixeré', 'Crossdocking/Fortaleza', 2575.23], // nova + 2 locais novos
            ['Natal/RN', 'Natal/RN', 100],                   // origem = destino
            ['Fulano', 'Beltrano', 'abc'],                   // valor inválido
            ['Macacos/Quixeré', 'Crossdocking/Fortaleza', 3000], // rota repetida
            ['', 'Destino sem origem', 100],                 // origem em branco
        ])->call('analisar')->assertHasNoErrors();

        $resumo = $teste->get('analise')['resumo'];
        $this->assertSame(1, $resumo['criar']);
        $this->assertSame(1, $resumo['atualizar']);
        $this->assertSame(1, $resumo['sem_alteracao']);
        $this->assertSame(4, $resumo['erro']);
        $this->assertSame(2, $resumo['locais_novos']);

        $this->assertSame($localsAntes, Local::count());
        $this->assertSame($tarifasAntes, TarifaRota::count());

        $teste->set('mostrar', 'todas')->assertSee('Origem e destino são iguais')->assertSee('Valor inválido')->assertSee('Rota repetida')->assertSee('Origem em branco');
    }

    public function test_confirma_cria_tarifas_e_cadastra_os_locais_que_faltam(): void
    {
        $this->importar([
            ['Origem', 'Destino', 'Valor'],
            ['São Sabino/Apodi', 'Natal/RN', '4.491,69'],
            ['Argo Fruit', 'Natal/RN', 11170.27],
        ])->call('analisar')->call('confirmar')->assertRedirect(route('tarifas.index'));

        $this->assertSame(3, Local::count());
        $this->assertSame(2, TarifaRota::count());

        $tarifa = TarifaRota::whereHas('origem', fn ($q) => $q->where('nome', 'Argo Fruit'))->first();
        $this->assertSame('11170.27', $tarifa->valor_frete);
        $this->assertSame('2026-11-01', $tarifa->vigencia_inicio->toDateString());
        $this->assertNull($tarifa->vigencia_fim);
        $this->assertNull($tarifa->cliente_id);
    }

    public function test_reimportar_o_mesmo_arquivo_nao_altera_nada(): void
    {
        $linhas = [['Origem', 'Destino', 'Valor'], ['A', 'B', 100], ['C', 'D', 200.5]];

        $this->importar($linhas)->call('confirmar');
        $this->assertSame(2, TarifaRota::count());

        $teste = $this->importar($linhas)->call('analisar');
        $this->assertSame(2, $teste->get('analise')['resumo']['sem_alteracao']);
        $this->assertSame(0, $teste->instance()->totalDeMudancas());

        $teste->call('confirmar');
        $this->assertSame(2, TarifaRota::count());
        $this->assertSame(4, Local::count());
    }

    public function test_valor_diferente_encerra_a_tarifa_antiga_na_vespera_e_cria_a_nova(): void
    {
        $antiga = $this->tarifa('A', 'B', '4000.00', '2026-01-01');

        $this->importar([['A', 'B', 4500]], ['inicio' => '2026-11-01'])->call('confirmar');

        $this->assertSame(2, TarifaRota::count());
        $this->assertSame('2026-10-31', $antiga->fresh()->vigencia_fim->toDateString());
        $this->assertSame('4000.00', $antiga->fresh()->valor_frete);

        $nova = TarifaRota::whereKeyNot($antiga->id)->first();
        $this->assertSame('4500.00', $nova->valor_frete);
        $this->assertSame('2026-11-01', $nova->vigencia_inicio->toDateString());
        $this->assertNull($nova->vigencia_fim);

        $this->assertTrue(TarifaRota::vigentesEm('2026-10-15')->whereKey($antiga->id)->exists());
        $this->assertTrue(TarifaRota::vigentesEm('2026-11-15')->whereKey($nova->id)->exists());
    }

    public function test_valor_diferente_na_propria_data_de_inicio_apenas_troca_o_valor(): void
    {
        $tarifa = $this->tarifa('A', 'B', '4000.00', '2026-11-01');

        $this->importar([['A', 'B', 4500]], ['inicio' => '2026-11-01'])->call('confirmar');

        $this->assertSame(1, TarifaRota::count());
        $this->assertSame('4500.00', $tarifa->fresh()->valor_frete);
    }

    public function test_nova_tarifa_herda_o_fim_da_antiga(): void
    {
        $antiga = $this->tarifa('A', 'B', '4000.00', '2026-01-01', '2026-12-31');

        $this->importar([['A', 'B', 4500]], ['inicio' => '2026-06-01'])->call('confirmar');

        $nova = TarifaRota::whereKeyNot($antiga->id)->first();
        $this->assertSame('2026-05-31', $antiga->fresh()->vigencia_fim->toDateString());
        $this->assertSame('2026-12-31', $nova->vigencia_fim->toDateString());
    }

    public function test_encerra_as_tarifas_que_nao_estao_na_planilha_somente_se_pedido(): void
    {
        $presente = $this->tarifa('A', 'B', '100.00');
        $ausente = $this->tarifa('C', 'D', '200.00');
        $futura = $this->tarifa('E', 'F', '300.00', '2027-01-01');

        $teste = $this->importar([['A', 'B', 100]]);
        $teste->call('confirmar');
        $this->assertNull($ausente->fresh()->vigencia_fim, 'sem o marcador, nada é encerrado');

        $teste = $this->importar([['A', 'B', 100]], ['encerrar' => true])->call('analisar');
        $this->assertSame(1, $teste->get('analise')['resumo']['a_encerrar']);
        $teste->assertSee('C → D');
        $teste->call('confirmar');

        $this->assertSame('2026-10-31', $ausente->fresh()->vigencia_fim->toDateString());
        $this->assertNull($presente->fresh()->vigencia_fim);
        $this->assertNull($futura->fresh()->vigencia_fim, 'tarifa que ainda não começou não é tocada');
        $this->assertNull($futura->fresh()->deleted_at);
    }

    public function test_a_tela_nao_tem_cliente_mas_a_action_isola_tarifas_por_cliente(): void
    {
        $cliente = Cliente::factory()->create();
        $generica = $this->tarifa('A', 'B', '100.00');

        $this->importar([['A', 'B', 100]])->assertDontSee('Tarifas para o cliente');

        // Reserva para o futuro: importar para um cliente específico não mexe nas tarifas genéricas.
        app(ImportarTarifas::class)->handle(
            [['linha' => 1, 'origem' => 'A', 'destino' => 'B', 'valor' => '150.00', 'valor_original' => '150']],
            $cliente->id,
            '2026-11-01',
        );

        $this->assertSame('100.00', $generica->fresh()->valor_frete);
        $this->assertNull($generica->fresh()->vigencia_fim);
        $this->assertSame(1, TarifaRota::where('cliente_id', $cliente->id)->count());
        $this->assertSame(2, TarifaRota::count());
    }

    public function test_reaproveita_local_existente_mesmo_inativo_sem_duplicar(): void
    {
        $local = Local::factory()->inativo()->create(['nome' => 'São Sabino/Apodi']);

        $this->importar([['sao sabino/apodi', 'Natal/RN', 100]])->call('confirmar');

        $this->assertSame(2, Local::count());
        $this->assertTrue($local->fresh()->ativo);
        $this->assertSame($local->id, TarifaRota::first()->origem_local_id);
    }

    public function test_linha_com_erro_e_ignorada_e_as_demais_sao_importadas(): void
    {
        $this->importar([['A', 'B', 100], ['C', 'D', 'invalido'], ['E', 'F', 300]])->call('confirmar');

        $this->assertSame(2, TarifaRota::count());
    }

    public function test_tarifa_futura_na_mesma_rota_vira_erro_na_linha(): void
    {
        $this->tarifa('A', 'B', '100.00', '2027-03-01');

        $teste = $this->importar([['A', 'B', 120]], ['inicio' => '2026-11-01'])->call('analisar');

        $this->assertSame(1, $teste->get('analise')['resumo']['erro']);
        $this->assertSame(0, $teste->get('analise')['resumo']['criar']);
        $teste->assertSee('tarifa futura');
        $teste->call('confirmar');
        $this->assertSame(1, TarifaRota::count());
    }

    public function test_arquivo_invalido_e_rejeitado(): void
    {
        $admin = $this->usuarioComPapel('admin');

        Livewire::actingAs($admin)->test(Importar::class)
            ->set('arquivo', UploadedFile::fake()->createWithContent('lixo.xlsx', 'isto não é uma planilha'))
            ->call('analisar')
            ->assertHasErrors('arquivo');

        Livewire::actingAs($admin)->test(Importar::class)
            ->set('arquivo', UploadedFile::fake()->create('doc.pdf', 10))
            ->call('analisar')
            ->assertHasErrors('arquivo');

        Livewire::actingAs($admin)->test(Importar::class)
            ->call('analisar')
            ->assertHasErrors(['arquivo' => 'required']);
    }

    public function test_mudar_os_parametros_invalida_a_previa(): void
    {
        $teste = $this->importar([['A', 'B', 100]])->call('analisar');
        $this->assertNotNull($teste->get('analise'));

        $teste->set('vigencia_inicio', '2026-12-01')->assertSet('analise', null);
    }

    public function test_a_importacao_nao_confia_na_previa_enviada_pelo_navegador(): void
    {
        $teste = $this->importar([['A', 'B', 100]])->call('analisar');

        // Adultera o estado da pré-visualização: a importação deve ignorá-lo e reanalisar o arquivo.
        $teste->set('analise', ['linhas' => [['acao' => 'criar', 'origem' => 'HACK', 'destino' => 'HACK2', 'valor' => '9999.00', 'linha' => 1, 'valor_original' => '', 'valor_atual' => null, 'mensagem' => null, 'locais_novos' => []]], 'resumo' => [], 'ausentes' => []]);
        $teste->call('confirmar');

        $this->assertSame(0, Local::where('nome', 'HACK')->count());
        $this->assertSame('100.00', TarifaRota::first()->valor_frete);
    }

    public function test_registra_a_importacao_na_auditoria(): void
    {
        $this->importar([['A', 'B', 100]])->call('confirmar');

        $log = Activity::where('description', 'Importação de planilha de tarifas')->first();

        $this->assertNotNull($log);
        $this->assertSame(1, $log->properties['criadas']);
    }

    public function test_leitor_de_xlsx_gerado_pelo_modelo_e_valido(): void
    {
        $this->assertInstanceOf(Spreadsheet::class, (new PlanilhaTarifas)->modelo());
        $this->assertTrue(class_exists(LeitorXlsx::class));
    }
}
