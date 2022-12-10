<?php

namespace MagentoSupport\SupportChecker;

use Magento\Analytics\Model\Config\Backend\CollectionTime;
use Magento\Analytics\Model\ReportUrlProvider;
use Magento\Analytics\Model\SubscriptionStatusProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;

abstract class AbstractDbChecker //implements CheckInterface
{
    /**
     * @var ResourceConnection
     */
    protected $connection;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    protected $resource;

    /**
     * DbDataSeeker constructor.
     * @param ResourceConnection $resource
     */
    public function __construct(ResourceConnection $resource, ScopeConfigInterface $scopeConfig)
    {
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->scopeConfig = $scopeConfig;
        $this->resource = $resource;
    }

    abstract public function getName();

    abstract public function execute(InputInterface $input, OutputInterface $output);

    /**
     * Get data from core config table
     * @param $columns
     * @param $pathValue
     * @return false|string
     */
    protected function selectFromCoreConfig($columns, $pathValue)
    {
        $configTable = $this->connection->getTableName('core_config_data');
        $select = $this->connection->select()->from($configTable, $columns)->where('path LIKE :path');
        $bind = [':path' => $pathValue];
        return $this->connection->fetchAll($select, $bind);
    }
}