<?php

namespace {

    if (PHP_SAPI !== 'cli') {
        echo 'bin/magento must be run as a CLI application';
        exit(1);
    }
    define('ROOT_DIRECTORY_FOR_MAGENTO', __DIR__ . '/../');

    $checksList = [
        'advanced_reporting' => [
            \MagentoSupport\SupportChecker\EnabledChecker::class,
            \MagentoSupport\SupportChecker\IsMultipleCurrency::class,
            \MagentoSupport\SupportChecker\CronDbCheck::class,
            \MagentoSupport\SupportChecker\TokenChecker::class,
            \MagentoSupport\SupportChecker\FlagChecker::class,
            \MagentoSupport\SupportChecker\EscapedQuotesChecker::class,
            \MagentoSupport\SupportChecker\StoreInconsistencyChecker::class,
            \MagentoSupport\SupportChecker\ReportUrl::class,
        ],
        'product_recommendations' => [
            \MagentoSupport\SupportChecker\ProductRecommendations\ApiKeys::class,
            \MagentoSupport\SupportChecker\ProductRecommendations\ExtensionVersion::class,
            \MagentoSupport\SupportChecker\ProductRecommendations\EnvIds::class,
            \MagentoSupport\SupportChecker\ProductRecommendations\IndexedData::class,
            \MagentoSupport\SupportChecker\ProductRecommendations\CronCheck::class,
            \MagentoSupport\SupportChecker\ProductRecommendations\SyncCheck::class,
        ]
    ];

    try {
        require ROOT_DIRECTORY_FOR_MAGENTO . 'app/bootstrap.php';
    } catch (\Exception $e) {
        echo 'Autoload error: ' . $e->getMessage();
        exit(1);
    }
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
}


namespace MagentoSupport\SupportChecker {

    use Magento\Analytics\Model\Config\Backend\CollectionTime;
    use Magento\Analytics\Model\ReportUrlProvider;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Magento\Framework\App\ResourceConnection;

//    interface CheckInterface {
//        public function getName();
//        public function execute(InputInterface $input, OutputInterface $output);
//    }

    abstract class AbstractDbChecker //implements CheckInterface
    {
        /**
         * @var resource Connection
         */
        protected $connection;

        /**
         * @var \Magento\Framework\App\Config\ScopeConfigInterface
         */
        protected $scopeConfig;
        protected $resource;

        /**
         * DbDataSeeker constructor.
         * @param ResourceConnection $resource
         */
        public function __construct(ResourceConnection $resource, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
        {
            $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
            $this->scopeConfig = $scopeConfig;
            $this->resource = $resource;
        }

        abstract public function getName();

        abstract public function execute(InputInterface $input, OutputInterface $output);

        /**
         * Get data from core config table
         * @param $columns
         * @param $pathValue
         * @return false|string
         */
        protected function selectFromCoreConfig($columns, $pathValue)
        {
            $configTable = $this->connection->getTableName('core_config_data');
            $select = $this->connection->select()->from($configTable, $columns)->where('path LIKE :path');
            $bind = [':path' => $pathValue];
            return $this->connection->fetchAll($select, $bind);
        }
    }

    class EnabledChecker extends AbstractDbChecker
    {
        public function getName()
        {
            return 'Is Enabled';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $isError = false;
            $dbRows = $this->selectFromCoreConfig(
                ['scope', 'scope_id', 'value'],
                'analytics/subscription/enabled'
            );
            foreach ($dbRows as $dbRow) {
                if (!$dbRow['value']) {
                    // checking if app/etc/*.php files overrides this.
                    $isSetFlag = $this->scopeConfig->isSetFlag('analytics/subscription/enabled', $dbRow['scope'], $dbRow['scope_id']);

                    if (!$isSetFlag) {
                        $isError = true;
                        $output->writeln("<error>Module disabled in DB for scope: {$dbRow['scope']} = {$dbRow['scope_id']}</error>");
                    }
                }
            }

            if ($isError) {
                return false;
            }
            $isModuleEnabledByConfig = $this->scopeConfig->isSetFlag('analytics/subscription/enabled');

            if (!$isModuleEnabledByConfig) {
                $output->writeln("<error>Module disabled in app/etc/config.php or app/etc/env.php</error>");
                return false;
            }

            return true;
        }
    }

