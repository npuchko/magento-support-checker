<?php

namespace MagentoSupport\SupportChecker\Check\AdvancedReporting;

use Magento\Analytics\Model\Config\Backend\CollectionTime;
use Magento\Analytics\Model\ReportUrlProvider;
use Magento\Analytics\Model\SubscriptionStatusProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use MagentoSupport\SupportChecker\AbstractDbChecker;


class StoreInconsistencyChecker extends AbstractDbChecker
{

    public function getName()
    {
        return 'Check unexisting stores';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $result = $this->connection->fetchCol(
            "SELECT store_id FROM sales_order WHERE store_id not in (select store_id from store) GROUP BY store_id"
        );

        if (count($result) > 0) {
            $output->writeln('<error>sales_order contains unexisting store ids ' . json_encode() . '</error>');

            return false;
        }

        return true;

    }
}