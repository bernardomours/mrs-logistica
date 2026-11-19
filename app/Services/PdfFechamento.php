<?php

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

/**
 * Gera o PDF do fechamento semanal: uma folha (página) por motorista, no mesmo conteúdo da folha da tela e da
 * planilha. Semana fechada usa o snapshot; em aberto, o cálculo ao vivo (`ResumoDaSemana::totais()`).
 */
class PdfFechamento
{
    /** @param  Collection<int, ResumoDaSemana>  $resumos */
    public function gerar(Collection $resumos, User $emitidoPor): DomPdf
    {
        return Pdf::loadView('pdf.fechamento', [
            'resumos' => $resumos->values(),
            'emitidoPor' => $emitidoPor->name,
            'emitidoEm' => now(),
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/logo-mrs.png'))),
        ])->setPaper('a4', 'landscape')->setOption(['enable_font_subsetting' => true]);
    }
}