    class IsMultipleCurrency extends AbstractDbChecker
    {

        public function getName()
        {
            return 'Multiple Currency';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $result = $this->isMultiCurrency();

            if (count($result) > 1) {
                $output->writeln('<error>There is multiple currencies was in db found:' . json_encode($result) . '</error>');
                return false;
            }

            return true;
        }

        /**
         * check multiCurrency
         * @return string
         */
        private function isMultiCurrency()
        {
            $select = $this->connection->select()->from(
                $this->connection->getTableName('sales_order'),
                ['base_currency_code', 'COUNT(*)'])
                ->distinct(true)
                ->group('base_currency_code');
            $result = $this->connection->fetchAll($select);

            return $result;
            if (count($result) > 1) {
                return 'There is multiple currencies was found:' . json_encode($result);
            } else {
                return 'No multiple currencies was found';
            }
        }
    }

    class CronDbCheck extends AbstractDbChecker
    {

        public function getName()
        {
            return 'Cron in DB settings';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {

            $row = $this->selectFromCoreConfig(
                ['scope', 'scope_id', 'value'],
                '%analytics_collect_data/schedule/cron_expr'
            );

            if (!$row) {
                $output->writeln('<error>Cron executed time not set!</error>');
            } else {
                $output->writeln(json_encode($row));
            }

            $cronDefaultConfig = $this->scopeConfig->getValue('crontab/analytics/jobs/analytics_collect_data/schedule/cron_expr');
            $cronAnalyticsConfig = $this->scopeConfig->getValue('crontab/default/jobs/analytics_collect_data/schedule/cron_expr');

            if ($cronAnalyticsConfig && $cronDefaultConfig) {
                $output->writeln('<error>Cron setted up for 2 cron groups: default and analytics. Remove old one!</error>');
            }

            $cronJob = $this->findAnalyticsCronJobInDb();
            if (count($cronJob)) {
                $hasErrors = false;
                $errorMessages = [];
                foreach ($cronJob as $job) {
                    if ($job['status'] === 'error') {
                        $hasErrors = true;
                        $errorMessages[$job['messages']] = 1;
                    }
                }

                if ($hasErrors) {
                    $output->writeln('<error>Cron jobs has errors</error>');
                    $errorMessages = array_keys($errorMessages);
                    $errorMessages = array_slice($errorMessages, 0, 10);
                    foreach ($errorMessages as $errorMessage) {
                        $output->writeln('-    <error>' . $errorMessage. '</error>');
                    }

                    $output->writeln('');
                }

                $output->writeln('Cron jobs in DB: ' . json_encode($cronJob));
            } else {
                $output->writeln('Cron jobs in DB not found');
            }

            return false;
        }

        /**
         * Find all analytics_collect_data rows
         * @return false|string
         */
        private function findAnalyticsCronJobInDb()
        {
            $select = $this->connection->select()->from(
                $this->connection->getTableName('cron_schedule'),
                ['job_code', 'messages', 'status'])->where('job_code LIKE :job_code');
            $bind = [':job_code' => 'analytics_collect_data'];
            return $this->connection->fetchAll($select, $bind);


        }
    }

    class TokenChecker extends AbstractDbChecker
    {
        /**
         * @var Magento\Analytics\Model\AnalyticsToken
         */
        private $analyticsToken;

        public function __construct(
            ResourceConnection $resource,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            \Magento\Analytics\Model\AnalyticsToken $analyticsToken
        ) {
            parent::__construct($resource, $scopeConfig);
            $this->analyticsToken = $analyticsToken;
        }

        public function getName()
        {
            return 'Token in settings';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $configToken = $this->scopeConfig->getValue('analytics/general/token');

            $dbToken = $this->selectFromCoreConfig(
                ['scope', 'scope_id', 'value'],
                'analytics/general/token'
            );

            if (!$configToken && (!$dbToken || empty($dbToken[0]['value']))) {
                $output->writeln('<error>No Token found</error>');

                $this->checkCounter($output);
                return false;
            }

            $dbToken = $dbToken[0]['value'] ?? null;
            if ($configToken == $dbToken) {
                return true;
            }

            $output->writeln('<error>Token setted in app/etc/*.php file</error>');

            return false;
        }

