<?php


if (PHP_SAPI !== 'cli') {
    echo 'bin/magento must be run as a CLI application';
    exit(1);
}

define('ROOT_DIRECTORY_FOR_MAGENTO', str_replace(['phar://', '/magento_support_checker.phar'], '', __DIR__ . '/../'));

stream_wrapper_unregister('phar');


try {
    $oldErrorHandler = set_error_handler(function () {
        return true;
    }, E_WARNING);
    require ROOT_DIRECTORY_FOR_MAGENTO . 'app/bootstrap.php';
    set_error_handler($oldErrorHandler, E_WARNING);
} catch (\Exception $e) {
    echo 'Autoload error: ' . $e->getMessage();
    exit(1);
}
stream_wrapper_restore('phar');

include __DIR__ . '/vendor/autoload.php';
$checksList = [
    'advanced_reporting' => [
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\EnabledChecker::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\FailedSubscriptionChecker::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\ApiEndpointChecker::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\IsMultipleCurrency::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\CronDbCheck::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\TokenChecker::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\FlagChecker::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\EscapedQuotesChecker::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\StoreInconsistencyChecker::class,
        \MagentoSupport\SupportChecker\Check\AdvancedReporting\ReportUrl::class,
    ],
    'product_recommendations' => [
        \MagentoSupport\SupportChecker\Check\ProductRecommendations\ApiKeys::class,
        \MagentoSupport\SupportChecker\Check\ProductRecommendations\ExtensionVersion::class,
        \MagentoSupport\SupportChecker\Check\ProductRecommendations\EnvIds::class,
        \MagentoSupport\SupportChecker\Check\ProductRecommendations\IndexedData::class,
        \MagentoSupport\SupportChecker\Check\ProductRecommendations\CronCheck::class,
        \MagentoSupport\SupportChecker\Check\ProductRecommendations\SyncCheck::class,
        \MagentoSupport\SupportChecker\Check\ProductRecommendations\CategoryPermissionsCheck::class,
    ]
];
try {
    $handler = new \Magento\Framework\App\ErrorHandler();
    set_error_handler([$handler, 'handler']);
    $application = new Magento\Framework\Console\Cli('Magento CLI');
    $om = \Magento\Framework\App\ObjectManager::getInstance();

    /** @var \MagentoSupport\SupportChecker\Checker $checker */
    $checker = $om->get(\MagentoSupport\SupportChecker\Checker::class);

    $input = new \Symfony\Component\Console\Input\ArgvInput();
    $checkGroup = $input->getFirstArgument();
    if (!$checkGroup || $checkGroup === 'all') {
        $checkGroup = null;
    }
    $output = new \Symfony\Component\Console\Output\ConsoleOutput();

    $checker->runChecks($checksList, $checkGroup, $input, $output);
} catch (\Exception $e) {
    while ($e) {
        echo $e->getMessage();
        echo $e->getTraceAsString();
        echo "\n\n";
        $e = $e->getPrevious();
    }
    exit(Magento\Framework\Console\Cli::RETURN_FAILURE);
}