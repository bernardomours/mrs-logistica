<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Texto
{
    /**
     * Chave de comparação: maiúsculas, sem acento, espaços normalizados.
     * "  São  Sabino/Apodi " => "SAO SABINO/APODI". Usada em `nome_chave` (locais, postos) — RN-14.
     */
    public static function chave(string $texto): string
    {
        $semAcento = Str::ascii($texto);

        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', $semAcento)));
    }

    /** "12345678909" => "123.456.789-09" (devolve o original se não tiver 11 dígitos). */
    public static function cpf(?string $cpf): string
    {
        $d = self::digitos($cpf);

        return strlen($d) === 11
            ? substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9)
            : (string) $cpf;
    }

    /** "11222333000181" => "11.222.333/0001-81" (devolve o original se não tiver 14 dígitos). */
    public static function cnpj(?string $cnpj): string
    {
        $d = self::digitos($cnpj);

        return strlen($d) === 14
            ? substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12)
            : (string) $cnpj;
    }

    /** Somente dígitos ("123.456.789-09" => "12345678909"). */
    public static function digitos(?string $texto): string
    {
        return (string) preg_replace('/\D/', '', (string) $texto);
    }
}
