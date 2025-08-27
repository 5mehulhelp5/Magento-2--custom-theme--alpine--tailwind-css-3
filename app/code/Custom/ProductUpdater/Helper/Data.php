<?php
namespace Custom\ProductUpdater\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    const XML_PATH_JSON_FILE = 'product_updater/general/json_file_path';
    
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    /**
     * read and decodify JSON
     */
        public function getJsonData()
    {
        // Obtener la ruta configurada desde admin
        $relativePath = $this->scopeConfig->getValue(
            self::XML_PATH_JSON_FILE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

         $filePath = BP . '/' . ltrim($relativePath, '/');
        echo $filePath;

        // Convertir a ruta absoluta dentro de Magento
        if ($filePath && strpos($filePath, '/') !== 0) {
            // Ruta relativa → añadimos BP
            $filePath = BP . '/' . ltrim($filePath, '/');
        }

        if (!$filePath || !file_exists($filePath)) {
            $this->logger->error("JSON file not found: " . $filePath);
            return [];
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("JSON decoding error: " . json_last_error_msg());
            return [];
        }

        return $data;
    }

    /**
     * Validate structure of JSON
     */
    public function validateProductData(array $product)
    {
        return isset($product['sku']);
    }
}
