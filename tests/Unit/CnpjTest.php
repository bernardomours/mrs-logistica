<?php

namespace Tests\Unit;

use App\Rules\Cnpj;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    #[DataProvider('validos')]
    public function test_aceita_cnpj_valido(string $cnpj): void
    {
        $this->assertTrue(Cnpj::valido($cnpj));
    }

    #[DataProvider('invalidos')]
    public function test_rejeita_cnpj_invalido(string $cnpj): void
    {
        $this->assertFalse(Cnpj::valido($cnpj));
    }

    /** @return array<string, array{string}> */
    public static function validos(): array
    {
        return [
            'sem máscara' => ['11222333000181'],
            'com máscara' => ['11.222.333/0001-81'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidos(): array
    {
        return [
            'dígito errado' => ['11222333000182'],
            'todos iguais' => ['11111111111111'],
            'curto' => ['1122233300018'],
            'vazio' => [''],
            'letras' => ['abcdefghijklmn'],
        ];
    }
}
