<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissões `recurso.acao` e papéis padrão (ver CLAUDE.md §7). Idempotente.
 */
class PapeisEPermissoesSeeder extends Seeder
{
    /** @return array<int, string> */
    public static function permissoes(): array
    {
        $crud = fn (string $recurso, array $acoes = ['ver', 'criar', 'editar', 'excluir']) => array_map(
            fn ($acao) => "{$recurso}.{$acao}",
            $acoes
        );

        return array_merge(
            $crud('filiais', ['ver', 'criar', 'editar', 'desativar']),
            ['filiais.ver_todas'],
            $crud('motoristas'),
            $crud('veiculos'),
            $crud('locais'),
            $crud('postos'),
            $crud('tarifas'),
            ['tarifas.importar'],
            $crud('viagens', ['ver', 'criar', 'editar', 'excluir', 'cancelar']),
            ['viagens.ver_valores'],
            $crud('abastecimentos'),
            $crud('manutencoes'),
            ['manutencoes.ver_valores'],
            ['comissoes.ver', 'comissoes.editar'],
            $crud('fechamentos', ['ver', 'fechar', 'reabrir', 'pagar']),
            ['relatorios.ver', 'relatorios.exportar', 'dashboard.ver'],
            ['portal.acessar'],
            $crud('usuarios', ['ver', 'criar', 'editar', 'desativar']),
            ['papeis.gerenciar', 'auditoria.ver', 'importacao.executar', 'configuracoes.editar'],
        );
    }

    /** @return array<string, array<int, string>> */
    public static function papeis(): array
    {
        $todas = self::permissoes();
        $somenteVer = array_values(array_filter($todas, fn ($p) => str_ends_with($p, '.ver') || str_ends_with($p, '.ver_valores')));
        $semTodas = fn (array $lista) => array_values(array_diff($lista, ['filiais.ver_todas']));

        $gestor = array_merge($somenteVer, [
            'filiais.ver_todas', 'comissoes.ver', 'fechamentos.fechar',
            'relatorios.ver', 'relatorios.exportar', 'dashboard.ver',
        ]);

        $gerenteFilial = array_merge($semTodas($somenteVer), [
            'motoristas.criar', 'motoristas.editar', 'veiculos.criar', 'veiculos.editar',
            'viagens.criar', 'viagens.editar', 'viagens.cancelar',
            'abastecimentos.criar', 'abastecimentos.editar',
            'manutencoes.criar', 'manutencoes.editar',
            'comissoes.ver', 'fechamentos.fechar',
            'relatorios.ver', 'relatorios.exportar', 'dashboard.ver',
        ]);

        return [
            'admin' => $todas,
            'gestor' => array_values(array_unique($gestor)),
            'gerente_filial' => array_values(array_unique($gerenteFilial)),
            'financeiro' => [
                'filiais.ver', 'viagens.ver', 'viagens.ver_valores', 'abastecimentos.ver', 'manutencoes.ver', 'manutencoes.ver_valores',
                'motoristas.ver', 'veiculos.ver',
                'tarifas.ver', 'tarifas.criar', 'tarifas.editar', 'tarifas.excluir', 'tarifas.importar',
                'locais.ver', 'locais.criar',
                'comissoes.ver', 'comissoes.editar',
                'fechamentos.ver', 'fechamentos.fechar', 'fechamentos.pagar',
                'relatorios.ver', 'relatorios.exportar', 'dashboard.ver',
            ],
            'operacional' => [
                'filiais.ver', 'motoristas.ver', 'veiculos.ver',
                'locais.ver', 'locais.criar', 'locais.editar', 'postos.ver', 'postos.criar', 'postos.editar',
                'viagens.ver', 'viagens.ver_valores', 'viagens.criar', 'viagens.editar', 'viagens.cancelar',
                'abastecimentos.ver', 'abastecimentos.criar', 'abastecimentos.editar',
                'manutencoes.ver', 'manutencoes.ver_valores', 'manutencoes.criar', 'manutencoes.editar',
                'dashboard.ver',
            ],
            // Somente leitura, sem valores financeiros (frete, comissão, líquido) e sem usuários/auditoria.
            'consulta' => array_values(array_filter(
                $somenteVer,
                fn ($p) => ! str_ends_with($p, '.ver_valores') && ! in_array($p, ['usuarios.ver', 'auditoria.ver', 'comissoes.ver'], true)
            )),
            'motorista' => ['portal.acessar'],
        ];
    }

    /**
     * Permissões que deixaram de existir (removidas do produto). Apagadas ao rodar o seeder.
     *
     * @return array<int, string>
     */
    public static function obsoletas(): array
    {
        return ['clientes.ver', 'clientes.criar', 'clientes.editar', 'clientes.excluir'];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->whereIn('name', self::obsoletas())->delete();

        foreach (self::permissoes() as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        foreach (self::papeis() as $papel => $permissoes) {
            $role = Role::findOrCreate($papel, 'web');

            $role->syncPermissions(array_values(array_unique($permissoes)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
