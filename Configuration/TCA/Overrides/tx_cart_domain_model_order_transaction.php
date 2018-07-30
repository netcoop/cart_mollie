<?php

defined('TYPO3_MODE') or die();

$_LLL = 'LLL:EXT:cart_mollie/Resources/Private/Language/locallang_db.xlf';

$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.completed', 'open'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.created', 'canceled'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.denied', 'pending'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.expired', 'expired'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.failed', 'failed'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.refunded', 'paid'];
$GLOBALS['TCA']['tx_cart_domain_model_order_transaction']['columns']['status']['config']['items'][] =
    [$_LLL . ':tx_cart_domain_model_order_transaction.status.reversed', 'refunded'];
