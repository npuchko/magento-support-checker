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


class EscapedQuotesChecker extends AbstractDbChecker
{

    public function getName()
    {
        return 'Escaped quotes in db';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $escapedQuotes = $this->checkEscapedQuotes();
        $escapedQuotesCount = count($escapedQuotes);

        if ($escapedQuotesCount > 0) {
            $output->writeln('<error>Found '.$escapedQuotesCount.' rows with escaped quotes</error>');
            $out = array_slice($escapedQuotes, 0, 10);
            foreach ($out as $product) {
                $output->writeln($product['sku'] . ' - ' . $product['name']);
            }

            $output->writeln('');
            return false;
        }

        return true;
    }

    /**
     * Find escaped quotes
     * @return false|string
     */
    private function checkEscapedQuotes()
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('sales_order_item'),
            ['name', 'COUNT(*)', 'sku'])
            ->where('name like \'%\\\\\\\\"%\' or name like \'%\"%\' ')
            ->group(['name', 'sku']);
        $result = $this->connection->fetchAll($select);
        return ($result);

    }
}