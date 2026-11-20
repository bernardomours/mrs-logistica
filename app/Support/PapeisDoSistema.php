<?php

namespace App\Support;

/**
 * Papéis que já vêm com o sistema (CLAUDE.md §7): rótulos em português e quais são protegidos.
 * Papéis criados pelo usuário usam o próprio nome como rótulo.
 */
final class PapeisDoSistema
{
    public const ADMIN = 'admin';

    public const MOTORISTA = 'motorista';

    /** @var array<string, array{rotulo: string, descricao: string}> */
    private const PADRAO = [
        'admin' => ['rotulo' => 'Administrador', 'descricao' => 'Acesso total, em todas as filiais. Não pode ser alterado.'],
        'gestor' => ['rotulo' => 'Gestor', 'descricao' => 'Vê tudo, emite relatórios e fecha semanas em todas as filiais.'],
        'gerente_filial' => ['rotulo' => 'Gerente de filial', 'descricao' => 'Gestor restrito às filiais vinculadas.'],
        'financeiro' => ['rotulo' => 'Financeiro', 'descricao' => 'Tarifas, comissões, fechamentos e relatórios.'],
        'operacional' => ['rotulo' => 'Operacional', 'descricao' => 'Lança viagens, abastecimentos e manutenções das suas filiais.'],
        'consulta' => ['rotulo' => 'Consulta', 'descricao' => 'Somente leitura, sem valores financeiros.'],
        'motorista' => ['rotulo' => 'Motorista', 'descricao' => 'Portal do motorista. Criado pelo cadastro de motoristas; não pode ser alterado.'],
    ];

    /** @return array<int, string> */
    public static function padrao(): array
    {
        return array_keys(self::PADRAO);
    }

    public static function ehDoSistema(string $nome): bool
    {
        return isset(self::PADRAO[$nome]);
    }

    /** Papéis cujas permissões e nome não podem ser mexidos pela tela. */
    public static function travado(string $nome): bool
    {
        return in_array($nome, [self::ADMIN, self::MOTORISTA], true);
    }

    public static function rotulo(string $nome): string
    {
        return self::PADRAO[$nome]['rotulo'] ?? $nome;
    }

    public static function descricao(string $nome): ?string
    {
        return self::PADRAO[$nome]['descricao'] ?? null;
    }
}
