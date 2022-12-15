<?php
namespace MagentoSupport\SupportChecker\Check\ProductRecommendations;

use Magento\CatalogSyncAdmin\Model\ServiceClientInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\ProductRecommendationsSyncAdmin\Controller\Adminhtml\Index\Middleware;
use Magento\Store\Model\StoreManagerInterface;
use MagentoSupport\SupportChecker\AbstractDbChecker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ApiKeys extends AbstractDbChecker
{

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    public function __construct(ResourceConnection $resource, ScopeConfigInterface $scopeConfig, \Magento\Framework\Encryption\EncryptorInterface $encryptor)
    {
        parent::__construct($resource, $scopeConfig);
        $this->encryptor = $encryptor;
    }

    public function getName()
    {
        return 'API keys';
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $area = $this->scopeConfig->getValue('magento_saas/environment');

        if ($area !== 'production') {
            $output->writeln('<error>Setting "magento_saas/environment" shoud be set to "production" value. Now it is "' . $area .'"</error>');
        }

        $keys = [
            'production_api_key',
            'production_private_key',
            'sandbox_api_key',
            'sandbox_private_key',
        ];

        foreach ($keys as $key) {
            $fullKey = 'services_connector/services_connector_integration/' . $key;
            $value = $this->scopeConfig->getValue($fullKey);
            $decryptedValue =  $this->encryptor->decrypt($value);

            if (!$value) {
                $output->writeln('<error>'.$key.' not found! Please fill all the API/Private keys including sandbox</error>');
                continue;
            }


            if (strpos($key, 'private') === false) {
                continue;
            }

            if (!$this->checkPrivateKey($value) && !$this->checkPrivateKey($decryptedValue)) {
                $output->writeln('<error>'.$key.' seems incorrect. Each key should have -----BEGIN PRIVATE KEY----- and -----END PRIVATE KEY----- and should contain line breaks</error>');
            }
        }

        return $productionApiKey && $private;
    }


    private function checkPrivateKey($value)
    {
        $result = strpos($value,'BEGIN PRIVATE KEY') === false || strpos($value,'END PRIVATE KEY') === false || strpos($value, PHP_EOL) === false;

        return !$result;
    }
}