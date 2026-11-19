<?php

namespace Tests\Feature;

use App\Models\Concerns\PertenceAFilial;
use App\Models\Filial;
use App\Services\FilialAtual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Modelo de teste: qualquer model futuro com `filial_id` + trait PertenceAFilial se comporta assim (RN-18). */
class RegistroDeTeste extends Model
{
    use PertenceAFilial;

    protected $table = 'registros_de_teste';

    protected $guarded = [];
}

class FilialScopeTest extends TestCase
{
    use RefreshDatabase;

    private Filial $a;

    private Filial $b;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('registros_de_teste', function ($table) {
            $table->id();
            $table->foreignId('filial_id')->nullable();
            $table->string('nome');
            $table->timestamps();
        });

        $this->a = Filial::factory()->create(['nome' => 'A']);
        $this->b = Filial::factory()->create(['nome' => 'B']);

        RegistroDeTeste::create(['nome' => 'da A', 'filial_id' => $this->a->id]);
        RegistroDeTeste::create(['nome' => 'da B', 'filial_id' => $this->b->id]);
    }

    public function test_usuario_de_uma_filial_so_enxerga_a_propria(): void
    {
        $usuario = $this->usuarioComPapel('operacional', [$this->a]);

        $this->actingAs($usuario);

        $this->assertSame(['da A'], RegistroDeTeste::pluck('nome')->all());
    }

    public function test_quem_ve_todas_enxerga_tudo_e_pode_filtrar_pelo_seletor(): void
    {
        $gestor = $this->usuarioComPapel('gestor');
        $this->actingAs($gestor);

        $this->assertCount(2, RegistroDeTeste::all());

        app(FilialAtual::class)->selecionar($this->b->id);
        $this->assertSame(['da B'], RegistroDeTeste::pluck('nome')->all());

        app(FilialAtual::class)->selecionar(null);
        $this->assertCount(2, RegistroDeTeste::all());
    }

    public function test_selecionar_filial_nao_permitida_nao_amplia_o_acesso(): void
    {
        $usuario = $this->usuarioComPapel('operacional', [$this->a]);
        $this->actingAs($usuario);

        app(FilialAtual::class)->selecionar($this->b->id);

        $this->assertSame(['da A'], RegistroDeTeste::pluck('nome')->all());
    }

    public function test_usuario_sem_filial_nao_ve_nada(): void
    {
        $usuario = $this->usuarioComPapel('operacional');
        $this->actingAs($usuario);

        $this->assertCount(0, RegistroDeTeste::all());
    }

    public function test_grava_a_filial_do_seletor_ao_criar(): void
    {
        $usuario = $this->usuarioComPapel('operacional', [$this->a, $this->b]);
        $this->actingAs($usuario);

        app(FilialAtual::class)->selecionar($this->b->id);
        $novo = RegistroDeTeste::create(['nome' => 'novo']);

        $this->assertSame($this->b->id, $novo->filial_id);
    }

    public function test_usa_a_filial_padrao_quando_nao_ha_selecao(): void
    {
        $usuario = $this->usuarioComPapel('operacional', [$this->a, $this->b], ['filial_padrao_id' => $this->a->id]);
        $this->actingAs($usuario);

        $this->assertSame($this->a->id, RegistroDeTeste::create(['nome' => 'padrão'])->filial_id);
    }

    public function test_console_sem_usuario_nao_sofre_restricao(): void
    {
        // Testes rodam no console: jobs/seeders sem usuário autenticado enxergam tudo.
        $this->assertCount(2, RegistroDeTeste::all());
    }
}
