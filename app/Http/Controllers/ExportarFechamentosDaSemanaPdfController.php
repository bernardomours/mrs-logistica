<?php

namespace App\Http\Controllers;

use App\Models\FechamentoSemanal;
use App\Services\PdfFechamento;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Baixa um único PDF com a folha de todos os motoristas que tiveram movimento na semana (uma página por motorista). */
class ExportarFechamentosDaSemanaPdfController extends Controller
{
    public function __invoke(string $semana, ResumoSemanal $resumos, PdfFechamento $pdf): Response
    {
        Gate::authorize('exportarTodos', FechamentoSemanal::class);

        $semanaIso = SemanaIso::daChave($semana) ?? abort(404);
        $folhas = $resumos->motoristasComMovimento($semanaIso)
            ->map(fn ($motorista) => $resumos->paraMotorista($motorista, $semanaIso));

        abort_if($folhas->isEmpty(), 404, 'Nenhum motorista teve lançamentos nesta semana.');

        $arquivo = sprintf('fechamento-semana-%d-S%02d.pdf', $semanaIso->ano, $semanaIso->semana);

        return $pdf->gerar($folhas, Auth::user())->download($arquivo);
    }
}
