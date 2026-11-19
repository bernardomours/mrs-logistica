<?php

namespace App\Http\Controllers;

use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Services\PdfFechamento;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Baixa o PDF da folha da semana de um motorista. */
class ExportarFechamentoPdfController extends Controller
{
    public function __invoke(Motorista $motorista, string $semana, ResumoSemanal $resumos, PdfFechamento $pdf): Response
    {
        Gate::authorize('exportar', [FechamentoSemanal::class, $motorista]);

        $semanaIso = SemanaIso::daChave($semana) ?? abort(404);
        $resumo = $resumos->paraMotorista($motorista, $semanaIso);

        abort_if($resumo->semLancamentos(), 404, 'Este motorista não tem lançamentos nesta semana.');

        $arquivo = sprintf('fechamento-%s-%d-S%02d.pdf', Str::slug($motorista->nome), $semanaIso->ano, $semanaIso->semana);

        return $pdf->gerar(collect([$resumo]), Auth::user())->download($arquivo);
    }
}