        private function checkCounter(OutputInterface $output)
        {
            $configTable = $this->connection->getTableName('flag');
            $select = $this->connection->select()->from($configTable)->where('flag_code = :flag_code');
            $bind = [':flag_code' => 'analytics_link_subscription_update_reverse_counter'];
            $rows =  $this->connection->fetchAll($select, $bind);

            if ($rows) {
                $output->writeln('<error>Counter flag found:</error>' . json_encode($rows));
            }
        }
    }

    class FlagChecker extends AbstractDbChecker
    {
        public function getName()
        {
            return 'Last generated report in flag';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $flag = $this->checkFlagTable();

            if (!count($flag)) {
                $output->writeln('<error>Flag not found, report wasnt generated</error>');
                $counterFlag = $this->getCounterFlag();
                if ($counterFlag) {
                    $output->writeln('<error>Counter flag found: ' . json_encode($counterFlag) . '</error>');
                }

                return false;
            }

            $flag = $flag[0];
            $lastUpdate = new \DateTime($flag['last_update']);
            $currentDate = new \DateTime();
            $diffDays = $currentDate->diff($lastUpdate)->format('%a');

            $isError = false;
            if ($diffDays > 2) {
                $output->write('<error>' . $diffDays . ' days ago. (' . $flag['last_update'] . ')</error>');
                $isError = true;
            } else {
                $output->write($diffDays . ' days ago. (' . $flag['last_update'] . ') ');
            }

            $flagData = json_decode($flag['flag_data'], true);
            $filePath = ROOT_DIRECTORY_FOR_MAGENTO . 'pub/media/' . $flagData['path'];


            if (!is_file($filePath)) {
                $isError = true;
                $output->writeln('');
                $output->writeln('<error>File not found!</error> ' . $filePath);
            }

            $url = $this->scopeConfig->getValue('web/secure/base_url') . 'media/' . $flagData['path'];
            $output->writeln('File URL: ' . $url);


            return !$isError;
        }

        private function checkFlagTable()
        {
            $select = $this->connection->select()->from(
                $this->connection->getTableName('flag'),
                ['flag_code', 'flag_data', 'last_update'])
                ->where('flag_code LIKE :flag_code');
            $bind = [':flag_code' => 'analytics_file_info'];
            return $this->connection->fetchAll($select, $bind);
        }

        private function getCounterFlag()
        {
            $select = $this->connection->select()->from(
                $this->connection->getTableName('flag'),
                ['flag_code', 'flag_data', 'last_update'])
                ->where('flag_code LIKE :flag_code');
            $bind = [':flag_code' => 'analytics_link_subscription_update_reverse_counter'];
            return $this->connection->fetchAll($select, $bind);
        }
    }

    class EscapedQuotesChecker extends AbstractDbChecker
    {

        public function getName()
        {
            return 'Escaped quotes in db';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $escapedQuotes = $this->checkEscapedQuotes();
            $escapedQuotesCount = count($escapedQuotes);

            if ($escapedQuotesCount > 0) {
                $output->writeln('<error>Found '.$escapedQuotesCount.' rows with escaped quotes</error>');
                $out = array_slice($escapedQuotes, 0, 10);
                foreach ($out as $product) {
                    $output->writeln($product['sku'] . ' - ' . $product['name']);
                }

                $output->writeln('');
                return false;
            }

            return true;
        }

        /**
         * Find escaped quotes
         * @return false|string
         */
        private function checkEscapedQuotes()
        {
            $select = $this->connection->select()->from(
                $this->connection->getTableName('sales_order_item'),
                ['name', 'COUNT(*)', 'sku'])
                ->where('name like \'%\\\\\\\\"%\' or name like \'%\"%\' ')
                ->group(['name', 'sku']);
            $result = $this->connection->fetchAll($select);
            return ($result);

        }
    }

    class StoreInconsistencyChecker extends AbstractDbChecker
    {

        public function getName()
        {
            return 'Check unexisting stores';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $result = $this->connection->fetchCol(
                "SELECT store_id FROM sales_order WHERE store_id not in (select store_id from store) GROUP BY store_id"
            );

            if (count($result) > 0) {
                $output->writeln('<error>sales_order contains unexisting store ids ' . json_encode() . '</error>');

                return false;
            }

            return true;

        }
    }

