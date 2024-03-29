<?php

namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class SyncCheck extends AbstractDbChecker
{
    private $storeManager;
    private $serviceClient;

    public function __construct(ResourceConnection                                 $resource,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                StoreManagerInterface                              $storeManager,
                                ServiceClientInterface                             $serviceClient)
    {
        parent::__construct($resource, $scopeConfig);
        $this->storeManager = $storeManager;
        $this->serviceClient = $serviceClient;
    }

    public function getName()
    {
        return 'Sync Status';
    }

    /**
     * @return array
     */
    private function getCountsForStoreViews()
    {
        $indexTable = $this->connection->getTableName('catalog_data_exporter_products');
        /*
         select
    store_view_code, count(*) as count,
    SUM(IF(feed_data like '%displayable":true%', 1, 0)) as count_displayable,
    SUM(IF(feed_data like '%inStock":true%', 1, 0)) as count_in_stock,
    SUM(IF(feed_data like '%buyable":true%', 1, 0)) as count_buyable
    from `catalog_data_exporter_products` group by store_view_code;
         */
        $countInIndexSql = "select 
    store_view_code, count(*) as count, 
    SUM(IF(feed_data like '%displayable\":true%', 1, 0)) as count_displayable, 
    SUM(IF(feed_data like '%inStock\":true%', 1, 0)) as count_in_stock, 
    SUM(IF(feed_data like '%buyable\":true%', 1, 0)) as count_buyable
    from `{$indexTable}` group by store_view_code";

        $counts = $this->connection->fetchAll($countInIndexSql);

        $result = [];
        foreach ($counts as $row) {
            $result[$row['store_view_code']] = [
                'count_total' => $row['count'],
                'count_displayable' => $row['count_displayable'],
                'count_in_stock' => $row['count_in_stock'],
                'count_buyable' => $row['count_buyable'],
            ];
        }

        return $result;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $envId = $this->scopeConfig->getValue('services_connector/services_id/environment_id');
        if (!$envId) {
            $output->writeln('<info>No env ID!</info>');
            return false;
        }

        $counts = $this->getCountsForStoreViews();
        $stores = $this->storeManager->getStores(false);

        foreach ($stores as $store) {
            $website = $this->storeManager->getWebsite($store->getWebsiteId())->getCode();
            $storeCode = $store->getCode();
            $output->writeln('Store ' . $storeCode);


            $url = "catalogsyncstatus/environments/{$envId}/websites/{$website}/storeviews/{$storeCode}/aggregated";

            $baseRoute = $this->scopeConfig->getValue('product_recommendations_sync_admin/admin_api_path');
            $apiUrl = $this->serviceClient->getUrl($baseRoute, 'v1', $url);


            $response = $this->serviceClient->request('GET', $apiUrl, '');

            if (!isset($response['storeViewSyncStatusResponse'])) {
                $output->writeln('<error>ERROR:</error> ' . json_encode($response));
                continue;
            }
            $output->writeln(
                "Total synced products on SaaS side: {$response['documentCountResponse']['documentCount']}"
            );

            $countInIndex = $counts[$storeCode]['count_total'] ?? 0;
            $countDisplayableInIndex = $counts[$storeCode]['count_displayable'] ?? 0;
            $countBuyableInIndex = $counts[$storeCode]['count_buyable'] ?? 0;
            $countInStockInIndex = $counts[$storeCode]['count_in_stock'] ?? 0;

            if ((int)$countInIndex !== (int)$response['documentCountResponse']['documentCount']) {
                $output->write(
                    "<error>Counts are different!</error>"
                );
            }

            $output->writeln(
                "SaaS count: {$response['documentCountResponse']['documentCount']}, Magento Index count: {$countInIndex},"
                . " Displayable=true Magento Index Count: {$countDisplayableInIndex}"
                . " Buyable=true Magento Index Count: {$countBuyableInIndex}"
                . " Is In Stock=true Magento Index Count: {$countInStockInIndex}"
            );

            $output->writeln(
                "Last Sync - Num synced: {$response['storeViewSyncStatusResponse']['numSynced']}, "
                . "Last Time: {$response['storeViewSyncStatusResponse']['lastSyncTs']}, "
                . "Status: {$response['storeViewSyncStatusResponse']['status']} "
            );

            $url = "/{$envId}/{$storeCode}/units";
            $apiUrl = $this->serviceClient->getUrl($baseRoute, 'v1', $url);


            $response = $this->serviceClient->request('GET', $apiUrl, '');

            $output->writeln('Units: ');
            $output->writeln(json_encode($response));


            foreach ($response['results'] as $unit) {
                $found = false;
                foreach ($unit['filterRules'] ?? [] as $filterRule) {
                    foreach ($filterRule['conditions'] as $condition) {
                        if ($condition['field'] === 'category') {
                            //$found = true;

                            foreach ($condition['operator']['customOperator']['value'] ?? [] as $val) {
                                if (is_null($val)) {
                                    $found = true;
                                }
                            }
                            break 2;
                        }
                    }
                }

                if ($found) {
                    $output->writeln('<error>Invalid category filter in: ' . "Unit {$unit['unitId']} {$unit['unitName']}" . '</error>');
                }
            }


            $output->writeln('');
        }


        return null;
    }
}