<?php
namespace Custom\CustomPayment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'custompayment';

    protected $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        $publicKey = $this->scopeConfig->getValue(
            'payment/custompayment/api_key_public',
            ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                self::CODE => [
                    'publicKey' => $publicKey
                ]
            ]
        ];
    }
}
