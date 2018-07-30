<?php

namespace Netcoop\CartMollie\Utility\Dispatcher;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Ajax Dispatcher
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class Cart
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Configuration Manager
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Request
     *
     * @var \TYPO3\CMS\Extbase\Mvc\Request
     */
    protected $request;

    /**
     * logManager
     *
     * @var \TYPO3\CMS\Core\Log\LogManager
     */
    protected $logManager;

    /**
     * Cart Repository
     *
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

    /**
     * Order Item Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\ItemRepository
     * @inject
     */
    protected $orderItemRepository;

    /**
     * Order Payment Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\PaymentRepository
     */
    protected $orderPaymentRepository;

    /**
     * Order Payment Repository
     *
     * @var \Extcode\Cart\Domain\Repository\Order\TransactionRepository
     */
    protected $orderTransactionRepository;

    /**
     * Cart
     *
     * @var \Extcode\Cart\Domain\Model\Cart
     */
    protected $cart;

    /**
     * OrderItem
     *
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem;

    /**
     * Order Payment
     *
     * @var \Extcode\Cart\Domain\Model\Order\Payment
     */
    protected $orderPayment;

    /**
     * Order Transaction
     *
     * @var \Extcode\Cart\Domain\Model\Order\Transaction
     */
    protected $transaction;

    /**
     * @var \TYPO3\CMS\Extbase\Service\TypoScriptService
     */
    protected $typoScriptService;

    /**
     * Order Number
     *
     * @var string
     */
    protected $orderNumber;

    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * @var int
     */
    protected $pageUid;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * Cart Configuration
     *
     * @var array
     */
    protected $cartConf = [];

    /**
     * Cart Mollie Configuration
     *
     * @var array
     */
    protected $cartMollieConf = [];

    /**
     * Curl Result
     *
     * @var string
     */
    protected $curlResult = '';

    /**
     * Curl Results
     *
     * @var array
     */
    protected $curlResults = [];

    /**
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(
        \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     */
    public function injectConfigurationManager(
        \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
    ) {
        $this->cartMollieConfigurationManager = $configurationManager;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(
        \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager
    ) {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @param \TYPO3\CMS\Core\Log\LogManager $logManager
     */
    public function injectLogManager(
        \TYPO3\CMS\Core\Log\LogManager $logManager
    ) {
        $this->logManager = $logManager;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
     */
    public function injectCartRepository(
        \Extcode\Cart\Domain\Repository\CartRepository $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\ItemRepository $orderItemRepository
     */
    public function injectOrderItemRepository(
        \Extcode\Cart\Domain\Repository\Order\ItemRepository $orderItemRepository
    ) {
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\PaymentRepository $orderPaymentRepository
     */
    public function injectOrderPaymentRepository(
        \Extcode\Cart\Domain\Repository\Order\PaymentRepository $orderPaymentRepository
    ) {
        $this->orderPaymentRepository = $orderPaymentRepository;
    }

    /**
     * @param \Extcode\Cart\Domain\Repository\Order\TransactionRepository $orderTransactionRepository
     */
    public function injectOrderTransactionRepository(
        \Extcode\Cart\Domain\Repository\Order\TransactionRepository $orderTransactionRepository
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
     */
    public function injectTypoScriptService(
        \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
    ) {
        $this->typoScriptService = $typoScriptService;
    }

    /**
     * Initialize Settings
     */
    protected function initSettings()
    {
        $this->cartConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cart.']
        );
        $this->cartMollieConf = $this->typoScriptService->convertTypoScriptArrayToPlainArray(
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_cartmollie.']
        );
    }

    /**
     * Get Request
     */
    protected function getRequest()
    {
        $request = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('request');
        $action = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('eID');

        $this->request = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Mvc\Request::class
        );
        $this->request->setControllerVendorName('Netcoop');
        $this->request->setControllerExtensionName('CartMollie');
        $this->request->setControllerActionName($action);
        if (is_array($request['arguments'])) {
            $this->request->setArguments($request['arguments']);
        }
    }

    /**
     * Dispatch
     */
    public function dispatch()
    {
        $response = [];

        $this->initSettings();

        $this->getRequest();

        switch ($this->request->getControllerActionName()) {
            case 'mollieWebhook':
                $response = $this->mollieWebhookAction();
                break;
            case 'mollieWebhookTest':
                $response = $this->mollieWebhookTestAction();
                break;
        }

        return json_encode($response);
    }

    protected function mollieWebhookTestAction()
    {
	    $response = [
		    'testmode' => $this->cartMollieConf['settings']['testmode'] ? true : false,
		    'webhookPath' => $this->cartMollieConf['settings']['webhookPath'] ? true : false,
		    'return_url' => $this->cartMollieConf['settings']['return_url'] ? true : false,
	    ];

	    return $response;
    }

    /**
     * mollieWebhookAction
     * Called by Mollie as soon as status of a payment has changed
     *
     * @return array
     */
    protected function mollieWebhookAction()
    {
        // Allow GET vars in testmode
	    if ($this->cartMollieConf['settings']['testmode']) {
		    $molliePaymentId = GeneralUtility::_GP('id');
	    } else {
		    $molliePaymentId = GeneralUtility::_POST('id');
	    }

	    $mollie = \Netcoop\CartMollie\Utility\Mollie::instantiateMollie($this->cartMollieConf['settings']);
	    $molliePayment = $mollie->payments->get($molliePaymentId);

	    if ($this->cartMollieConf['settings']['logPayments']) {
		    \Netcoop\CartMollie\Utility\Mollie::logWrite( $molliePaymentId, 'webhook', print_r( $molliePayment, true ) );
	    }

	    // Payment states: open, pending, paid, cancelled

	    $molliePaymentStatus = $molliePayment->status;
		$metadata = $molliePayment->metadata;
		$orderNumber = $metadata->orderNumber;

	    $this->getOrderItem($orderNumber);
        $this->getOrderPayment();
        $this->getCart();

        if (NULL === $this->transaction = $this->getTransactionForPaymentId($molliePaymentId))
        {
	        \TYPO3\CMS\Core\Utility\GeneralUtility::sysLog('Invalid webhook request: Transaction with molliePaymentId (' . $molliePaymentId . ') not found.', 'cart_mollie', 3);
        }
        elseif (!$this->orderPayment->getTransactions()->contains($this->transaction))
        {
	        \TYPO3\CMS\Core\Utility\GeneralUtility::sysLog( 'Invalid webhook request: No matching transaction found for this combination of orderNumber (' . $orderNumber . ') and molliePaymentId (' . $molliePaymentId . ')', 'cart_mollie', 3 );
        }
        else
        {

	        $this->transaction->setExternalStatusCode($molliePaymentStatus);
	        $this->transaction->setStatus($molliePaymentStatus);
	        $this->orderPayment->setStatus($molliePaymentStatus);

	        // Possible statusses for which Mollie calls the webhook: canceled, expired, failed, paid
	        // Only for status paid additional action is required at this point, can be checked with $molliePayment->isPaid()
	        if ($molliePayment->isPaid())
	        {
	        	// do something
		        // Mails are configured for each status separately. For emails to buyer there is only a template for status 'paid'
		        // so we don't need to do more here. Emails to seller can be set for other statusses, just create the templates
	        }

	        $this->orderTransactionRepository->update($this->transaction);
	        $this->orderPaymentRepository->update($this->orderPayment);

	        $this->persistenceManager->persistAll();

	        $this->sendMails();

	        $this->updateCart();

        }

        return [];
    }

    /**
     * Send Mails
     */
    protected function sendMails()
    {
        $billingAddress = $this->orderItem->getBillingAddress()->_loadRealInstance();
        if ($this->orderItem->getShippingAddress()) {
            $shippingAddress = $this->orderItem->getShippingAddress()->_loadRealInstance();
        }

        $this->sendBuyerMail($this->orderItem, $billingAddress, $shippingAddress);
        $this->sendSellerMail($this->orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Send a Mail to Buyer
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem Order Item
     * @param \Extcode\Cart\Domain\Model\Order\Address $billingAddress Billing Address
     * @param \Extcode\Cart\Domain\Model\Order\Address $shippingAddress Shipping Address
     */
    protected function sendBuyerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem,
        \Extcode\Cart\Domain\Model\Order\Address $billingAddress,
        \Extcode\Cart\Domain\Model\Order\Address $shippingAddress = null
    ) {
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );

        $mailHandler->setCart($this->cart->getCart());

        $mailHandler->sendBuyerMail($orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Send a Mail to Seller
     *
     * @param \Extcode\Cart\Domain\Model\Order\Item $orderItem Order Item
     * @param \Extcode\Cart\Domain\Model\Order\Address $billingAddress Billing Address
     * @param \Extcode\Cart\Domain\Model\Order\Address $shippingAddress Shipping Address
     */
    protected function sendSellerMail(
        \Extcode\Cart\Domain\Model\Order\Item $orderItem,
        \Extcode\Cart\Domain\Model\Order\Address $billingAddress,
        \Extcode\Cart\Domain\Model\Order\Address $shippingAddress = null
    ) {
        $mailHandler = $this->objectManager->get(
            \Extcode\Cart\Service\MailHandler::class
        );

        $mailHandler->setCart($this->cart->getCart());

        $mailHandler->sendSellerMail($orderItem, $billingAddress, $shippingAddress);
    }

    /**
     * Get Order Item
     */
    protected function getOrderItem($orderNumber)
    {
        if ($orderNumber) {
            $this->orderItem = $this->orderItemRepository->findOneByOrderNumber($orderNumber);
        }
    }

    /**
     * Get Payment
     */
    protected function getOrderPayment()
    {
        if ($this->orderItem) {
            $this->orderPayment = $this->orderItem->getPayment();
        }
    }

    /**
     * Get Cart
     */
    protected function getCart()
    {
        if ($this->orderItem) {
            /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
            $querySettings = $this->objectManager->get(
                \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class
            );
            $querySettings->setStoragePageIds([$this->cartConf['settings']['order']['pid']]);
            $this->cartRepository->setDefaultQuerySettings($querySettings);

            $this->cart = $this->cartRepository->findOneByOrderItem($this->orderItem);
        }
    }

    /**
     * Update Cart
     */
    protected function updateCart()
    {
        $this->cart->setWasOrdered(true);

        $this->cartRepository->update($this->cart);

        $this->persistenceManager->persistAll();
    }

    /**
     * @param string $txn_id
     * @param string $txn_txt
     *
     * @return \Extcode\Cart\Domain\Model\Order\Transaction
     */
    protected function getTransactionForPaymentId($molliePaymentId)
    {
    	$transactions = $this->orderPayment->getTransactions();
    	foreach ($transactions as $transaction)
	    {
	    	$txnId = $transaction->getTxnId();
	    	if ($txnId == $molliePaymentId)
		    {
		    	return $transaction;
		    }
	    }
    }

    /**
     * @param int $status
     */
    protected function setOrderPaymentStatus($status)
    {
        if ($this->orderPayment) {
            $this->orderPayment->setStatus($status);
        }
    }
}
