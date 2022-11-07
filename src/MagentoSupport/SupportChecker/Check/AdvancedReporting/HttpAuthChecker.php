<?php

namespace MagentoSupport\SupportChecker\Check\AdvancedReporting;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;


class HttpAuthChecker extends AbstractDbChecker
{
    private array $whitelistIps = [
        '34.206.147.74',
        '35.172.154.189'
    ];

    public function getName()
    {
        return 'HTTP Authentication check';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $envUser = getenv('USER');

        $routerFile = '/etc/platform/' . $envUser . '/router.json';
        if (!file_exists($routerFile)) {
            $output->writeln('<error>No router.json file</error>');
            return false;
        }

        $routerConfig = json_decode(file_get_contents($routerFile), true);
        if (isset($routerConfig['http_access']) && !empty($routerConfig['http_access']['basic_auth'])) {
            $output->writeln('HTTP auth is enabled!');

            if (!empty($routerConfig['http_access']['addresses'])) {
                $wlMatched = 0;
                foreach ($routerConfig['http_access']['addresses'] as $ip) {
                    if (preg_match('/^[\d+\.]+/', $ip['address'], $matches) && $matches[0]) {
                        if ($ip['permission'] == "allow" && in_array($matches[0], $this->whitelistIps)) {
                            $output->writeln($ip['address'] . ' is whitelisted');
                            $wlMatched++;
                        }
                    }
                }

                if ($wlMatched == count($this->whitelistIps)) {
                    $output->writeln('All Advanced Reporting IP addresses are in whitelist');
                    return true;
                }
            }

            $output->writeln('<error>Advanced Reporting IP addresses are not in whitelist </error>');
            return false;
        }

        $output->writeln('HTTP auth is disabled!');

        return true;

    }
}