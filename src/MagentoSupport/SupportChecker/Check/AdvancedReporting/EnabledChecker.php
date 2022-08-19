<?php

namespace MagentoSupport\SupportChecker\Check\AdvancedReporting;

use Magento\Analytics\Model\Config\Backend\CollectionTime;
use Magento\Analytics\Model\ReportUrlProvider;
use Magento\Analytics\Model\SubscriptionStatusProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;

class EnabledChecker extends AbstractDbChecker
{
    /**
     * @var \Magento\Analytics\Model\SubscriptionStatusProvider
     */
    private $subscriptionStatusProvider;

    public function __construct(
        ResourceConnection $resource,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Analytics\Model\SubscriptionStatusProvider $subscriptionStatusProvider
    ) {
        parent::__construct($resource, $scopeConfig);
        $this->subscriptionStatusProvider = $subscriptionStatusProvider;
    }

    public function getName()
    {
        return 'Is Enabled';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $subscriptionStatus = $this->subscriptionStatusProvider->getStatus();
        $output->writeln('');
        $output->writeln('Subscription status: ' . $subscriptionStatus);
        if (SubscriptionStatusProvider::PENDING === $subscriptionStatus) {
            $output->writeln('<error>Check for cron job analytics_subscribe</error>');
        }

        if (SubscriptionStatusProvider::FAILED === $subscriptionStatus) {
            $output->writeln('<error>Check for cron job analytics_subscribe in the past</error>');
        }
        $isError = false;
        $dbRows = $this->selectFromCoreConfig(
            ['scope', 'scope_id', 'value'],
            'analytics/subscription/enabled'
        );
        foreach ($dbRows as $dbRow) {
            if (!$dbRow['value']) {
                // checking if app/etc/*.php files overrides this.
                $isSetFlag = $this->scopeConfig->isSetFlag('analytics/subscription/enabled', $dbRow['scope'], $dbRow['scope_id']);

                if (!$isSetFlag) {
                    $isError = true;
                    $output->writeln("<error>Module disabled in DB for scope: {$dbRow['scope']} = {$dbRow['scope_id']}</error>");
                }
            }
        }

        if ($isError) {
            return false;
        }
        $isModuleEnabledByConfig = $this->scopeConfig->isSetFlag('analytics/subscription/enabled');

        if (!$isModuleEnabledByConfig) {
            $output->writeln("<error>Module disabled in app/etc/config.php or app/etc/env.php</error>");
            return false;
        }

        return true;
    }
}