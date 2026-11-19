<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Intervalo de datas (inclusive) dos relatórios e do dashboard. Vem de um atalho (`semana_atual`, `mes_atual`…)
 * ou de datas livres (`personalizado`); intervalos maiores que um ano são limitados.
 */
final readonly class PeriodoRelatorio
{
    public const ATALHOS = [
        'semana_atual' => 'Semana atual',
        'semana_anterior' => 'Semana anterior',
        'mes_atual' => 'Mês atual',
        'mes_anterior' => 'Mês anterior',
        'ultimos_30' => 'Últimos 30 dias',
        'ano_atual' => 'Ano atual',
        'personalizado' => 'Período personalizado',
    ];

    private const MAXIMO_DIAS = 366;

    public function __construct(public CarbonImmutable $inicio, public CarbonImmutable $fim) {}

    public static function deAtalho(string $atalho, ?string $de = null, ?string $ate = null): self
    {
        $hoje = CarbonImmutable::today();

        // Semana ISO (segunda a domingo), igual à `SemanaIso`: o locale pt_BR do Carbon começaria no domingo.
        $semana = SemanaIso::deData($hoje);
        $anterior = $semana->anterior();

        return match ($atalho) {
            'semana_atual' => new self($semana->inicio, $semana->fim),
            'semana_anterior' => new self($anterior->inicio, $anterior->fim),
            'mes_anterior' => new self($hoje->subMonthNoOverflow()->startOfMonth(), $hoje->subMonthNoOverflow()->endOfMonth()->startOfDay()),
            'ultimos_30' => new self($hoje->subDays(29), $hoje),
            'ano_atual' => new self($hoje->startOfYear(), $hoje->endOfYear()->startOfDay()),
            'personalizado' => self::personalizado($de, $ate) ?? new self($hoje->startOfMonth(), $hoje->endOfMonth()->startOfDay()),
            default => new self($hoje->startOfMonth(), $hoje->endOfMonth()->startOfDay()),
        };
    }

    private static function personalizado(?string $de, ?string $ate): ?self
    {
        try {
            $inicio = $de ? CarbonImmutable::parse($de)->startOfDay() : null;
            $fim = $ate ? CarbonImmutable::parse($ate)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }

        if ($inicio === null && $fim === null) {
            return null;
        }

        $inicio ??= $fim->startOfMonth();
        $fim ??= $inicio->endOfMonth()->startOfDay();

        if ($inicio->greaterThan($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        if ($inicio->diffInDays($fim) > self::MAXIMO_DIAS) {
            $fim = $inicio->addDays(self::MAXIMO_DIAS);
        }

        return new self($inicio, $fim);
    }

    public function de(): string
    {
        return $this->inicio->toDateString();
    }

    public function ate(): string
    {
        return $this->fim->toDateString();
    }

    /** "01/11/2026 a 30/11/2026". */
    public function rotulo(): string
    {
        return $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y');
    }
}
