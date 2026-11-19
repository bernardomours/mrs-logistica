<?php

namespace Tests\Unit;

use App\Support\Dinheiro;
use App\Support\SemanaIso;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DinheiroSemanaTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function comissoes(): array
    {
        // Frete e % reais da planilha do motorista STENYO (6%): comissão arredondada por viagem (RN-02).
        return [
            'argo fruit' => ['11170.27', '6', '670.22'],
            'são sabino → natal' => ['4491.69', '6', '269.50'],
            'são sabino → crossdocking' => ['4276.09', '6', '256.57'],
            'macacos → crossdocking' => ['2575.23', '6', '154.51'],
            'macacos → natal' => ['4541.01', '6', '272.46'],
            'frete variado' => ['4481.97', '6', '268.92'],
            'percentual fracionado' => ['1000.00', '6.5', '65.00'],
            'meio centavo sobe' => ['0.25', '2', '0.01'],
            'zero' => ['0.00', '6', '0.00'],
        ];
    }

    #[DataProvider('comissoes')]
    public function test_comissao_por_viagem_arredondada_half_up(string $frete, string $percentual, string $esperado): void
    {
        $this->assertSame($esperado, Dinheiro::percentual($frete, $percentual));
    }

    public function test_arredondamento_half_up_para_positivos_e_negativos(): void
    {
        $this->assertSame('0.01', Dinheiro::arredondar('0.005'));
        $this->assertSame('0.00', Dinheiro::arredondar('0.004999'));
        $this->assertSame('2.50', Dinheiro::arredondar('2.495'));
        $this->assertSame('-2.50', Dinheiro::arredondar('-2.495'));
        $this->assertSame('0.00', Dinheiro::arredondar('-0.001'));
        $this->assertSame('367.647', Dinheiro::arredondar('367.6470588', 3));
    }

    public function test_multiplicar_dividir_somar_e_total(): void
    {
        $this->assertSame('2756.00', Dinheiro::multiplicar('400', '6.89'));
        // 367,647 L × 6,80 = 2.499,9996 → 2.500,00; a planilha digitou 2.499,99 (diferença de 1 centavo = tolerância RN-06).
        $this->assertSame('2500.00', Dinheiro::multiplicar('367.647', '6.8', 2));
        $this->assertSame('0.01', Dinheiro::subtrair('2500.00', '2499.99'));
        $this->assertSame('367.646', Dinheiro::dividir('2499.99', '6.8', 3));
        $this->assertSame('367.647', Dinheiro::dividir('2500.00', '6.8', 3));
        $this->assertNull(Dinheiro::dividir('10', '0'));
        $this->assertSame('7516.00', Dinheiro::total(['2756.00', '2040.00', '2720.00']));
        $this->assertSame('11428.43', Dinheiro::subtrair(Dinheiro::subtrair('20153.65', '1209.22'), '7516.00'));
        $this->assertSame(0, Dinheiro::comparar('1.001', '1.004'));
        $this->assertSame('1.5', Dinheiro::abs('-1.5'));
    }

    public function test_nao_perde_precisao_com_valores_que_o_float_erra(): void
    {
        $this->assertSame('0.30', Dinheiro::somar('0.10', '0.20'));
        $this->assertSame('1.15', Dinheiro::multiplicar('1.15', '1'));
        $this->assertSame('1.01', Dinheiro::multiplicar('1.005', '1'));
    }

    public function test_semana_iso_das_datas_da_planilha(): void
    {
        $s40 = SemanaIso::deData('2026-09-29'); // terça
        $this->assertSame([2026, 40], [$s40->ano, $s40->semana]);
        $this->assertSame('2026-09-28', $s40->inicio->toDateString());
        $this->assertSame('2026-10-04', $s40->fim->toDateString());

        $this->assertSame(41, SemanaIso::deData('2026-10-07')->semana);
        $this->assertSame(42, SemanaIso::deData('2026-10-12')->semana);
        $this->assertSame(42, SemanaIso::deData('2026-10-18')->semana, 'domingo ainda é da semana 42');
        $this->assertSame(43, SemanaIso::deData('2026-10-19')->semana);
    }

    public function test_semana_iso_na_virada_de_ano(): void
    {
        $this->assertSame([2025, 1], [SemanaIso::deData('2024-12-30')->ano, SemanaIso::deData('2024-12-30')->semana]);
        $this->assertSame([2026, 53], [SemanaIso::deData('2027-01-01')->ano, SemanaIso::deData('2027-01-01')->semana]);
        $this->assertSame([2026, 1], [SemanaIso::deData('2025-12-29')->ano, SemanaIso::deData('2025-12-29')->semana]);
    }

    public function test_navegacao_entre_semanas_chave_e_rotulo(): void
    {
        $s42 = SemanaIso::de(2026, 42);

        $this->assertSame('2026-W42', $s42->chave());
        $this->assertSame('2026-W41', $s42->anterior()->chave());
        $this->assertSame('2026-W43', $s42->proxima()->chave());
        $this->assertSame('Semana 42/2026 (12/10 a 18/10)', $s42->rotulo());
        $this->assertTrue($s42->contem('2026-10-18'));
        $this->assertFalse($s42->contem('2026-10-19'));
        $this->assertEquals($s42->inicio, SemanaIso::daChave('2026-W42')->inicio);
        $this->assertNull(SemanaIso::daChave('lixo'));
        $this->assertNull(SemanaIso::daChave('2026-W99'));
    }
}
