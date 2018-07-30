<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\SignalSlot\\Dispatcher');
$dispatcher->connect(
    'Extcode\Cart\Utility\OrderUtility',
    'handlePayment',
    'Netcoop\CartMollie\Service\Payment',
    'handlePayment'
);

if (TYPO3_MODE == 'FE') {
    $TYPO3_CONF_VARS['FE']['eID_include']['mollieWebhook'] = 'EXT:cart_mollie/Classes/Utility/eIDDispatcher.php';
}
