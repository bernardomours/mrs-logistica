<?php

namespace App\Services;

use App\Enums\ViagemStatus;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Exporta a folha da semana no layout da planilha original ("PLAN. ENVIO - MOT. X"): viagens à esquerda,
 * abastecimentos à direita, linha TOTAL e caixa-resumo. Só viagens concluídas entram (as canceladas não somam).
 * Semana fechada exporta os totais gravados no snapshot; em aberto, os calculados ao vivo.
 */
class PlanilhaFechamento
{
    private const FORMATO_DINHEIRO = '#,##0.00';

    public function gerar(ResumoDaSemana $resumo): Spreadsheet
    {
        $f = $resumo->fechamento;
        $t = [
            'faturamento' => (string) ($f?->total_faturamento ?? $resumo->faturamento),
            'comissao' => (string) ($f?->total_comissao ?? $resumo->comissao),
            'abastecimento' => (string) ($f?->total_abastecimento ?? $resumo->abastecimento),
            'liquido' => (string) ($f?->total_liquido ?? $resumo->liquido),
            'litros' => (string) ($f?->total_litros ?? $resumo->litros),
            'km' => (string) ($f?->total_km ?? $resumo->km),
            'media' => $f ? $f->media_km_l_simples : $resumo->mediaSimples,
        ];

        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet()->setTitle('Página1');
        $semana = $resumo->semana;

        $aba->setCellValue('A1', 'MOT. '.mb_strtoupper($resumo->motorista->nome));
        $aba->mergeCells('A1:Q1');
        $aba->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $aba->setCellValue('A2', sprintf('SEMANA %d/%d — %s a %s%s', $semana->semana, $semana->ano, $semana->inicio->format('d/m/Y'), $semana->fim->format('d/m/Y'), $f ? ' — '.mb_strtoupper($f->status->rotulo()) : ' — EM ANDAMENTO'));
        $aba->mergeCells('A2:Q2');

        $cabecalho = ['SEMANA', 'DATA', 'ORIGEM', 'DESTINO', 'FATURAMENTO', 'COMISSÃO MOT', '', 'PGTO DATA', 'LITROS', 'VALOR', 'PREÇO/LT', 'POSTO', 'KM INICIAL', 'KM FINAL', 'KM TOTAL', 'MÉDIA', 'MÉDIA DA SEMANA'];
        $aba->fromArray($cabecalho, null, 'A4');
        $estiloCabecalho = $aba->getStyle('A4:Q4');
        $estiloCabecalho->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estiloCabecalho->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B1F3B');
        $estiloCabecalho->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $aba->getStyle('G4')->getFill()->setFillType(Fill::FILL_NONE);

        $viagens = $resumo->viagens->filter(fn ($v) => $v->status === ViagemStatus::Concluida)->values();
        $abastecimentos = $resumo->abastecimentos->values();
        $linhas = max($viagens->count(), $abastecimentos->count(), 1);

        for ($i = 0; $i < $linhas; $i++) {
            $linha = 5 + $i;
            $aba->setCellValue("A{$linha}", $semana->semana);

            if ($viagem = $viagens->get($i)) {
                $aba->setCellValue("B{$linha}", Date::PHPToExcel($viagem->data_viagem));
                $aba->setCellValue("C{$linha}", $viagem->origem->nome);
                $aba->setCellValue("D{$linha}", $viagem->destino->nome);
                $aba->setCellValue("E{$linha}", (float) $viagem->valor_frete);
                $aba->setCellValue("F{$linha}", (float) $viagem->comissao_valor);
            }

            if ($abastecimento = $abastecimentos->get($i)) {
                $aba->setCellValue("H{$linha}", Date::PHPToExcel($abastecimento->data_abastecimento));
                $aba->setCellValue("I{$linha}", (float) $abastecimento->litros);
                $aba->setCellValue("J{$linha}", (float) $abastecimento->valor_total);
                $aba->setCellValue("K{$linha}", (float) $abastecimento->preco_litro);
                $aba->setCellValue("L{$linha}", $abastecimento->posto?->nome);
                $aba->setCellValue("M{$linha}", $abastecimento->km_anterior !== null ? (float) $abastecimento->km_anterior : null);
                $aba->setCellValue("N{$linha}", (float) $abastecimento->odometro_km);
                $aba->setCellValue("O{$linha}", $abastecimento->km_rodados !== null ? (float) $abastecimento->km_rodados : null);
                $aba->setCellValue("P{$linha}", $abastecimento->media_km_l !== null ? (float) $abastecimento->media_km_l : null);
            }
        }

        $ultima = 4 + $linhas;
        $total = $ultima + 1;

        $aba->setCellValue("A{$total}", 'TOTAL');
        $aba->setCellValue("E{$total}", (float) $t['faturamento']);
        $aba->setCellValue("F{$total}", (float) $t['comissao']);
        $aba->setCellValue("I{$total}", (float) $t['litros']);
        $aba->setCellValue("J{$total}", (float) $t['abastecimento']);
        $aba->setCellValue("O{$total}", (float) $t['km']);
        $aba->setCellValue('Q5', $t['media'] !== null ? (float) $t['media'] : null);
        $aba->getStyle("A{$total}:Q{$total}")->getFont()->setBold(true);
        $aba->getStyle("A{$total}:F{$total}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3EFFB');
        $aba->getStyle("H{$total}:Q{$total}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3EFFB');

        // Caixa-resumo da semana
        $caixa = $total + 2;
        $aba->setCellValue("A{$caixa}", 'SEMANA '.$semana->semana);
        $aba->fromArray(['FATURAMENTO', 'COMISSÃO MOT', 'ABASTECIMENTO', 'TOTAL'], null, 'B'.$caixa);
        $aba->fromArray([(float) $t['faturamento'], (float) $t['comissao'], (float) $t['abastecimento'], (float) $t['liquido']], null, 'B'.($caixa + 1));
        $aba->getStyle("A{$caixa}:E{$caixa}")->getFont()->setBold(true);
        $aba->getStyle("A{$caixa}:E".($caixa + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $aba->setCellValue('A'.($caixa + 3), 'MÉDIA KM/L');
        $aba->setCellValue('B'.($caixa + 3), $t['media'] !== null ? (float) $t['media'] : null);
        $aba->getStyle('A'.($caixa + 3))->getFont()->setBold(true);

        // Formatos e larguras
        $aba->getStyle("B5:B{$ultima}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        $aba->getStyle("H5:H{$ultima}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        foreach (["E5:F{$total}", "J5:J{$total}", 'B'.($caixa + 1).':E'.($caixa + 1)] as $faixa) {
            $aba->getStyle($faixa)->getNumberFormat()->setFormatCode(self::FORMATO_DINHEIRO);
        }
        $aba->getStyle("I5:I{$total}")->getNumberFormat()->setFormatCode('#,##0.000');
        $aba->getStyle("K5:K{$ultima}")->getNumberFormat()->setFormatCode('#,##0.000');
        $aba->getStyle("M5:O{$total}")->getNumberFormat()->setFormatCode('#,##0.0');
        $aba->getStyle("P5:Q{$ultima}")->getNumberFormat()->setFormatCode('0.0000');
        $aba->getStyle('B'.($caixa + 3))->getNumberFormat()->setFormatCode('0.0000');

        foreach (['A' => 9, 'B' => 12, 'C' => 26, 'D' => 28, 'E' => 15, 'F' => 15, 'G' => 3, 'H' => 12, 'I' => 11, 'J' => 13, 'K' => 10, 'L' => 24, 'M' => 12, 'N' => 12, 'O' => 11, 'P' => 9, 'Q' => 16] as $coluna => $largura) {
            $aba->getColumnDimension($coluna)->setWidth($largura);
        }

        return $planilha;
    }
}
