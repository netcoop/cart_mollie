<?php

namespace Netcoop\CartMollie\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

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

/**
 * Payment Service
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class Payment
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     * @inject
     */
    protected $persistenceManager;

    /**
     * Configuration Manager
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * Cart Repository
     *
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

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
     * Cart Settings
     *
     * @var array
     */
    protected $cartConf = [];

    /**
     * Cart Mollie Settings
     *
     * @var array
     */
    protected $cartMollieConf = [];

    /**
     * Payment Query Url
     *
     * @var string
     */
    protected $paymentQueryUrl = '';

    /**
     * Payment Query
     *
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * Order Item
     *
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * Cart
     *
     * @var \Extcode\Cart\Domain\Model\Cart\Cart
     */
    protected $cart = null;

    /**
     * CartFHash
     *
     * @var string
     */
    protected $cartFHash = '';

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
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Extbase\Object\ObjectManager::class
        );

        $this->configurationManager = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class
        );

        $this->cartConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->cartMollieConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartMollie'
            );
    }

    /**
     * Handle Payment - Signal Slot Function
     *
     * @param array $params
     *
     * @return array
     */
    public function handlePayment($params)
    {
        $this->orderItem = $params['orderItem'];

        if ($this->orderItem->getPayment()->getProvider() == 'MOLLIE') {
            $params['providerUsed'] = true;

            $this->cart = $params['cart'];

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($this->cart);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                \Extcode\Cart\Domain\Repository\CartRepository::class
            );
            $cartRepository->add($cart);

            $this->persistenceManager->persistAll();

            $this->cartFHash = $cart->getFHash();

	        $mollie = \Netcoop\CartMollie\Utility\Mollie::instantiateMollie($this->cartMollieConf['settings']);
	        $totalAmount = $this->calculateOrderTotal();

	        $orderNumber = $this->orderItem->getOrderNumber();

	        $redirectUrl = $this->getRedirectUrl();
	        $webhookUrl = $this->getWebhookUrl();

	        $molliePayment = $mollie->payments->create([
		        'amount' => [
			        'currency' => $this->cartMollieConf['settings']['currency_code'],
			        'value' => $totalAmount
		        ],
		        'metadata' => ['orderNumber' => $orderNumber],
		        'description' => $this->getOrderDescription(),
		        'redirectUrl' => $redirectUrl,
		        'webhookUrl'  => $webhookUrl,
	        ]);

	        $molliePaymentId = $molliePayment->id;
	        $molliePaymentStatus = $molliePayment->status;
	        $molliePaymentDebugData = print_r($molliePayment, true);

	        $this->orderPayment = $this->orderItem->getPayment();
	        $this->orderPayment->setStatus($molliePaymentStatus);
	        $this->addPaymentTransaction($molliePaymentId, $molliePaymentDebugData, $molliePaymentStatus);

	        if ($this->cartMollieConf['settings']['logPayments']) {
		        \Netcoop\CartMollie\Utility\Mollie::logWrite( $molliePaymentId, 'payment', print_r( $params, true ) );
	        }

			$checkoutUrl = $molliePayment->getCheckoutUrl();
	        header("Location: " . $checkoutUrl, true, 303);
        }

        return [$params];
    }

	/**
	 * @param string $txn_id
	 * @param string $txn_txt
	 * @param string $externalStatus
	 */
	protected function addPaymentTransaction($txn_id, $txn_txt = '', $externalStatus = '')
	{
		$this->transaction = $this->objectManager->get('Extcode\Cart\Domain\Model\Order\Transaction');
		$this->transaction->setPid($this->orderPayment->getPid());

		$this->transaction->setTxnId($txn_id);
		$this->transaction->setTxnTxt($txn_txt);
		$this->transaction->setStatus($externalStatus);
		$this->transaction->setExternalStatusCode($externalStatus);

		$this->orderTransactionRepository->add($this->transaction);

		if ($this->orderPayment) {
			$this->orderPayment->addTransaction($this->transaction);
		}

		$this->orderPaymentRepository->update($this->orderPayment);

		$this->persistenceManager->persistAll();
	}

	/**
	 * @return string
	 */
	protected function getRedirectUrl()
	{
		if ($this->cartMollieConf['settings']['redirectUrl']) {
			$redirectUrl = $this->makeAbsoluteUrl($this->cartMollieConf['settings']['redirectUrl']);
		} else {
			$objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( \TYPO3\CMS\Extbase\Object\ObjectManager::class);
			$uriBuilder = $objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);

			$pageid = $GLOBALS['TSFE']->id;
			$redirectUrl = $uriBuilder
				->reset()
				->setTargetPageUid($pageid)
				->setArguments(['tx_cart_cart' => ['order' => $this->orderItem->getOrderNumber()]])
				->setCreateAbsoluteUri(true)
				->build();

//	        $uriBuilder = $objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
//	        $redirectUrl = $uriBuilder->uriFor('orderFinished');

		}
		return $redirectUrl;
	}

	/**
	 * @return string
	 */
	protected function getWebhookUrl()
	{
		return $this->makeAbsoluteUrl($this->cartMollieConf['settings']['webhookUrl']);
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	protected function makeAbsoluteUrl($url)
	{
		if (substr($url, 0, 4) !== 'http' && substr($url, 0, 2) !== '//') {
			$protocol   = isset( $_SERVER['HTTPS'] ) && strcasecmp( 'off', $_SERVER['HTTPS'] ) !== 0 ? "https" : "http";
			$hostname   = $_SERVER['HTTP_HOST'];
			$url = $protocol . '://' . $hostname . '/' . $url;
		}
		return $url;
	}

	/**
	 * @return string
	 */
    protected function calculateOrderTotal() {
	    $total = number_format(
		    $this->cart->getGross() + $this->cart->getServiceGross(),
		    2,
		    '.',
		    ''
	    );
		return $total;
    }

	/**
	 */
	protected function getOrderDescription()
	{
		$description = '';

		if ($this->orderItem->getProducts()) {
			$count = 0;
			$totalGross = 0;
			foreach ($this->orderItem->getProducts() as $productKey => $product) {
				$count += 1;
				$totalGross += $product->getGross();
				$description .= $product->getTitle() . " (" . $product->getCount() . ' x ' . $this->formatCurrency($product->getGross() / $product->getCount()) . ")" . chr(10);

			}
			$description .= 'Totaal: ' . $this->formatCurrency($totalGross);
		}

		return $description;
	}

	/**
	 * Format amount as specified in ext cart settings
	 *
	 * @param $amount
	 *
	 * @return string
	 */
	protected function formatCurrency($amount)
	{
		$currencyFormat = $this->cartConf['settings']['format']['currency'];

		$formattedAmount = number_format(
			$amount,
			$currencyFormat['decimals'],
			$currencyFormat['decimalSeparator'],
			$currencyFormat['thousandsSeparator']
		);

		$formattedAmount = $currencyFormat['prependCurrency']
			? $currencyFormat['currencySign'] . $currencyFormat['separateCurrency'] . $formattedAmount
			: $formattedAmount . $currencyFormat['separateCurrency'] . $currencyFormat['currencySign'];

		return $formattedAmount;
	}


    /**
     * Returns Query Url
     */
    protected function getQueryUrl()
    {
        if ($this->cartMollieConf['settings']['sandbox']) {
            $this->paymentQueryUrl = $this->cartMollieConf['websrcUrl']['sandbox'];
        } else {
            $this->paymentQueryUrl = $this->cartMollieConf['websrcUrl']['live'];
        }
    }

    /**
     * Get Query
     */
    protected function getQuery()
    {
        $this->getQueryFromSettings();
        $this->getQueryFromCart();
        $this->getQueryFromOrder();
    }

    /**
     * Get Query From Setting
     */
    protected function getQueryFromSettings()
    {
        $this->paymentQuery['business']      = $this->cartMollieConf['settings']['business'];
        $this->paymentQuery['test_ipn']      = intval($this->cartMollieConf['settings']['sandbox']);

        $this->paymentQuery['notify_url']    = $this->cartMollieConf['settings']['notify_url'];
        $this->paymentQuery['return']        = $this->cartMollieConf['settings']['return_url'];
        $cancelUrl = $this->cartMollieConf['settings']['cancel_url'];
        if ($cancelUrl) {
            $controllerParam = '&tx_cart_cart[controller]=Order';
            $orderParam = '&tx_cart_cart[order]=' . $this->orderItem->getUid();

            $actionFParam = '&tx_cart_cart[action]=paymentCancel';
            $hashFParam = '&tx_cart_cart[hash]=' . $this->cartFHash;
            $fParams = $controllerParam . $actionFParam . $orderParam . $hashFParam;

            $cancelUrl = $cancelUrl . $fParams;
        }
        $this->paymentQuery['cancel_return']        = $cancelUrl;

        $this->paymentQuery['cmd']           = '_cart';
        $this->paymentQuery['upload']        = '1';

        $this->paymentQuery['currency_code'] = $this->cartMollieConf['settings']['currency_code'];
    }

    /**
     * Get Query From Cart
     */
    protected function getQueryFromCart()
    {
        $this->paymentQuery['invoice'] = $this->cart->getOrderNumber();

        if ($this->cartMollieConf['settings']['sendEachItemToMollie']) {
            $this->addEachItemsFromCartToQuery();
        } else {
            $this->addEntireCartToQuery();
        }
    }

    /**
     * Get Query From Order
     */
    protected function getQueryFromOrder()
    {
        /** @var \Extcode\Cart\Domain\Model\Order\Address $billingAddress */
        $billingAddress = $this->orderItem->getBillingAddress();

        $this->paymentQuery['first_name'] = $billingAddress->getFirstName();
        $this->paymentQuery['last_name']  = $billingAddress->getLastName();
        $this->paymentQuery['email']      = $billingAddress->getEmail();
    }

    /**
     */
    protected function addEachItemsFromCartToQuery()
    {
        $shippingGross = $this->cart->getShipping()->getGross();
        $this->paymentQuery['handling_cart'] = number_format(
            $shippingGross,
            2,
            '.',
            ''
        );

        $this->addEachCouponFromCartToQuery();
        $this->addEachProductFromCartToQuery();

        $this->paymentQuery['mc_gross'] = number_format(
            $this->cart->getTotalGross(),
            2,
            '.',
            ''
        );
    }

    /**
     * @retrun void
     */
    protected function addEachCouponFromCartToQuery()
    {
        if ($this->cart->getCoupons()) {
            $discount = 0;
            /**
             * @var $cartCoupon \Extcode\Cart\Domain\Model\Cart\CartCoupon
             */
            foreach ($this->cart->getCoupons() as $cartCoupon) {
                if ($cartCoupon->getIsUseable()) {
                    $discount += $cartCoupon->getDiscount();
                }
            }

            $this->paymentQuery['discount_amount_cart'] = $discount;
        }
    }

    /**
     */
    protected function addEachProductFromCartToQuery()
    {
        if ($this->orderItem->getProducts()) {
            $count = 0;
            foreach ($this->orderItem->getProducts() as $productKey => $product) {
                $count += 1;

                $this->paymentQuery['item_name_' . $count] = $product->getTitle();
                $this->paymentQuery['quantity_' . $count] = $product->getCount();
                $this->paymentQuery['amount_' . $count] = number_format(
                    $product->getGross() / $product->getCount(),
                    2,
                    '.',
                    ''
                );
            }
        }
    }

    /**
     */
    protected function addEntireCartToQuery()
    {
        $this->paymentQuery['quantity'] = 1;
        $this->paymentQuery['mc_gross'] = number_format(
            $this->cart->getGross() + $this->cart->getServiceGross(),
            2,
            '.',
            ''
        );

        $this->paymentQuery['item_name_1'] = $this->cartMollieConf['settings']['sendEachItemToMollieTitle'];
        $this->paymentQuery['quantity_1'] = 1;
        $this->paymentQuery['amount_1'] = $this->paymentQuery['mc_gross'];
    }
}
