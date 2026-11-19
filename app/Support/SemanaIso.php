<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Semana ISO-8601 (segunda a domingo) — CLAUDE.md RN-01. A semana de um lançamento vem da própria data.
 * Cuidado com a virada de ano: 29/12 pode ser semana 1 do ano seguinte (usa o ano ISO).
 */
final readonly class SemanaIso
{
    public function __construct(
        public int $ano,
        public int $semana,
        public CarbonImmutable $inicio,
        public CarbonImmutable $fim,
    ) {}

    public static function deData(DateTimeInterface|string $data): self
    {
        $dia = CarbonImmutable::parse($data)->startOfDay();

        return new self(
            $dia->isoWeekYear,
            $dia->isoWeek,
            $dia->startOfWeek(Carbon::MONDAY),
            $dia->startOfWeek(Carbon::MONDAY)->addDays(6),
        );
    }

    public static function de(int $ano, int $semana): self
    {
        return self::deData(CarbonImmutable::now()->setISODate($ano, $semana, 1));
    }

    public static function atual(): self
    {
        return self::deData(CarbonImmutable::now());
    }

    public function anterior(): self
    {
        return self::deData($this->inicio->subDay());
    }

    public function proxima(): self
    {
        return self::deData($this->fim->addDay());
    }

    public function contem(DateTimeInterface|string $data): bool
    {
        $dia = CarbonImmutable::parse($data)->startOfDay();

        return $dia->betweenIncluded($this->inicio, $this->fim);
    }

    /** "2026-W42" — usado em filtros e query string. */
    public function chave(): string
    {
        return sprintf('%d-W%02d', $this->ano, $this->semana);
    }

    public static function daChave(string $chave): ?self
    {
        return preg_match('/^(\d{4})-W(\d{2})$/', $chave, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 53
            ? self::de((int) $m[1], (int) $m[2])
            : null;
    }

    /** "Semana 42/2026 (12/10 a 18/10)". */
    public function rotulo(): string
    {
        return sprintf('Semana %d/%d (%s a %s)', $this->semana, $this->ano, $this->inicio->format('d/m'), $this->fim->format('d/m'));
    }
}
