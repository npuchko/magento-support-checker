<?php
declare(strict_types=1);

namespace MagentoSupport\SupportChecker\Check\AdvancedReporting;

use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseUrlSettingsChecker extends AbstractDbChecker
{

    public function getName()
    {
        return 'Base URL checker';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $result = true;
        $secureUseInAdmin = $this->scopeConfig->getValue('web/secure/use_in_adminhtml');

        if (!$secureUseInAdmin) {
            $result = false;
            $output->writeln('<error>Use secure URLs in admin must be YES</error>');
        }
        $secureUseInFrontend = $this->scopeConfig->getValue('web/secure/use_in_frontend');
        if (!$secureUseInFrontend) {
            $result = false;
            $output->writeln('<error>Use secure URLs on admin Storefront be YES</error>');
        }

        $secureUrl = $this->scopeConfig->getValue('web/secure/base_url');
        $output->writeln('Default secure URL is: ' . $secureUrl);

        if (strpos($secureUrl, 'https') !== 0) {
            $result = false;
            $output->writeln('<error>Base Secure URL should starts with https</error>');
        }

        if ($result){
            $output->writeln('Secure URLs enabled for admin and storefront');
        }

        return $result;
    }
}