<?php

namespace App\Http\Controllers;

use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Services\PlanilhaFechamento;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Baixa a folha da semana de um motorista no layout da planilha original (CLAUDE.md §10). */
class ExportarFechamentoController extends Controller
{
    public function __invoke(Motorista $motorista, string $semana, ResumoSemanal $resumos, PlanilhaFechamento $planilha): StreamedResponse
    {
        Gate::authorize('exportar', [FechamentoSemanal::class, $motorista]);

        $semanaIso = SemanaIso::daChave($semana) ?? abort(404);
        $arquivo = sprintf('fechamento-%s-%d-S%02d.xlsx', Str::slug($motorista->nome), $semanaIso->ano, $semanaIso->semana);

        $documento = $planilha->gerar($resumos->paraMotorista($motorista, $semanaIso));

        return response()->streamDownload(
            fn () => (new Xlsx($documento))->save('php://output'),
            $arquivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
