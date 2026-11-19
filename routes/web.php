<?php

use App\Http\Controllers\ExportarFechamentoController;
use App\Http\Controllers\ExportarFechamentoPdfController;
use App\Http\Controllers\ExportarFechamentosDaSemanaPdfController;
use App\Http\Controllers\ExportarRelatorioController;
use App\Http\Controllers\ModeloTarifasController;
use App\Livewire\Auth;
use App\Livewire\Dashboard;
use App\Livewire\Fechamentos;
use App\Livewire\Portal;
use App\Livewire\Relatorios;
use App\Livewire\Tarifas;
use App\Livewire\Veiculos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::redirect('/', '/painel');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', Auth\Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', function (Request $request) {
        AuthFacade::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/senha', Auth\TrocarSenha::class)->middleware('usuario.ok')->name('senha.trocar');

    Route::middleware('usuario.ok')->group(function () {
        Route::get('/painel', Dashboard\Index::class)->name('dashboard');

        // Portal do motorista (RN-21): somente leitura, sempre dos dados do próprio usuário
        Route::middleware('can:portal.acessar')->prefix('motorista')->group(function () {
            Route::get('/', Portal\Dashboard::class)->name('portal.dashboard');
            Route::get('/abastecimentos', Portal\Abastecimentos::class)->name('portal.abastecimentos');
            Route::get('/dados-pessoais', Portal\DadosPessoais::class)->name('portal.dados');
        });

        // Cadastros no padrão Index/Create/Edit (CLAUDE.md §8.1):
        // /{modulo} · /{modulo}/criar · /{modulo}/{parametro}/editar  =>  modulo.index|create|edit
        $cadastros = [
            'filiais' => 'filial',
            'motoristas' => 'motorista',
            'veiculos' => 'veiculo',
            'postos' => 'posto',
            'viagens' => 'viagem',
            'abastecimentos' => 'abastecimento',
            'manutencoes' => 'manutencao',
            'tarifas' => 'tarifa',
        ];

        // Importação de tarifas por planilha (planilha de origem, destino e valor)
        Route::get('/tarifas/importar', Tarifas\Importar::class)->name('tarifas.importar');
        Route::get('/tarifas/modelo', ModeloTarifasController::class)->name('tarifas.modelo');

        // Relatórios (Fase 4): hub, relatório com filtro de período e exportação Excel
        Route::middleware('can:relatorios.ver')->group(function () {
            Route::get('/relatorios', Relatorios\Index::class)->name('relatorios.index');
            Route::get('/relatorios/{relatorio}', Relatorios\Show::class)->name('relatorios.show');
            Route::get('/relatorios/{relatorio}/excel', ExportarRelatorioController::class)->name('relatorios.excel');
        });

        // Fechamento semanal: visão da semana, folha do motorista e exportação Excel (RN-11)
        Route::middleware('can:fechamentos.ver')->group(function () {
            Route::get('/fechamentos', Fechamentos\Index::class)->name('fechamentos.index');
            Route::get('/fechamentos/{motorista}/{semana}', Fechamentos\Folha::class)
                ->where('semana', '\d{4}-W\d{2}')->name('fechamentos.folha');
            Route::get('/fechamentos/{motorista}/{semana}/excel', ExportarFechamentoController::class)
                ->where('semana', '\d{4}-W\d{2}')->name('fechamentos.excel');
            Route::get('/fechamentos/{motorista}/{semana}/pdf', ExportarFechamentoPdfController::class)
                ->where('semana', '\d{4}-W\d{2}')->name('fechamentos.pdf');
            Route::get('/fechamentos/{semana}/pdf', ExportarFechamentosDaSemanaPdfController::class)
                ->where('semana', '\d{4}-W\d{2}')->name('fechamentos.pdf-semana');
        });

        foreach ($cadastros as $modulo => $parametro) {
            $namespace = 'App\\Livewire\\'.Str::studly($modulo);

            Route::get("/{$modulo}", "{$namespace}\\Index")->name("{$modulo}.index");
            Route::get("/{$modulo}/criar", "{$namespace}\\Create")->name("{$modulo}.create");
            Route::get("/{$modulo}/{{$parametro}}/editar", "{$namespace}\\Edit")->name("{$modulo}.edit");
        }

        // Ficha do veículo (linha do tempo e alertas de preventiva)
        Route::get('/veiculos/{veiculo}', Veiculos\Show::class)->whereNumber('veiculo')->name('veiculos.show');
    });
});