    class ReportUrl extends AbstractDbChecker
    {

        /**
         * @var ReportUrlProvider
         */
        private $reportUrlProvider;

        public function __construct(
            ResourceConnection                                 $resource,
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            ReportUrlProvider                                  $reportUrlProvider
        )
        {
            parent::__construct($resource, $scopeConfig);

            $this->reportUrlProvider = $reportUrlProvider;
        }

        public function getName()
        {
            return 'Generated report URL';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $url = $this->reportUrlProvider->getUrl();

            $output->writeln($url);

            return false;
        }
    }

    class Checker
    {
        /**
         * @var \Magento\Framework\ObjectManagerInterface
         */
        private $objectManager;

        public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
        {
            $this->objectManager = $objectManager;
        }

        public function runChecks(array $checksGroups, ?string $checkGroup = null, InputInterface $input, OutputInterface $output)
        {
            $output->writeln('');
            $result = [];
            foreach ($checksGroups as $group => $checkClasses) {

                if ($checkGroup !== null && $checkGroup != $group) {
                    continue;
                }
                $result[$group] = [];

                $total = count($checkClasses);
                $output->writeln("Check group: {$group} with total {$total} rules'");
                foreach ($checkClasses as $i => $checkClass) {
                    /** @var AbstractDbChecker $check */
                    $check = $this->objectManager->get($checkClass);

                    $i++;
                    $output->write("[{$i}/$total] " . $check->getName() . ': ');
                    $result = $check->execute($input, $output);
                    if ($result === true) {
                        $output->writeln('<info>OK</info>');
                    }
                }
                $output->writeln('');
            }

            return $result;
        }
    }
}


namespace MagentoSupport\SupportChecker\ProductRecommendations {

    use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
    use Magento\Framework\App\ResourceConnection;
    use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
    use Magento\Store\Model\StoreManagerInterface;
    use MagentoSupport\SupportChecker\AbstractDbChecker;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class ApiKeys extends AbstractDbChecker
    {

        public function getName()
        {
            return 'API keys';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $productionApiKey = $this->scopeConfig->getValue('services_connector/services_connector_integration/production_api_key');
            $private = $this->scopeConfig->getValue('services_connector/services_connector_integration/production_private_key');


            if (!$productionApiKey) {
                $output->writeln('<error>Production API key not found!</error>');
            }

            if (!$private) {
                $output->writeln('<error>Production Private key not found!</error>');
            }

            return $productionApiKey && $private;
        }
    }

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
                }
            }

