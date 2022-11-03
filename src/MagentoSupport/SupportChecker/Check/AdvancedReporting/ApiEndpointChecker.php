<?php

namespace MagentoSupport\SupportChecker\Check\AdvancedReporting;

use Magento\Analytics\Model\Config\Backend\CollectionTime;
use Magento\Analytics\Model\ReportUrlProvider;
use Magento\Analytics\Model\SubscriptionStatusProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\Store\Model\Store;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Magento\Config\Model\Config as SystemConfig;


class ApiEndpointChecker extends AbstractDbChecker
{
    /**
     * @var IntegrationServiceInterface
     */
    private $integrationService;
    /**
     * @var OauthServiceInterface
     */
    private $oauthService;
    /**
     * @var SystemConfig
     */
    private $config;

    public function __construct(
        ResourceConnection $resource,
        ScopeConfigInterface $scopeConfig,
        IntegrationServiceInterface $integrationService,
        OauthServiceInterface $oauthService,
        SystemConfig $config
    ) {
        parent::__construct($resource, $scopeConfig);
        $this->integrationService = $integrationService;
        $this->oauthService = $oauthService;
        $this->config = $config;
    }

    public function getName()
    {
        return 'Api Endpoint';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $this->scopeConfig->getValue('web/secure/base_url') . 'rest/V1/analytics/link';

        try {
            $integrationName = $this->config->getConfigDataValue('analytics/integration_name');

            $output->writeln('Integration name ' . $integrationName);
            $integration = $this->integrationService->findByName($integrationName);
            if (!$integration->getId()) {
                throw new \Exception('Integration with name "' . $integrationName .  '" does not exists!');
            }

            if ($integration->getStatus() === Integration::STATUS_INACTIVE) {
                throw new \Exception('Integration "' . $integrationName .  '" is not active!');
            } else {
                $output->writeln('Integration is active');
            }
            $consumerId = $integration->getConsumerId();
            $accessToken = $this->oauthService->getAccessToken($consumerId);

            if (!$accessToken) {
                throw new \Exception('Access token on integration ' . $integrationName . ' is empty!');
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>Something went wrong with Integration Consumer! '.$e->getMessage().' </error>');
            return false;
        }

        $command = "curl --location --request GET '{$url}' \
--header 'Authorization: Bearer {$accessToken->getData('token')}'";
        $output->writeln('Try to run cURL in cli: ');
        $output->writeln('=============');
        $output->writeln($command);
        $output->writeln('=============');


        return true;

    }
}