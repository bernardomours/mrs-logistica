<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Sino da barra superior: balão com "Nenhuma notificação" enquanto não há itens. */
class SinoDeNotificacoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_painel_mostra_o_sino_com_o_balao_de_nenhuma_notificacao(): void
    {
        $this->actingAs($this->usuarioComPapel('admin'))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notificações"', false)
            ->assertSee('Nenhuma notificação');
    }

    public function test_sem_itens_nao_ha_indicador_e_com_itens_aparecem_a_lista_e_a_bolinha(): void
    {
        $vazio = Blade::render('<x-ui.sino />');
        $this->assertStringContainsString('Nenhuma notificação', $vazio);
        $this->assertStringNotContainsString('bg-red-500', $vazio);

        $cheio = Blade::render('<x-ui.sino :itens="$itens" />', ['itens' => [
            ['titulo' => 'Semana 46 fechada', 'texto' => 'Stenyo', 'quando' => 'há 5 min', 'url' => '/fechamentos'],
        ]]);
        $this->assertStringNotContainsString('Nenhuma notificação', $cheio);
        $this->assertStringContainsString('Semana 46 fechada', $cheio);
        $this->assertStringContainsString('bg-red-500', $cheio);
    }
}
