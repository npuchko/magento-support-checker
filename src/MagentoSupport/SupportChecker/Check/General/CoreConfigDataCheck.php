<?php
declare(strict_types=1);

namespace MagentoSupport\SupportChecker\Check\General;

use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CoreConfigDataCheck extends AbstractDbChecker
{

    public function getName()
    {
        return 'Configs';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->scopeConfig->isSetFlag('admin/url/use_custom')) {
            $url = $this->scopeConfig->getValue('admin/url/custom');

            if (empty($url)) {
                $output->writeln('<error>Use Custom Admin URL enabled, but no URL specified!</error>');
            }
        }

    }
}