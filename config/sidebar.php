<?php
return [
    [
        'title' => 'Dashboard',
        'route' => 'profile.dashboard',
        'icon' => 'fas fa-tachometer-alt',
    ],
    [
        'title' => 'Master',
        'icon' => 'far fa-newspaper',
        'submenu' => [
            [
                'title' => 'Product Category',
                'icon' => 'fas fa-shopping-basket',
                'submenu' => [
                    ['title' => 'Manage Category', 'route' => 'category.index', 'icon' => 'fas fa-tasks'],
                    ['title' => 'Manage Sub Category', 'route' => 'subcategory.index', 'icon' => 'fas fa-tags'],
                ]
            ],
            ['title' => 'Brand', 'route' => 'brands.index', 'icon' => 'fas fa-rupee-sign'],
            ['title' => 'Manage Groups', 'route' => 'groups.index', 'icon' => 'far fa-object-group'],
            ['title' => 'POS Counters', 'route' => 'counters.index', 'icon' => 'fas fa-cash-register'],
        ]
    ],
    [
        'title' => 'Settings',
        'icon' => 'fas fa-cogs',
        'submenu' => [
            ['title' => 'Users', 'route' => 'users.index', 'icon' => 'fas fa-user-cog'],
            ['title' => 'Company', 'route' => 'company.index', 'icon' => 'far fa-building'],
        ]
    ],
    [
        'title' => 'Utility',
        'icon' => 'fas fa-spinner',
        'submenu' => [
            ['title' => 'Back Up', 'route' => 'profile.dbbackup', 'icon' => 'fas fa-database'],
            ['title' => 'Restore', 'route' => 'profile.restore', 'icon' => 'far fa-window-restore'],
        ]
    ],
];
