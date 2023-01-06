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


class ReportUrl extends AbstractDbChecker
{

    /**
     * @var ReportUrlProvider
     */
    private $reportUrlProvider;

    public function __construct(
        ResourceConnection                                 $resource,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        ReportUrlProvider                                  $reportUrlProvider
    )
    {
        parent::__construct($resource, $scopeConfig);

        $this->reportUrlProvider = $reportUrlProvider;
    }

    public function getName()
    {
        return 'Generated report URL';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $url = $this->reportUrlProvider->getUrl();

            $output->writeln($url);
        } catch (\Throwable $e) {
            $output->writeln('<error>ERROR! </error>');
            $output->writeln((string)$e);
        }


        return false;
    }
}