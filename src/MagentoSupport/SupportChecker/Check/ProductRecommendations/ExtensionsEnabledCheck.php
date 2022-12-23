<?php
declare(strict_types=1);

namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\Framework\App\ResourceConnection;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ExtensionsEnabledCheck extends AbstractDbChecker
{
    /**
     * @var Manager
     */
    private $moduleManager;

    public function __construct(ResourceConnection $resource, ScopeConfigInterface $scopeConfig, Manager $moduleManager)
    {
        parent::__construct($resource, $scopeConfig);
        $this->moduleManager = $moduleManager;
    }

    public function getName()
    {
        return 'Enabled extensions';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $extensions = [
            'Magento_ServicesId',
            'Magento_ServicesConnector',
            'Magento_DataExporter',
            'Magento_CatalogDataExporter',
            'Magento_CatalogInventoryDataExporter',
            'Magento_ConfigurableProductDataExporter',
            'Magento_BundleProductDataExporter',
            'Magento_ParentProductDataExporter',
            'Magento_ProductOverrideDataExporter',
            'Magento_CatalogUrlRewriteDataExporter',
            'Magento_SaaSCommon',
            'Magento_SaaSCatalog',
            'Magento_SaaSProductOverride',
        ];

        $pwaExtensions = [
            'Magento_DataServicesGraphQl',
            'Magento_ServicesIdGraphQlServer',
        ];

        $result = $this->checkExtensions($extensions, $output);
        if ($result) {
            $output->writeln('<info>OK</info>');
        }
        $output->writeln('<comment>Checking PWA extensions:</comment>');


        $result = $this->checkExtensions($pwaExtensions, $output);


        return null;
    }

    private function checkExtensions($extensions, OutputInterface $output)
    {
        $isError = false;
        foreach ($extensions as $extension) {
            if (!$this->moduleManager->isEnabled($extension)) {
                $output->writeln('<error>'.$extension.' is disabled!</error>');
                $isError = true;
            }
        }

        return !$isError;
    }
}