<?php

namespace MagentoSupport\SupportChecker\AdvancedReporting;

use Magento\Analytics\Model\Config\Backend\CollectionTime;
use Magento\Analytics\Model\ReportUrlProvider;
use Magento\Analytics\Model\SubscriptionStatusProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use MagentoSupport\SupportChecker\AbstractDbChecker;


class TokenChecker extends AbstractDbChecker
{
    /**
     * @var Magento\Analytics\Model\AnalyticsToken
     */
    private $analyticsToken;

    public function __construct(
        ResourceConnection $resource,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Analytics\Model\AnalyticsToken $analyticsToken
    ) {
        parent::__construct($resource, $scopeConfig);
        $this->analyticsToken = $analyticsToken;
    }

    public function getName()
    {
        return 'Token in settings';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configToken = $this->scopeConfig->getValue('analytics/general/token');

        $dbToken = $this->selectFromCoreConfig(
            ['scope', 'scope_id', 'value'],
            'analytics/general/token'
        );

        if (!$configToken && (!$dbToken || empty($dbToken[0]['value']))) {
            $output->writeln('<error>No Token found</error>');

            $this->checkCounter($output);
            return false;
        }

        $dbToken = $dbToken[0]['value'] ?? null;
        if ($configToken == $dbToken) {
            return true;
        }

        $output->writeln('<error>Token setted in app/etc/*.php file</error>');

        return false;
    }

    private function checkCounter(OutputInterface $output)
    {
        $configTable = $this->connection->getTableName('flag');
        $select = $this->connection->select()->from($configTable)->where('flag_code = :flag_code');
        $bind = [':flag_code' => 'analytics_link_subscription_update_reverse_counter'];
        $rows =  $this->connection->fetchAll($select, $bind);

        if ($rows) {
            $output->writeln('<error>Counter flag found:</error>' . json_encode($rows));
        }
    }
}