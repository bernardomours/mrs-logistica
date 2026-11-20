<?php

namespace App\Support;

/**
 * Permissões agrupadas e descritas em português para a tela de Papéis.
 * Toda permissão criada pelo seeder precisa aparecer aqui (há um teste que confere).
 */
final class CatalogoDePermissoes
{
    /**
     * @return array<string, array{titulo: string, permissoes: array<string, string>}>
     */
    public static function grupos(): array
    {
        return [
            'filiais' => ['titulo' => 'Filiais', 'permissoes' => [
                'filiais.ver' => 'Ver filiais',
                'filiais.criar' => 'Cadastrar filiais',
                'filiais.editar' => 'Editar filiais',
                'filiais.desativar' => 'Ativar e desativar filiais',
                'filiais.ver_todas' => 'Enxergar todas as filiais (visão consolidada)',
            ]],
            'motoristas' => ['titulo' => 'Motoristas', 'permissoes' => [
                'motoristas.ver' => 'Ver motoristas',
                'motoristas.criar' => 'Cadastrar motoristas',
                'motoristas.editar' => 'Editar motoristas',
                'motoristas.excluir' => 'Excluir motoristas',
            ]],
            'veiculos' => ['titulo' => 'Veículos', 'permissoes' => [
                'veiculos.ver' => 'Ver veículos',
                'veiculos.criar' => 'Cadastrar veículos',
                'veiculos.editar' => 'Editar veículos',
                'veiculos.excluir' => 'Excluir veículos',
            ]],
            'postos' => ['titulo' => 'Postos e locais', 'permissoes' => [
                'postos.ver' => 'Ver postos',
                'postos.criar' => 'Cadastrar postos',
                'postos.editar' => 'Editar postos',
                'postos.excluir' => 'Excluir postos',
                'locais.ver' => 'Ver locais',
                'locais.criar' => 'Cadastrar locais (botão "Novo local")',
                'locais.editar' => 'Editar locais',
                'locais.excluir' => 'Excluir locais',
            ]],
            'tarifas' => ['titulo' => 'Tarifas de rota', 'permissoes' => [
                'tarifas.ver' => 'Ver tarifas',
                'tarifas.criar' => 'Cadastrar tarifas',
                'tarifas.editar' => 'Editar tarifas',
                'tarifas.excluir' => 'Excluir tarifas',
                'tarifas.importar' => 'Importar tarifas por planilha',
            ]],
            'viagens' => ['titulo' => 'Viagens', 'permissoes' => [
                'viagens.ver' => 'Ver viagens',
                'viagens.criar' => 'Lançar viagens',
                'viagens.editar' => 'Editar viagens',
                'viagens.cancelar' => 'Cancelar viagens',
                'viagens.excluir' => 'Excluir viagens',
                'viagens.ver_valores' => 'Ver e digitar o valor do frete',
            ]],
            'abastecimentos' => ['titulo' => 'Abastecimentos', 'permissoes' => [
                'abastecimentos.ver' => 'Ver abastecimentos',
                'abastecimentos.criar' => 'Lançar abastecimentos',
                'abastecimentos.editar' => 'Editar abastecimentos',
                'abastecimentos.excluir' => 'Excluir abastecimentos',
            ]],
            'manutencoes' => ['titulo' => 'Manutenções', 'permissoes' => [
                'manutencoes.ver' => 'Ver manutenções',
                'manutencoes.criar' => 'Registrar manutenções',
                'manutencoes.editar' => 'Editar manutenções',
                'manutencoes.excluir' => 'Excluir manutenções',
                'manutencoes.ver_valores' => 'Ver e digitar valores de manutenção',
            ]],
            'comissoes' => ['titulo' => 'Comissões', 'permissoes' => [
                'comissoes.ver' => 'Ver comissões e o líquido da semana',
                'comissoes.editar' => 'Alterar a comissão de uma viagem',
            ]],
            'fechamentos' => ['titulo' => 'Fechamento semanal', 'permissoes' => [
                'fechamentos.ver' => 'Ver fechamentos',
                'fechamentos.fechar' => 'Fechar semanas',
                'fechamentos.pagar' => 'Marcar semanas como pagas e estornar pagamentos',
                'fechamentos.reabrir' => 'Reabrir semanas fechadas',
            ]],
            'relatorios' => ['titulo' => 'Relatórios e painel', 'permissoes' => [
                'dashboard.ver' => 'Ver o painel inicial (indicadores)',
                'relatorios.ver' => 'Ver relatórios',
                'relatorios.exportar' => 'Exportar relatórios, Excel e PDF',
            ]],
            'portal' => ['titulo' => 'Portal do motorista', 'permissoes' => [
                'portal.acessar' => 'Acessar o portal do motorista',
            ]],
            'usuarios' => ['titulo' => 'Usuários e papéis', 'permissoes' => [
                'usuarios.ver' => 'Ver usuários',
                'usuarios.criar' => 'Cadastrar usuários e criar acesso de motoristas',
                'usuarios.editar' => 'Editar usuários e redefinir senhas',
                'usuarios.desativar' => 'Ativar e desativar usuários',
                'usuarios.excluir' => 'Excluir usuários sem histórico',
                'papeis.gerenciar' => 'Gerenciar papéis e permissões',
            ]],
            'sistema' => ['titulo' => 'Sistema', 'permissoes' => [
                'auditoria.ver' => 'Ver a auditoria',
                'importacao.executar' => 'Importar planilhas',
                'configuracoes.editar' => 'Editar configurações',
            ]],
        ];
    }

    /** @return array<string, string> permissão => rótulo */
    public static function todas(): array
    {
        $todas = [];

        foreach (self::grupos() as $grupo) {
            $todas += $grupo['permissoes'];
        }

        return $todas;
    }

    public static function rotulo(string $permissao): string
    {
        return self::todas()[$permissao] ?? $permissao;
    }
}