            if ($version) {
                $output->writeln($version);
            } else {
                $output->writeln('<error>Package not found in composer.lock file!</error>');
            }
        }
    }

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

            if ($this->scopeConfig->isSetFlag('services_connector/product_recommendations/alternate_environment_enabled'))
            {
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

    class IndexedData extends AbstractDbChecker
    {

        public function __construct(ResourceConnection $resource, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
        {
            parent::__construct($resource, $scopeConfig);
        }

        public function getName()
        {
            return 'Indexed products';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $dataExporterCount = $this->count('catalog_data_exporter_products');
            $productsTotalCount = $this->count('catalog_product_entity');
            $storeCount = $this->count('store');

            $output->writeln("{$dataExporterCount} rows in {$storeCount} stores (catalog_product_entity has {$productsTotalCount})");

            return false;
        }

        private function count($table)
        {
            $table = $this->resource->getTableName($table);

            $sql = "SELECT COUNT(*) FROM {$table}";
            return $this->connection->fetchOne($sql);
        }
    }

    class CronCheck extends AbstractDbChecker
    {

        public function getName()
        {
            return 'Cron';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $table = $this->resource->getTableName('cron_schedule');

            $jobCodes = ['submit_product_feed', 'submit_product_metadata_feed'];
            $statuses = ['error', 'missed', 'pending', 'success'];

            $sql = "SELECT count(*) as cnt, job_code, status, MAX(executed_at) as last FROM {$table}
            WHERE job_code IN ('submit_product_feed', 'submit_product_metadata_feed')
            GROUP BY job_code, status;";

            $rows = $this->connection->fetchAll($sql);

            $data = [];
            foreach ($rows as $row) {
                $data[$row['job_code'] . '_' . $row['status']] = ['count' => $row['cnt'], 'last' => $row['last']];
            }
            $output->writeln('');
            $hasErrors = false;
            $notHaveSuccess = false;
            foreach ($jobCodes as $jobCode) {
                $output->writeln('Job code ' . $jobCode . ' ');
                foreach ($statuses as $status) {
                    $count = $data[$jobCode . '_' . $status]['count'] ?? '0';
                    if ($status === 'error' && $count > 0) {
                        $hasErrors = true;
                    }

                    if ($status === 'success' && $count == 0){
                        $notHaveSuccess = true;
                    }
                    $last = $data[$jobCode . '_' . $status]['last'] ?? ' N/A';
                    $output->writeln("{$status} - {$count} rows, last exec time {$last}");
                }
                $output->writeln('');
            }

            if ($hasErrors) {
                $output->writeln('<error>Cron jobs has errors</error>');
                return false;
            }
            if ($notHaveSuccess) {
                $output->writeln('<error>No one success cron job</error>');
                return false;
            }

            return true;
        }
    }

    class SyncCheck extends AbstractDbChecker
    {
        private $storeManager;
        private $serviceClient;

        public function __construct(ResourceConnection $resource,
                                    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                    StoreManagerInterface $storeManager,
                                    ServiceClientInterface $serviceClient)
        {
            parent::__construct($resource, $scopeConfig);
            $this->storeManager = $storeManager;
            $this->serviceClient = $serviceClient;
        }

        public function getName()
        {
            return 'Sync Status';
        }

        public function execute(InputInterface $input, OutputInterface $output)
        {
            $envId = $this->scopeConfig->getValue('services_connector/services_id/environment_id');
            if (!$envId) {
                $output->writeln('<info>No env ID!</info>');
                return false;
            }


            $stores = $this->storeManager->getStores(false);

            foreach ($stores as $store) {
                $website = $this->storeManager->getWebsite($store->getWebsiteId())->getCode();
                $storeCode =$store->getCode();
                $output->writeln('Store ' . $storeCode);


                $url = "catalogsyncstatus/environments/{$envId}/websites/{$website}/storeviews/{$storeCode}/aggregated";

                $baseRoute = $this->scopeConfig->getValue('product_recommendations_sync_admin/admin_api_path');
                $apiUrl = $this->serviceClient->getUrl($baseRoute, 'v1', $url);


                $response = $this->serviceClient->request('GET', $apiUrl, '');

                if (!isset($response['storeViewSyncStatusResponse'])) {
                    $output->writeln('<error>ERROR:</error> ' .json_encode($response));
                    continue;
                }
                $output->writeln(
                    "Total count: {$response['documentCountResponse']['documentCount']}"
                );
                $output->writeln(
                    "Last Sync - Num synced: {$response['storeViewSyncStatusResponse']['numSynced']}, "
                    . "Last Time: {$response['storeViewSyncStatusResponse']['lastSyncTs']}, "
                    . "Status: {$response['storeViewSyncStatusResponse']['status']} "
                );

                $url = "/{$envId}/{$storeCode}/units";
                $apiUrl = $this->serviceClient->getUrl($baseRoute, 'v1', $url);


                $response = $this->serviceClient->request('GET', $apiUrl, '');

                $output->writeln('Units: ');
                $output->writeln(json_encode($response));


                foreach ($response['results'] as $unit) {
                    $found = false;
                    foreach ($unit['filterRules'] as $filterRule) {
                        foreach ($filterRule['conditions'] as $condition) {
                            if ($condition['field'] === 'category') {
                                //$found = true;

                                foreach ($condition['operator']['customOperator']['value'] ?? [] as $val) {
                                    if (is_null($val)) {
                                        $found = true;
                                    }
                                }
                                break 2;
                            }
                        }
                    }

                    if ($found) {
                        $output->writeln('<error>Invalid category filter in: ' ."Unit {$unit['unitId']} {$unit['unitName']}". '</error>');
                    }
                }


                $output->writeln('');
            }


            return null;
        }
    }
}
