<?php

namespace Tests\Unit;

use App\Rules\Cpf;
use App\Rules\Placa;
use App\Support\Numero;
use App\Support\Texto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SuporteTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function numeros(): array
    {
        return [
            'milhar e vírgula' => ['4.491,69', '4491.69'],
            'com R$' => ['R$ 4.491,69', '4491.69'],
            'só vírgula' => ['6,5', '6.50'],
            'ponto decimal' => ['6.5', '6.50'],
            'vários pontos (milhar)' => ['1.234.567', '1234567.00'],
            'inteiro' => ['4491', '4491.00'],
            'percentual' => ['6%', '6.00'],
            'arredonda para cima' => ['0,005', '0.01'],
            'vazio' => ['', null],
            'nulo' => [null, null],
            'texto' => ['abc', null],
            'só sinal' => ['-', null],
        ];
    }

    #[DataProvider('numeros')]
    public function test_converte_numero_digitado_em_decimal(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Numero::paraDecimal($entrada));
    }

    public function test_casas_decimais_configuraveis(): void
    {
        $this->assertSame('667944.9', Numero::paraDecimal('667.944,9', 1));
    }

    public function test_formata_para_exibicao(): void
    {
        $this->assertSame('4.491,69', Numero::formatar('4491.69'));
        $this->assertSame('', Numero::formatar(null));
        $this->assertSame('4491,69', Numero::paraCampo('4491.69'));
        $this->assertSame('667944,9', Numero::paraCampo('667944.9', 1));
    }

    public function test_chave_ignora_acento_caixa_e_espacos(): void
    {
        $this->assertSame('SAO SABINO/APODI', Texto::chave('  São   Sabino/Apodi '));
        $this->assertSame(Texto::chave('Macacos/Quixeré'), Texto::chave('MACACOS/QUIXERE'));
    }

    public function test_formata_documentos(): void
    {
        $this->assertSame('111.444.777-35', Texto::cpf('11144477735'));
        $this->assertSame('11.222.333/0001-81', Texto::cnpj('11222333000181'));
        $this->assertSame('123', Texto::cpf('123'));
        $this->assertSame('12345678909', Texto::digitos('123.456.789-09'));
    }

    public function test_valida_cpf(): void
    {
        $this->assertTrue(Cpf::valido('111.444.777-35'));
        $this->assertTrue(Cpf::valido('11144477735'));
        $this->assertFalse(Cpf::valido('11144477736'));
        $this->assertFalse(Cpf::valido('11111111111'));
        $this->assertFalse(Cpf::valido('123'));
    }

    public function test_valida_e_normaliza_placa(): void
    {
        $this->assertTrue(Placa::valida('ABC-1234'));
        $this->assertTrue(Placa::valida('abc1d23'));
        $this->assertFalse(Placa::valida('AB-12345'));
        $this->assertFalse(Placa::valida('1234ABC'));
        $this->assertSame('ABC1D23', Placa::normalizar('abc-1d23'));
    }
}
