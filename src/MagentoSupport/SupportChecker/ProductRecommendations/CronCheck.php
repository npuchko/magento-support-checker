<?php

namespace MagentoSupport\SupportChecker\ProductRecommendations;


use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class CronCheck extends AbstractDbChecker
{

    public function getName()
    {
        return 'Cron';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $table = $this->resource->getTableName('cron_schedule');

        $jobCodes = ['submit_product_feed', 'submit_product_metadata_feed'];
        $statuses = ['error', 'missed', 'pending', 'success'];

        $sql = "SELECT count(*) as cnt, job_code, status, MAX(executed_at) as last FROM {$table}
            WHERE job_code IN ('submit_product_feed', 'submit_product_metadata_feed')
            GROUP BY job_code, status;";

        $rows = $this->connection->fetchAll($sql);

        $data = [];
        foreach ($rows as $row) {
            $data[$row['job_code'] . '_' . $row['status']] = ['count' => $row['cnt'], 'last' => $row['last']];
        }
        $output->writeln('');
        $hasErrors = false;
        $notHaveSuccess = false;
        foreach ($jobCodes as $jobCode) {
            $output->writeln('Job code ' . $jobCode . ' ');
            foreach ($statuses as $status) {
                $count = $data[$jobCode . '_' . $status]['count'] ?? '0';
                if ($status === 'error' && $count > 0) {
                    $hasErrors = true;
                }

                if ($status === 'success' && $count == 0) {
                    $notHaveSuccess = true;
                }
                $last = $data[$jobCode . '_' . $status]['last'] ?? ' N/A';
                $output->writeln("{$status} - {$count} rows, last exec time {$last}");
            }
            $output->writeln('');
        }

        if ($hasErrors) {
            $output->writeln('<error>Cron jobs has errors</error>');
            return false;
        }
        if ($notHaveSuccess) {
            $output->writeln('<error>No one success cron job</error>');
            return false;
        }

        return true;
    }
}