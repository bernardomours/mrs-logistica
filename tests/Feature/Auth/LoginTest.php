<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TrocarSenha;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/painel')->assertRedirect(route('login'));
        $this->get('/')->assertRedirect('/painel');
        $this->get(route('login'))->assertOk();
    }

    public function test_entra_com_email_e_senha(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com', 'password' => 'Senha123']);

        Livewire::test(Login::class)
            ->set('login', 'ana@exemplo.com')
            ->set('password', 'Senha123')
            ->call('entrar')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotNull($usuario->fresh()->ultimo_login_at);
    }

    public function test_campos_de_senha_tem_botao_de_mostrar_e_ocultar(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Mostrar senha')->assertSee('Ocultar senha');

        $this->actingAs(User::factory()->create(['deve_trocar_senha' => false]))
            ->get(route('senha.trocar'))->assertOk()->assertSee('Mostrar senha');
    }

    public function test_motorista_entra_com_cpf_formatado_e_vai_para_o_portal(): void
    {
        $usuario = $this->usuarioComPapel('motorista', [], ['cpf' => '12345678909', 'password' => 'Senha123']);

        Livewire::test(Login::class)
            ->set('login', '123.456.789-09')
            ->set('password', 'Senha123')
            ->call('entrar')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_cpf_so_vale_para_motorista(): void
    {
        $this->usuarioComPapel('admin', [], ['email' => 'chefe@exemplo.com', 'cpf' => '12345678909', 'password' => 'Senha123']);
        $this->usuarioComPapel('gestor', [], ['email' => 'gestor@exemplo.com', 'cpf' => '98765432100', 'password' => 'Senha123']);

        foreach (['123.456.789-09', '98765432100'] as $cpf) {
            Livewire::test(Login::class)->set('login', $cpf)->set('password', 'Senha123')
                ->call('entrar')->assertHasErrors('login')->assertSee('O CPF é só para motoristas');

            $this->assertGuest();
        }
    }

    public function test_motorista_nao_entra_com_email(): void
    {
        $this->usuarioComPapel('motorista', [], ['email' => 'motorista@exemplo.com', 'cpf' => '12345678909', 'password' => 'Senha123']);

        Livewire::test(Login::class)->set('login', 'motorista@exemplo.com')->set('password', 'Senha123')
            ->call('entrar')->assertHasErrors('login')->assertSee('Motorista entra com o CPF');

        $this->assertGuest();
    }

    public function test_equipe_administrativa_entra_com_email_e_cai_no_painel(): void
    {
        foreach (['admin', 'gestor', 'financeiro', 'operacional'] as $papel) {
            $usuario = $this->usuarioComPapel($papel, [], ['email' => "{$papel}@exemplo.com", 'password' => 'Senha123']);

            Livewire::test(Login::class)->set('login', "{$papel}@exemplo.com")->set('password', 'Senha123')
                ->call('entrar')->assertHasNoErrors()->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($usuario);
            auth()->logout();
        }
    }

    public function test_tentativa_na_porta_errada_conta_para_o_limite_e_nao_deixa_sessao(): void
    {
        $this->usuarioComPapel('motorista', [], ['email' => 'motorista@exemplo.com', 'password' => 'Senha123']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('login', 'motorista@exemplo.com')->set('password', 'Senha123')->call('entrar')->assertHasErrors('login');
        }

        Livewire::test(Login::class)->set('login', 'motorista@exemplo.com')->set('password', 'Senha123')
            ->call('entrar')->assertSee('Muitas tentativas');

        $this->assertGuest();
    }

    public function test_rejeita_senha_errada_e_usuario_inativo(): void
    {
        User::factory()->create(['email' => 'ana@exemplo.com', 'password' => 'Senha123']);
        User::factory()->create(['email' => 'off@exemplo.com', 'password' => 'Senha123', 'ativo' => false]);

        Livewire::test(Login::class)
            ->set('login', 'ana@exemplo.com')->set('password', 'errada')
            ->call('entrar')->assertHasErrors('login');

        Livewire::test(Login::class)
            ->set('login', 'off@exemplo.com')->set('password', 'Senha123')
            ->call('entrar')->assertHasErrors('login');

        $this->assertGuest();
    }

    public function test_bloqueia_apos_muitas_tentativas(): void
    {
        User::factory()->create(['email' => 'ana@exemplo.com', 'password' => 'Senha123']);

        $componente = Livewire::test(Login::class)->set('login', 'ana@exemplo.com')->set('password', 'errada');

        foreach (range(1, 5) as $_) {
            $componente->call('entrar');
        }

        $componente->set('password', 'Senha123')->call('entrar')->assertHasErrors('login');
        $this->assertGuest();
    }

    public function test_senha_provisoria_obriga_a_troca_antes_de_qualquer_tela(): void
    {
        $usuario = User::factory()->create(['password' => 'Provisoria1', 'deve_trocar_senha' => true]);

        $this->actingAs($usuario)->get('/painel')->assertRedirect(route('senha.trocar'));

        Livewire::actingAs($usuario)->test(TrocarSenha::class)
            ->set('senha_atual', 'Provisoria1')
            ->set('nova_senha', 'NovaSenha9')
            ->set('nova_senha_confirmation', 'NovaSenha9')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($usuario->fresh()->deve_trocar_senha);
    }

    public function test_senha_nova_precisa_ser_forte_e_diferente(): void
    {
        $usuario = User::factory()->create(['password' => 'Provisoria1']);

        Livewire::actingAs($usuario)->test(TrocarSenha::class)
            ->set('senha_atual', 'Provisoria1')
            ->set('nova_senha', 'curta')
            ->set('nova_senha_confirmation', 'curta')
            ->call('salvar')
            ->assertHasErrors('nova_senha');
    }

    public function test_usuario_desativado_e_deslogado(): void
    {
        $usuario = User::factory()->create(['ativo' => false]);

        $this->actingAs($usuario)->get('/painel')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sair_encerra_a_sessao(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
