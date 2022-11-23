<?php
declare(strict_types=1);

namespace MagentoSupport\SupportChecker\Check\AdvancedReporting;

use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OpExcludeChecker extends AbstractDbChecker
{

    public function getName()
    {
        return 'Op exclude checker';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = ROOT_DIRECTORY_FOR_MAGENTO . 'op-exclude.txt';

        if (!is_file($filePath)) {
            $output->writeln('<error>op-exclude.txt not found! Please create one!</error>');
            return false;
        }

        $data = explode(PHP_EOL, file_get_contents($filePath));

        $requiredLines = [
            '/app/*/app/etc/config.php',
            '/app/*/app/etc/env.php',
            '/app/etc/config.php',
            '/app/etc/env.php',
        ];

        $requiredLines = array_flip($requiredLines);

        foreach ($data as $line) {
            $line = trim($line);

            if (isset($requiredLines[$line])) {
                unset($requiredLines[$line]);
            }
        }

        if ($requiredLines) {
            $output->writeln('<error>op-exclude.txt is incorrect! Please add following rows:</error>');
            $output->writeln(implode(PHP_EOL, array_keys($requiredLines)));
        }
    }
}