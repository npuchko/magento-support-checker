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

class Checker
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function runChecks(array $checksGroups, ?string $checkGroup, InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $result = [];
        $found = false;
        foreach ($checksGroups as $group => $checkClasses) {

            if ($checkGroup !== null && $checkGroup != $group) {
                continue;
            }
            $found = true;
            $result[$group] = [];

            $total = count($checkClasses);
            $output->writeln("Check group: {$group} with total {$total} rules'");
            foreach ($checkClasses as $i => $checkClass) {
                try {
                    /** @var AbstractDbChecker $check */
                    $check = $this->objectManager->get($checkClass);

                    $i++;
                    $output->write("[{$i}/$total] " . $check->getName() . ': ');
                    $result = $check->execute($input, $output);
                } catch (\Throwable $e) {
                    $result = false;
                    $output->writeln('<error>Error running check '.$checkClass.'</error>');

                    $output->writeln('<error>'.$e->getMessage().'</error>');
                }

                if ($result === true) {
                    $output->writeln('<info>OK</info>');
                }
            }
            $output->writeln('');
        }

        if (!$found) {
            $output->writeln('<error>Checks id '.$checkGroup.' are not found!</error>');
        }

        return $result;
    }
}