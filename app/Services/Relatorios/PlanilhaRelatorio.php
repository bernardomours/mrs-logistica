<?php

namespace App\Services\Relatorios;

use App\Support\PeriodoRelatorio;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Gera o .xlsx de um relatório: as mesmas colunas, formatos e linha TOTAL da tela. */
class PlanilhaRelatorio
{
    private const FORMATOS = [
        'inteiro' => '#,##0', 'dinheiro' => '#,##0.00', 'decimal1' => '#,##0.0', 'decimal2' => '#,##0.00', 'decimal3' => '#,##0.000',
    ];

    public function gerar(Relatorio $relatorio, PeriodoRelatorio $periodo): Spreadsheet
    {
        $colunas = $relatorio->colunas();
        $linhas = $relatorio->linhas($periodo);

        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet()->setTitle(mb_substr($relatorio->titulo(), 0, 31));

        $aba->setCellValue('A1', $relatorio->titulo());
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $aba->setCellValue('A2', 'Período: '.$periodo->rotulo());

        $cabecalho = 4;
        foreach ($colunas as $i => $coluna) {
            $aba->setCellValue([$i + 1, $cabecalho], $coluna['rotulo']);
        }
        $ultimaColuna = Coordinate::stringFromColumnIndex(count($colunas));
        $estilo = $aba->getStyle("A{$cabecalho}:{$ultimaColuna}{$cabecalho}");
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B1F3B');
        $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $linha = $cabecalho;
        foreach ($linhas as $dados) {
            $linha++;
            foreach ($colunas as $i => $coluna) {
                $this->escrever($aba, $i + 1, $linha, $coluna['tipo'], $dados[$coluna['chave']] ?? null);
            }
        }

        if ($linhas->count() > 1 && collect($colunas)->contains(fn ($c) => $c['somar'] ?? false)) {
            $linha++;
            $totais = Tabela::totais($colunas, $linhas);

            foreach ($colunas as $i => $coluna) {
                $i === 0
                    ? $aba->setCellValue([1, $linha], 'TOTAL')
                    : $this->escrever($aba, $i + 1, $linha, $coluna['tipo'], $totais[$coluna['chave']]);
            }

            $total = $aba->getStyle("A{$linha}:{$ultimaColuna}{$linha}");
            $total->getFont()->setBold(true);
            $total->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3EFFB');
        }

        foreach (range(1, count($colunas)) as $indice) {
            $aba->getColumnDimensionByColumn($indice)->setAutoSize(true);
        }

        return $planilha;
    }

    private function escrever($aba, int $coluna, int $linha, string $tipo, mixed $valor): void
    {
        if ($valor === null || $valor === '') {
            return;
        }

        if (Tabela::ehNumero($tipo)) {
            $aba->setCellValue([$coluna, $linha], (float) $valor);
            $aba->getStyle([$coluna, $linha])->getNumberFormat()->setFormatCode(self::FORMATOS[$tipo]);

            return;
        }

        $aba->setCellValue([$coluna, $linha], Tabela::formatar($tipo, $valor));
    }
}
