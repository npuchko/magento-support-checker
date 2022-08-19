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


class IsMultipleCurrency extends AbstractDbChecker
{

    public function getName()
    {
        return 'Multiple Currency';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $result = $this->isMultiCurrency();

        if (count($result) > 1) {
            $output->writeln('<error>There is multiple currencies was in db found:' . json_encode($result) . '</error>');
            return false;
        }

        return true;
    }

    /**
     * check multiCurrency
     * @return string
     */
    private function isMultiCurrency()
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('sales_order'),
            ['base_currency_code', 'COUNT(*)'])
            ->distinct(true)
            ->group('base_currency_code');
        $result = $this->connection->fetchAll($select);

        return $result;
        if (count($result) > 1) {
            return 'There is multiple currencies was found:' . json_encode($result);
        } else {
            return 'No multiple currencies was found';
        }
    }
}