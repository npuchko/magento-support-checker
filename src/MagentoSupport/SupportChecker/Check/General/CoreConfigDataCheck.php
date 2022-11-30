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

        if ($this->scopeConfig->isSetFlag('web/session/use_remote_addr')) {
            $output->writeln('<error>Stores -> Configuration -> General -> Web -> Session Validation Settings -> Validate REMOTE_ADDR = "Yes"</error>');
            $output->writeln('<comment>web/session/use_remote_addr is not recommended when using load balancer/fastly/cloudflare</comment>');
        }

        return true;
    }
}