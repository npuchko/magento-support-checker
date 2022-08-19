<?php

namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexedData extends AbstractDbChecker
{

    public function __construct(ResourceConnection $resource, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        parent::__construct($resource, $scopeConfig);
    }

    public function getName()
    {
        return 'Indexed products';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dataExporterCount = $this->count('catalog_data_exporter_products');
        $productsTotalCount = $this->count('catalog_product_entity');
        $storeCount = $this->count('store');

        $output->writeln("{$dataExporterCount} rows in {$storeCount} stores (catalog_product_entity has {$productsTotalCount})");

        return false;
    }

    private function count($table)
    {
        $table = $this->resource->getTableName($table);

        $sql = "SELECT COUNT(*) FROM {$table}";
        return $this->connection->fetchOne($sql);
    }
}