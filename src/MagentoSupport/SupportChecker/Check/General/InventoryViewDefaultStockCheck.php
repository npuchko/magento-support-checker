<?php
declare(strict_types=1);

namespace MagentoSupport\SupportChecker\Check\General;

use Magento\Framework\App\ResourceConnection;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class InventoryViewDefaultStockCheck extends AbstractDbChecker
{

    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    private $deploymentConfig;

    public function __construct(ResourceConnection $resource, ScopeConfigInterface $scopeConfig, \Magento\Framework\App\DeploymentConfig $deploymentConfig)
    {
        parent::__construct($resource, $scopeConfig);
        $this->deploymentConfig = $deploymentConfig;
    }

    public function getName()
    {
        return 'Inventory check';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $select = "SHOW FULL TABLES WHERE TABLE_TYPE LIKE '%VIEW%';";
        $rows = $this->connection->fetchAll($select);
        $tables = [];

        $inventoryStockFound = false;
        foreach ($rows as $row) {
            unset($row["Table_type"]);
            $data = array_values($row);
            if ($data[0] === 'inventory_stock_1') {
                $inventoryStockFound = true;
            }
            $tables[] = $data[0];
        }


        if (!$inventoryStockFound) {
            $output->writeln('<error>INVENTORY ERROR: view inventory_stock_1 not found!</error>');
        }
    }
}