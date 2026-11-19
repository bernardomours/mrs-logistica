<?php

namespace App\Http\Controllers;

use App\Models\TarifaRota;
use App\Services\PlanilhaTarifas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Baixa a planilha-modelo da importação de tarifas de rota. */
class ModeloTarifasController extends Controller
{
    public function __invoke(Request $request, PlanilhaTarifas $planilha): StreamedResponse
    {
        Gate::authorize('importar', TarifaRota::class);

        return response()->streamDownload(
            fn () => (new Xlsx($planilha->modelo()))->save('php://output'),
            'modelo-tarifas-de-rota.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
