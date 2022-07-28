<?php

$envType = PrexConfig::PROD;
$dataSpaceId = '152f00fd-e7c8-4e97-9faf-b24f2ba05366';



$config = include __DIR__ .'/config.php';


$prexConfig  = new PrexConfig($config);
$prexClient = new PrexClient($prexConfig->getConfig($envType));




$cacheFile = __DIR__ . '/'.$dataSpaceId . '.json';

if (is_file($cacheFile)) {
    $data = file_get_contents($cacheFile);
} else {
    $data = $prexClient->call($dataSpaceId, ['from' => 0, 'size' => 10000]);
    file_put_contents($cacheFile, $data);
}


$array = json_decode($data, true);

$urlDomains = [];

$storesWebsites = [];
foreach ($array['hits']['hits'] as $productIndex) {
    $website = $productIndex['_source']['websiteCode'];
    $storesWebsites[$website] = $storesWebsites[$website] ?? [];
    if (!isset($productIndex['_source']['product'])) {
        echo $productIndex['_id'] . PHP_EOL;
        continue;
    }

    foreach ($productIndex['_source']['product'] as $storeCode => $productData) {
        if (!isset($storesWebsites[$website][$storeCode])) {
            $storesWebsites[$website][$storeCode] = 0;
        }
        $storesWebsites[$website][$storeCode] += 1;

        if (!empty($productData['url'])) {
            $urlDomains[getDomain($productData['url'])] = true;
        }
    }
}


echo PHP_EOL;

foreach ($storesWebsites as $website => $stores) {
    echo "Website: {$website}" . PHP_EOL;
    $totalCount = 0;
    echo "Stores: ". PHP_EOL;
    foreach ($stores as $storeCode => $productsCount) {
        $totalCount = max($productsCount, $totalCount);
        echo "-   {$storeCode} ({$productsCount} products)" . PHP_EOL;
    }
    echo "Total products count for website: {$totalCount} products" . PHP_EOL;
    echo PHP_EOL;
}
echo "Domains: " . PHP_EOL;

foreach ($urlDomains as $domain => $a) {
    echo $domain . PHP_EOL;
}
echo PHP_EOL;



class PrexClient
{
    /** @var PrexCreds */
    private $creds;

    public function __construct($creds)
    {
        $this->creds = $creds;
    }

    public function call($dataSpaceId, array $postData = [])
    {
        $url = $this->creds->getUrl().'/_plugin/kibana/api/console/proxy?path=catalog_11_'.$dataSpaceId.'*/_search&method=GET';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        curl_setopt($ch, CURLOPT_USERPWD, $this->creds->getUsername() . ":" . $this->creds->getPassword());

        $headers = array(
            'Content-Type: application/json',
            'kbn-xsrf: kibana',
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close ($ch);

        return $server_output;
    }
}

class PrexConfig {
    const PROD = 'prod';
    const MERCH_TEST = 'merch-test';
    /**
     * @var array
     */
    private $config;

    public function __construct(array $config) {

        foreach ($config as $env => $data) {
            $this->config[$env] = new PrexCreds($data);
        }
    }

    /**
     * @param string $envName
     * @return PrexCreds
     */
    public function getConfig(string $envName)
    {

        return $this->config[$envName];
    }
}

class PrexCreds {
    public const USERNAME = 'username';
    public const PASSWORD = 'password';
    public const URL = 'url';
    /**
     * @var array
     */
    private $data;


    public function __construct(array $data) {

        $this->data = $data;
    }

    public function getUsername()
    {
        return $this->data[self::USERNAME];
    }

    public function getPassword()
    {
        return $this->data[self::PASSWORD];
    }

    public function getUrl()
    {

        return $this->data[self::URL];
    }
}

function getDomain($string) {
    $part = parse_url($string, PHP_URL_HOST);

    return $part;
}