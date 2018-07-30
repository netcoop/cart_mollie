<?php

namespace Netcoop\CartMollie\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;


class Mollie {

	/**
	 * Returns mollie object
	 */
	public static function instantiateMollie($settings)
	{
		if ($settings['testmode']) {
			$apiKey = $settings['apiKeyTest'];
		} else {
			$apiKey = $settings['apiKey'];
		}
		$mollie = new \Mollie\Api\MollieApiClient();
		$mollie->setApiKey($apiKey);
		return $mollie;
	}

	public static function logWrite($paymentId, $filename, $status='')
	{
		$logPath = $_ENV['TYPO3_PATH_APP'] . '/log/';
		if (!is_dir($logPath))
		{
			GeneralUtility::mkdir($_ENV['TYPO3_PATH_APP']);
		}
		$logFile = strftime('%Y%m%d-%H%M') . '-' . $filename . '-' . $paymentId . '.txt';
		file_put_contents($logPath . $logFile, $status);
	}


}