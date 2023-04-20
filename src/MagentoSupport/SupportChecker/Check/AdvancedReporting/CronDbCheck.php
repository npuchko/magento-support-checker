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

class CronDbCheck extends AbstractDbChecker
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
        return 'Cron in DB settings';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $jobs = [
            'analytics_subscribe',
            'analytics_update',
            'analytics_collect_data',
        ];
        $output->writeln('');
        foreach ($jobs as $job) {
            $output->writeln('Checking ' . $job);
            $this->checkCronJob($job, $output);
        }

        return false;
    }

    public function checkCronJob($code, OutputInterface $output)
    {
        if (!$this->deploymentConfig->get('cron/enabled', 1)) {
            $output->writeln('<error>' . 'Cron is disabled. Jobs were not run.' . '</error>');
            $output->writeln('<error>Enable cron in app/etc/env.php!</error>');
        }

        $row = $this->selectFromCoreConfig(
            ['scope', 'scope_id', 'value'],
            '%'.$code.'/schedule/cron_expr'
        );

        if (!$row) {
            $output->writeln('<error>Cron executed time not set!</error>');
        } else {
            $output->writeln(json_encode($row));
        }

        $cronDefaultConfig = $this->scopeConfig->getValue('crontab/analytics/jobs/'.$code.'/schedule/cron_expr');
        $cronAnalyticsConfig = $this->scopeConfig->getValue('crontab/default/jobs/'.$code.'/schedule/cron_expr');

        if ($cronAnalyticsConfig && $cronDefaultConfig) {
            $output->writeln('<error>Cron setted up for 2 cron groups: "default" and "analytics".</error>');
            $xmlFile = ROOT_DIRECTORY_FOR_MAGENTO . '/vendor/magento/module-analytics/etc/crontab.xml';
            $data = simplexml_load_string(file_get_contents($xmlFile));

            $rightGroup = (string)$data->group['id'];
            $output->writeln('Current cron group is <comment>'.$rightGroup.'</comment>');
            if ($rightGroup === 'default') {

                $output->writeln('<error>REMOVE crontab/analytics/jobs/'.$code.'/schedule/cron_expr config</error>');
            } else {
                $output->writeln('<error>REMOVE crontab/default/jobs/'.$code.'/schedule/cron_expr config</error>');
            }
        }

        $cronJob = $this->findAnalyticsCronJobInDb($code);
        if (count($cronJob)) {
            $hasErrors = false;
            $errorMessages = [];
            foreach ($cronJob as $job) {
                if ($job['status'] === 'error') {
                    $hasErrors = true;
                    $errorMessages[$job['messages']] = 1;
                }
            }

            if ($hasErrors) {
                $output->writeln('<error>Cron jobs has errors</error>');
                $errorMessages = array_keys($errorMessages);
                $errorMessages = array_slice($errorMessages, 0, 10);
                foreach ($errorMessages as $errorMessage) {
                    $output->writeln('-    <error>' . $errorMessage. '</error>');
                }

                $output->writeln('');
            }

            $output->writeln('Cron jobs in DB: ' . json_encode($cronJob));
        } else {
            $output->writeln('Cron jobs in DB not found');
        }
    }
    /**
     * Find all cron rows
     * @return false|string
     */
    private function findAnalyticsCronJobInDb($code)
    {
        $select = $this->connection->select()->from(
            $this->connection->getTableName('cron_schedule'),
            ['job_code', 'messages', 'status'])->where('job_code LIKE :job_code');
        $bind = [':job_code' => $code];
        return $this->connection->fetchAll($select, $bind);


    }
}