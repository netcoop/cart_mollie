<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cart - Mollie',
    'description' => 'Shopping Cart(s) for TYPO3 - Mollie Payment Provider',
    'category' => 'services',
    'author' => 'Loek Hilgersom',
    'author_email' => 'typo3extensions@netcoop.nl',
    'author_company' => 'NetCoop',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'alpha',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '6.2.0-8.7.99',
            'php' => '5.6.0',
            'cart' => '4.5.0-',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
