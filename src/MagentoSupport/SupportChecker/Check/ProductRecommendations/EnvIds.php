<?php

namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;


use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvIds extends AbstractDbChecker
{

    public function getName()
    {
        return 'ENV Data';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $fields = [
            'services_connector/services_id/project_id' => 'Project ID',
            'services_connector/services_id/project_name' => 'Project Name',
            'services_connector/services_id/environment_id' => 'Data Space ID(env ID)',
            'services_connector/services_id/environment_name' => 'Data Space Name',
            'services_connector/services_id/environment' => 'Data Space Type',
            'services_connector/product_recommendations/alternate_environment_enabled' => 'Alternate ENV enabled?',
            'services_connector/product_recommendations/alternate_environment_id' => 'Alternate ENV ID',
        ];

        $output->writeln('');

        $hasEmpty = false;
        foreach ($fields as $xpath => $title) {
            $value = $this->scopeConfig->getValue($xpath);
            $output->writeln("{$title}: {$value}");
            if (empty($value) && strpos($xpath, 'alternate') === false) {
                $hasEmpty = true;
            }
        }

        if ($this->scopeConfig->isSetFlag('services_connector/product_recommendations/alternate_environment_enabled')) {
            $output->writeln('<error>Alternate env ENABLED!</error>');

        } else {
            $output->writeln('<info>Alternate env DISABLED!</info>');
        }

        if ($hasEmpty) {
            $output->writeln('<error>Some of fields are empty!</error>');
        } else {
            return true;
        }

        return false;
    }
}