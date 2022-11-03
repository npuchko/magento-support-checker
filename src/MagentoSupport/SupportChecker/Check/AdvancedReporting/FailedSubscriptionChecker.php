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


class FailedSubscriptionChecker extends AbstractDbChecker {

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
        return 'Subscription Checker';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $status = $this->subscriptionStatusProvider->getStatus();

        if (
            $status === \Magento\Analytics\Model\SubscriptionStatusProvider::FAILED
            || $status === \Magento\Analytics\Model\SubscriptionStatusProvider::PENDING
        ) {
            $url = $this->scopeConfig->getValue(Store::XML_PATH_SECURE_BASE_URL);
            $output->writeln('URL for subscription: ' . $url);
            $output->writeln('Open this url, it should not be under HTTP AUTH or firewall!');
        }

        return true;
    }
}