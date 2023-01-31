<?php

namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CategoryPermissionsCheck extends AbstractDbChecker
{

    public function getName()
    {
        return 'Category permissions check';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $table = $this->resource->getTableName('magento_catalogpermissions');
        $sql = "SELECT count(*) as cnt FROM {$table}";
        $count = $this->connection->fetchOne($sql);

        if (!$count) {
            $output->writeln('Category permissions not currently enabled');
            //return true;
        }
        $table = $this->resource->getTableName('catalog_data_exporter_product_overrides');
        $sql = "SELECT count(*) as cnt, SUM(IF(feed_data like '%displayable%', 1, 0)) as count_permissions FROM {$table};";
        $row = $this->connection->fetchRow($sql);

        if ($row['count_permissions'] == 0) {
            return true;
        }

        if ($row['cnt'] !== $row['count_permissions']) {
            $output->writeln('<error>Not ALL products have category permissions</error>');
        }

        /* Find all customer groups without enabled permissions */
        /*
         SELECT customer_group.customer_group_id, customer_group_code, sha1(customer_group.customer_group_id) FROM customer_group
LEFT JOIN magento_catalogpermissions mc on customer_group.customer_group_id = mc.customer_group_id

WHERE mc.customer_group_id is null;
         */


        return false;
    }
}