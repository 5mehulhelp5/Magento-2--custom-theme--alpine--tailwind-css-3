<?php

namespace Custom\CustomPayment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\Method\Logger;
use Custom\CustomPayment\HttpClient\Factory as HttpClientFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Payment\Model\Method\Logger as PaymentLogger;
use Psr\Log\LoggerInterface;
class PaymentMethod extends AbstractMethod
{
    protected $_code = 'custompayment';
    protected $httpClient;
	protected $logger;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        PaymentHelper $paymentData,
        ScopeConfigInterface $scopeConfig,
        PaymentLogger $logger,
        HttpClientFactory $httpClientFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->httpClient = $httpClientFactory->createClient();

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
		$order = $payment->getOrder();
        $apiUrl = $this->scopeConfig->getValue('payment/custompayment/api_url', ScopeInterface::SCOPE_STORE);
        $apiKey = $this->scopeConfig->getValue('payment/custompayment/api_key_private', ScopeInterface::SCOPE_STORE);
		error_log('API KEY usada: ' . $apiKey);
		$currency = $order->getOrderCurrencyCode();
    	$orderId = $order->getIncrementId();
    	$additionalInfo = $payment->getAdditionalInformation();
    	$source = $payment->getAdditionalInformation('source');
		
		if (!$source) {
			throw new \Magento\Framework\Exception\LocalizedException(__('No se ha proporcionado el token de pago.'));
		}
        $payload = [
			'amount' => (int)($amount * 100),
			'currency' => 'eur',
			'payment_method_types[]' => 'card',
			'description' => 'Pedido #' . $orderId,
		];
		error_log('api url: ' . $apiUrl);
        try {
            $response = $this->httpClient->request('POST', $apiUrl, [
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey,
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body' => http_build_query($payload),
			]);

            $statusCode = $response->getStatusCode();
			$content = $response->getContent(false);
    		$data = json_decode($content, true);
            if ($statusCode !== 200) {
				throw new \Magento\Framework\Exception\LocalizedException(
					__('Error en la autorización del pago. Código: %1. Respuesta: %2', $statusCode, $content)
				);
			}
        } catch (\Exception $e) {
             throw new \Magento\Framework\Exception\LocalizedException(
       				 __('Error al conectar con la API de pago: %1', $e->getMessage())
    		);
        }

        $payment->setIsTransactionClosed(0);
        return $this;
    }
	
	public function assignData(\Magento\Framework\DataObject $data)
	{
		parent::assignData($data);

		$additionalData = $data->getData('additional_data');
		if (is_array($additionalData) && isset($additionalData['source'])) {
			$this->getInfoInstance()->setAdditionalInformation('source', $additionalData['source']);
		}

		return $this;
	}
}
