<?php

namespace App\Http\Controllers;

use App\Services\Relatorios\Catalogo;
use App\Services\Relatorios\PlanilhaRelatorio;
use App\Support\PeriodoRelatorio;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Baixa um relatório em Excel. Exige `relatorios.exportar` e as mesmas permissões financeiras da tela (RN-15). */
class ExportarRelatorioController extends Controller
{
    public function __invoke(Request $request, string $relatorio, Catalogo $catalogo, PlanilhaRelatorio $planilha): StreamedResponse
    {
        $definicao = $catalogo->porChave($relatorio) ?? abort(404);

        abort_unless($request->user()->can('relatorios.exportar') && $catalogo->podeVer($request->user(), $definicao), 403);

        $atalho = array_key_exists((string) $request->query('periodo'), PeriodoRelatorio::ATALHOS) ? (string) $request->query('periodo') : 'mes_atual';
        $periodo = PeriodoRelatorio::deAtalho($atalho, $request->query('de'), $request->query('ate'));

        $documento = $planilha->gerar($definicao, $periodo);

        return response()->streamDownload(
            fn () => (new Xlsx($documento))->save('php://output'),
            sprintf('%s-%s-a-%s.xlsx', $relatorio, $periodo->de(), $periodo->ate()),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
