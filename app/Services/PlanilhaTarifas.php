<?php

namespace App\Services;

use App\Support\Numero;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Throwable;

/**
 * Lê e gera a planilha de tarifas de rota: coluna A = origem, B = destino, C = valor do frete.
 * Aceita .xlsx, .xls e .csv; usa a primeira aba; ignora linhas vazias e a linha de cabeçalho.
 */
class PlanilhaTarifas
{
    public const MAX_LINHAS = 2000;

    /**
     * @return array<int, array{linha: int, origem: string, destino: string, valor: ?string, valor_original: string}>
     */
    public function ler(string $caminho, ?string $extensao = null): array
    {
        try {
            $tipo = ['xlsx' => 'Xlsx', 'xls' => 'Xls', 'csv' => 'Csv'][mb_strtolower((string) $extensao)] ?? null;
            $leitor = $tipo !== null ? IOFactory::createReader($tipo) : IOFactory::createReaderForFile($caminho);

            // A extensão informada precisa bater com o conteúdo (um texto qualquer chamado .xlsx não vale).
            if ($tipo !== null && ! $leitor->canRead($caminho)) {
                throw new \RuntimeException('Conteúdo diferente da extensão.');
            }

            if ($leitor instanceof Csv) {
                $leitor->setInputEncoding(Csv::guessEncoding($caminho));
            }

            $leitor->setReadDataOnly(true);
            $celulas = $leitor->load($caminho)->getSheet(0)->toArray(null, true, false, false);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'arquivo' => 'Não foi possível ler a planilha. Envie um arquivo .xlsx, .xls ou .csv válido.',
            ]);
        }

        $linhas = [];

        foreach ($celulas as $indice => $celula) {
            [$origem, $destino, $valor] = [$celula[0] ?? null, $celula[1] ?? null, $celula[2] ?? null];

            if ($this->vazia($origem) && $this->vazia($destino) && $this->vazia($valor)) {
                continue;
            }

            $linha = [
                'linha' => $indice + 1,
                'origem' => trim((string) $origem),
                'destino' => trim((string) $destino),
                'valor' => $this->converterValor($valor),
                'valor_original' => trim((string) $valor),
            ];

            if ($linhas === [] && $this->ehCabecalho($linha)) {
                continue;
            }

            $linhas[] = $linha;
        }

        if (count($linhas) > self::MAX_LINHAS) {
            throw ValidationException::withMessages([
                'arquivo' => 'A planilha tem mais de '.self::MAX_LINHAS.' linhas. Divida o arquivo em partes menores.',
            ]);
        }

        return $linhas;
    }

    /** Planilha-modelo para download. */
    public function modelo(): Spreadsheet
    {
        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet()->setTitle('Tarifas');

        $aba->fromArray([
            ['Origem', 'Destino', 'Valor'],
            ['SÃO SABINO/APODI', 'NATAL/RN', 4491.69],
            ['ARGO FRUIT', 'NATAL/RN', 11170.27],
        ]);

        $aba->getStyle('A1:C1')->getFont()->setBold(true);
        $aba->getStyle('C2:C1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        foreach (['A' => 32, 'B' => 32, 'C' => 16] as $coluna => $largura) {
            $aba->getColumnDimension($coluna)->setWidth($largura);
        }

        $aba->freezePane('A2');

        return $planilha;
    }

    private function vazia(mixed $valor): bool
    {
        return $valor === null || trim((string) $valor) === '';
    }

    /** Número da célula (já numérico) ou texto digitado em pt-BR → decimal com 2 casas. */
    private function converterValor(mixed $valor): ?string
    {
        if (is_int($valor) || is_float($valor)) {
            return number_format($valor, 2, '.', '');
        }

        return Numero::paraDecimal(is_string($valor) ? $valor : null);
    }

    /** @param  array{origem: string, destino: string, valor: ?string, valor_original: string}  $linha */
    private function ehCabecalho(array $linha): bool
    {
        return $linha['valor'] === null
            && (bool) preg_match('/origem|destino|valor|rota/i', $linha['origem'].' '.$linha['destino'].' '.$linha['valor_original']);
    }
}
