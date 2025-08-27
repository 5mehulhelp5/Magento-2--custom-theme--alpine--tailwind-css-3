<?php
namespace Custom\ProductUpdater\Cron;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Custom\ProductUpdater\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;

class UpdateProducts
{
    protected $productRepository;
    protected $stockRegistry;
    protected $helper;
    protected $scopeConfig;
    protected $logger;
    protected $indexerRegistry;
    protected $appState;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        HelperData $helper,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        IndexerRegistry $indexerRegistry,
        State $appState
    ) {
        $this->productRepository = $productRepository;
        $this->stockRegistry     = $stockRegistry;
        $this->helper            = $helper;
        $this->scopeConfig       = $scopeConfig;
        $this->logger            = $logger;
        $this->indexerRegistry   = $indexerRegistry;
        $this->appState          = $appState;
    }

    public function execute()
    {
       
    
        $this->logger->info("Cron started");

        $enabled = $this->scopeConfig->getValue('product_updater/general/enable_cron');
        if (!$enabled) {
            $this->logger->info("Cron is disabled in configuration");
            return;
        }

        $products = $this->helper->getJsonData();
        $this->logger->info("Products read from JSON: " . count($products));

        foreach ($products as $data) {
            try {
                $sku = isset($data['sku']) && !empty($data['sku']) ? (string)$data['sku'] : null;

                if (empty($sku)) {
                    $this->logger->error("Empty SKU in JSON, skipping record");
                    continue;
                }

                $qty = isset($data['stock']['qty']) && is_numeric($data['stock']['qty']) ? (int)$data['stock']['qty'] : 0;
                $isInStock = isset($data['stock']['is_in_stock']) ? (bool)$data['stock']['is_in_stock'] : ($qty > 0);

                $updateData = [
                    'name'        => $data['name'] ?? null,
                    'price'       => $data['price'] ?? null,
                    'status'      => $data['status'] ?? null,
                    'stock_qty'   => $qty,
                    'is_in_stock' => $isInStock
                ];

                $this->logger->info("Prepared update array for SKU {$sku}: " . print_r($updateData, true));

                // Update product
                $product = $this->productRepository->get($sku);

                if ($updateData['name'])   $product->setName($updateData['name']);
                if ($updateData['price'])  $product->setPrice($updateData['price']);
                if ($updateData['status']) $product->setStatus($updateData['status']);

                $this->productRepository->save($product);

                // Update stock
                $stockItem = $this->stockRegistry->getStockItemBySku($sku);
                $stockItem->setQty($qty);
                $stockItem->setIsInStock($isInStock);
                $this->stockRegistry->updateStockItemBySku($sku, $stockItem);

                $this->logger->info("Stock updated for SKU {$sku}: qty={$qty}, in_stock=" . (int)$isInStock);

            } catch (NoSuchEntityException $e) {
                $this->logger->warning("Product not found: {$sku}");
            } catch (\Exception $e) {
                $this->logger->error("Error updating product {$sku}: " . $e->getMessage());
            }
        }

        $this->reindexAll();
        $this->logger->info("Reindex completed after updating products");
        $this->logger->info("Cron finished");
    }

    private function reindexAll()
    {
        $indexers = [
            'catalog_product_price',
            'cataloginventory_stock',
            'catalog_product_attribute',
            'catalogsearch_fulltext'
        ];

        foreach ($indexers as $indexerId) {
            try {
                $indexer = $this->indexerRegistry->get($indexerId);
                $indexer->reindexAll();
                $this->logger->info("Reindexed: {$indexerId}");
            } catch (\Exception $e) {
                $this->logger->error("Error reindexing {$indexerId}: " . $e->getMessage());
            }
        }
    }
}
