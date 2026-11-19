<?php

/*
 * Menu lateral e rótulos de breadcrumb (ver CLAUDE.md §9).
 * Itens cuja rota ainda não existe são ocultados automaticamente, então o menu
 * cresce conforme os módulos são criados.
 */
return [
    'grupos' => [
        [
            'rotulo' => null,
            'itens' => [
                ['rotulo' => 'Dashboard', 'icone' => 'grid', 'rota' => 'dashboard', 'permissao' => null],
            ],
        ],
        [
            'rotulo' => 'Operação',
            'itens' => [
                ['rotulo' => 'Viagens', 'icone' => 'route', 'rota' => 'viagens.index', 'permissao' => 'viagens.ver'],
                ['rotulo' => 'Abastecimentos', 'icone' => 'fuel', 'rota' => 'abastecimentos.index', 'permissao' => 'abastecimentos.ver'],
                ['rotulo' => 'Manutenções', 'icone' => 'wrench', 'rota' => 'manutencoes.index', 'permissao' => 'manutencoes.ver'],
                ['rotulo' => 'Fechamento semanal', 'icone' => 'clipboard', 'rota' => 'fechamentos.index', 'permissao' => 'fechamentos.ver'],
            ],
        ],
        [
            'rotulo' => 'Cadastros',
            'itens' => [
                ['rotulo' => 'Filiais', 'icone' => 'building', 'rota' => 'filiais.index', 'permissao' => 'filiais.ver'],
                ['rotulo' => 'Motoristas', 'icone' => 'users', 'rota' => 'motoristas.index', 'permissao' => 'motoristas.ver'],
                ['rotulo' => 'Veículos', 'icone' => 'truck', 'rota' => 'veiculos.index', 'permissao' => 'veiculos.ver'],
                ['rotulo' => 'Postos', 'icone' => 'droplet', 'rota' => 'postos.index', 'permissao' => 'postos.ver'],
                ['rotulo' => 'Tarifas de rota', 'icone' => 'tag', 'rota' => 'tarifas.index', 'permissao' => 'tarifas.ver'],
            ],
        ],
        [
            'rotulo' => 'Relatórios',
            'itens' => [
                ['rotulo' => 'Relatórios', 'icone' => 'chart', 'rota' => 'relatorios.index', 'permissao' => 'relatorios.ver'],
            ],
        ],
        [
            'rotulo' => 'Administração',
            'itens' => [
                ['rotulo' => 'Usuários', 'icone' => 'user-cog', 'rota' => 'usuarios.index', 'permissao' => 'usuarios.ver'],
                ['rotulo' => 'Papéis', 'icone' => 'shield', 'rota' => 'papeis.index', 'permissao' => 'papeis.gerenciar'],
                ['rotulo' => 'Importação', 'icone' => 'upload', 'rota' => 'importacao.index', 'permissao' => 'importacao.executar'],
                ['rotulo' => 'Auditoria', 'icone' => 'eye', 'rota' => 'auditoria.index', 'permissao' => 'auditoria.ver'],
                ['rotulo' => 'Configurações', 'icone' => 'sliders', 'rota' => 'configuracoes.index', 'permissao' => 'configuracoes.editar'],
            ],
        ],
    ],

    // Prefixo da rota => rótulo do módulo (breadcrumb).
    'modulos' => [
        'dashboard' => 'Dashboard',
        'filiais' => 'Filiais',
        'motoristas' => 'Motoristas',
        'veiculos' => 'Veículos',
        'postos' => 'Postos',
        'tarifas' => 'Tarifas de rota',
        'viagens' => 'Viagens',
        'abastecimentos' => 'Abastecimentos',
        'manutencoes' => 'Manutenções',
        'fechamentos' => 'Fechamento semanal',
        'relatorios' => 'Relatórios',
        'usuarios' => 'Usuários',
        'papeis' => 'Papéis',
        'importacao' => 'Importação',
        'auditoria' => 'Auditoria',
        'configuracoes' => 'Configurações',
        'perfil' => 'Perfil',
    ],

    // Sufixo da rota => rótulo da ação (breadcrumb).
    'acoes' => [
        'create' => 'Novo cadastro',
        'importar' => 'Importar planilha',
        'folha' => 'Folha da semana',
        'edit' => 'Editar',
        'show' => 'Detalhes',
    ],
];
