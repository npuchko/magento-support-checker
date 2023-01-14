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



class FlagChecker extends AbstractDbChecker
{
    public function getName()
    {
        return 'Last generated report in flag';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $newBaseUrlFlag = $this->getUpdateBaseUrlFlag();
        $counterFlag = $this->getCounterFlag();

        if ($newBaseUrlFlag) {
            $output->writeln('<error>Base URL was changed! Old base url is: '.$newBaseUrlFlag['flag_data'].'</error>');

            if (!$counterFlag) {
                $output->writeln('<error>Counter flag NOT found! Check error logs!!</error>');
            }

            $row = $this->selectFromCoreConfig(
                ['scope', 'scope_id', 'value'],
                '%analytics_update/schedule/cron_expr'
            );

            if (!$row) {
                $output->writeln('<error>Job schedule for analytics_update cron job NOT FOUND</error>');
            }
        }
        $flag = $this->checkFlagTable();

        if (!count($flag)) {
            $output->writeln('<error>Flag not found, report wasnt generated</error>');

            if ($counterFlag) {
                $output->writeln('<error>Counter flag found: ' . json_encode($counterFlag) . '</error>');
            }

            return false;
        }

        $flag = $flag[0];
        $lastUpdate = new \DateTime($flag['last_update']);
        $currentDate = new \DateTime();
        $diffDays = $currentDate->diff($lastUpdate)->format('%a');

        $isError = false;
        if ($diffDays > 2) {
            $output->write('<error>' . $diffDays . ' days ago. (' . $flag['last_update'] . ')</error>');
            $isError = true;
        } else {
            $output->write($diffDays . ' days ago. (' . $flag['last_update'] . ') ');
        }

        $flagData = json_decode($flag['flag_data'], true);
        $filePath = ROOT_DIRECTORY_FOR_MAGENTO . 'pub/media/' . $flagData['path'];

        $output->writeln('');
        if (!is_file($filePath)) {
            $isError = true;
            $output->writeln('<error>File not found!</error> ' . $filePath);
        }

        $url = $this->scopeConfig->getValue('web/secure/base_url') . 'media/' . $flagData['path'];
        $output->writeln('Try to open File URL: ' . $url);


        return !$isError;
    }

    private function checkFlagTable()
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('flag'),
            ['flag_code', 'flag_data', 'last_update'])
            ->where('flag_code LIKE :flag_code');
        $bind = [':flag_code' => 'analytics_file_info'];
        return $this->connection->fetchAll($select, $bind);
    }

    private function getCounterFlag()
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('flag'),
            ['flag_code', 'flag_data', 'last_update'])
            ->where('flag_code LIKE :flag_code');
        $bind = [':flag_code' => 'analytics_link_subscription_update_reverse_counter'];
        return $this->connection->fetchAll($select, $bind);
    }

    private function getUpdateBaseUrlFlag()
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('flag'),
            ['flag_code', 'flag_data', 'last_update'])
            ->where('flag_code LIKE :flag_code');
        $bind = [':flag_code' => 'analytics_previous_base_url'];
        return $this->connection->fetchAll($select, $bind);
    }
}