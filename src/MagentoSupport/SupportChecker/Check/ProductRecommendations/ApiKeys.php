<?php
namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ApiKeys extends AbstractDbChecker
{

    public function getName()
    {
        return 'API keys';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $productionApiKey = $this->scopeConfig->getValue('services_connector/services_connector_integration/production_api_key');
        $private = $this->scopeConfig->getValue('services_connector/services_connector_integration/production_private_key');


        if (!$productionApiKey) {
            $output->writeln('<error>Production API key not found!</error>');
        }

        if (!$private) {
            $output->writeln('<error>Production Private key not found!</error>');
        }

        return $productionApiKey && $private;
    }
}