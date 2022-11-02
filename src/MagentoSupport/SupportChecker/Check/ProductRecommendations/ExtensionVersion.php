<?php

namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ExtensionVersion extends AbstractDbChecker
{

    public function getName()
    {
        return 'magento/product-recommendations version:';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $composerLock = ROOT_DIRECTORY_FOR_MAGENTO . '/composer.lock';
        if (!is_file($composerLock)) {
            $output->writeln('I cant read composer.lock file');

            return;
        }

        $composerData = file_get_contents($composerLock);
        $composerData = json_decode($composerData, true);

        $version = null;
        foreach ($composerData['packages'] as $package) {
            if ($package['name'] === 'magento/product-recommendations') {
                $version = $package['version'] ?? 'VERSION NOT SET';
                break;
            }
        }

        if ($version) {
            $output->writeln($version);
        } else {
            $output->writeln('<error>Package not found in composer.lock file!</error>');
        }
    }
}