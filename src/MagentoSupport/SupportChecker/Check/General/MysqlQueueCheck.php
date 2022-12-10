<?php
declare(strict_types=1);

namespace MagentoSupport\SupportChecker\Check\General;

use Magento\Framework\App\ResourceConnection;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class MysqlQueueCheck extends AbstractDbChecker
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
        return 'Mysql queue message check';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $runByCron = $this->deploymentConfig->get('cron_consumers_runner/cron_run');

        if (!$runByCron) {
            $output->writeln('<comment>Queue consumers didn\'t work by cron</comment>');
        }
        $whitelistedQueues = $this->deploymentConfig->get('cron_consumers_runner/consumers');
        $query = 'SELECT q.name, status, COUNT(*) as cnt FROM
    queue_message_status as s
    left join queue q on s.queue_id = q.id
group by queue_id, status';

        $rows = $this->connection->fetchAll($query);

        foreach ($rows as $row) {

            if ((int)$row['status'] === 4) {
                $output->writeln('<info>Queue '.$row['name'].' has ' . $row['cnt'] . ' processed rows</info>');

                continue;
            }
            $output->writeln('<error>Queue '.$row['name'].' has ' . $row['cnt'] . ' unprocessed rows with status '. $row['status'] .'</error>');
            if (!empty($whitelistedQueues) && !in_array($row['name'], $whitelistedQueues)) {
                $output->writeln('<error>Queue '.$row['name'].' didn\'t listed in the app/etc/env.php -> cron_consumers_runner->consumers !</error>');

            }
        }
    }
}