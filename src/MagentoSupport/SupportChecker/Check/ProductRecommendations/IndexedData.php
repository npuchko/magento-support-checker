<?php

namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexedData extends AbstractDbChecker
{

    /**
     * @var \Magento\Indexer\Model\Indexer\CollectionFactory
     */
    private $indexerCollectionFactory;

    public function __construct(ResourceConnection                               $resource, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Magento\Indexer\Model\Indexer\CollectionFactory $indexerCollectionFactory)
    {
        parent::__construct($resource, $scopeConfig);
        $this->indexerCollectionFactory = $indexerCollectionFactory;
    }

    public function getName()
    {
        return 'Indexed products';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dataExporterCount = $this->count('catalog_data_exporter_products');
        $productsTotalCount = $this->count('catalog_product_entity');
        $storeCount = $this->count('store') - 1; // remove admin store from count

        $countOfProductsWithoutWebsite = $this->getCountOfProductsWithoutWebsites();

        $output->writeln("catalog_data_exporter_products index has {$dataExporterCount} rows in {$storeCount} stores (catalog_product_entity has {$productsTotalCount})");

        $countAttributes = $this->count("catalog_data_exporter_product_attributes");
        $output->writeln("catalog_data_exporter_product_attributes index has {$countAttributes} rows");

        $countOverrides = $this->count("catalog_data_exporter_product_overrides");
        $countCustomerGroups = $this->count("customer_group");
        $output->writeln("catalog_data_exporter_product_overrides index has {$countOverrides} rows (customer groups count {$countCustomerGroups})");



        $percent = 0;
        if ($productsTotalCount != 0) {
            $percent = round(($countOfProductsWithoutWebsite / $productsTotalCount) * 100);
        }
        $output->writeln("{$percent}% - {$countOfProductsWithoutWebsite} products of {$productsTotalCount} doesn't have website");

        if ($percent > 30) {
            $output->writeln("<error>More than 30% ({$percent}%) products don't have a website!</error>");
        }

        $indexers = $this->getAllIndexers();

        $isError = false;
        foreach ($indexers as $indexerId => $indexer) {
            if (strpos($indexerId, "catalog_data_exporter_") !== 0) {
                continue;
            }

            if (!$indexer->isScheduled()) {
                $output->writeln("<error>Indexer {$indexerId} must be set \"Update By Schedule\" !</error>");
                $isError = true;
            }
        }

        if (!$isError) {
            $output->writeln("<info>Indexers are set \"By Schedule\"</info>");
        }

        return false;
    }

    private function count($table)
    {
        try {
            $table = $this->resource->getTableName($table);
            $sql = "SELECT COUNT(*) FROM {$table}";
            return $this->connection->fetchOne($sql);
        } catch (\Throwable $e) {
            return -1;
        }

    }

    private function getCountOfProductsWithoutWebsites()
    {
        $productTable = $this->resource->getTableName('catalog_product_entity');
        $productWebsiteTable = $this->resource->getTableName('catalog_product_website');
        $sql = "SELECT COUNT(*)
            FROM {$productTable} as e
            LEFT JOIN {$productWebsiteTable} as w on e.entity_id = w.product_id WHERE w.website_id IS NULL";

        return $this->connection->fetchOne($sql);
    }

    private function getAllIndexers()
    {
        $indexers = $this->indexerCollectionFactory->create()->getItems();
        return array_combine(
            array_map(
                function ($item) {
                    /** @var IndexerInterface $item */
                    return $item->getId();
                },
                $indexers
            ),
            $indexers
        );
    }
}