<?php

$envType = PrexConfig::PROD;
$dataSpaceId = 'da57c61c-a83e-43cf-8297-de7b76a6ce42';
$lessThan10k = true;



$config = include __DIR__ .'/config.php';


$prexConfig  = new PrexConfig($config);
$prexClient = new PrexClient($prexConfig->getConfig($envType));



$cacheDir = __DIR__ . '/cache/' . $dataSpaceId . '/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir);
}
$cacheFiles = glob($cacheDir . '*.json');

if (!$cacheFiles) {
//    $start = 0;
//    $size = 1000;



    if ($lessThan10k) {
        $request = 'catalog_11_'.$dataSpaceId.'*/_search';
        list($header, $data) = $prexClient->call($request, ['from' => 0, 'size' => 10000]);
        file_put_contents($cacheDir . '/1.json', $data);
    } else {
        $size = 10000;
        $request = 'catalog_11_'.$dataSpaceId.'*/_search?scroll=1m';
        $params = [
            'size' => $size,
            'sort' => ['_doc']
        ];
        $i = 0;
        $scrollId = null;
        do {
            $isRun = false;
            list($header, $data) = $prexClient->call($request, $params);
            if (empty($data)) {
                echo "EMPTY DATA!! Headers are: " . PHP_EOL;
                echo $header;
                echo PHP_EOL;
            }
            $array = json_decode($data, true);
            if (!empty($array['_scroll_id'])) {
                $scrollId = $array['_scroll_id'];
            }

            file_put_contents($cacheDir . $i . '.json', $data);

            $request = '/_search/scroll';
            $params = ['scroll_id' => $scrollId, "scroll" => '1m'];
            $i++;
            sleep(10);
        } while(count($array['hits']['hits']) > 0);
    }




//    do {
//
//        $request = urlencode('catalog_11_'.$dataSpaceId.'*/_search');
//        $data = $prexClient->call($dataSpaceId, ['from' => $start, 'size' => $size]);
//        $array = json_decode($data, true);
//
//        if (!isset($array['hits']['hits'])) {
//            var_dump('Array is empty', $array);
//            break;
//        }
//
//        file_put_contents($cacheDir . $i . '.json', $data);
//
//        $start += $size;
//        $i++;
//    } while($array['hits']['total']['value'] > $start);
}



$urlDomains = [];
$storesWebsites = [];

$isCatalogPermissionsEnabled = false;
$catalogPermissionsCount = 0;
$noCatalogPermissionsCount = 0;

$cacheFiles = glob($cacheDir . '*.json');

$skus = [];
foreach ($cacheFiles as $cacheFile) {
    $data = file_get_contents($cacheFile);
    $array = json_decode($data, true);

    if (!isset($array['hits']['hits'])) {
        var_dump('Array is empty', $array);
        break;
    }


    foreach ($array['hits']['hits'] as $productIndex) {
        $website = $productIndex['_source']['websiteCode'];
        $storesWebsites[$website] = $storesWebsites[$website] ?? [];

        if (isset($skus[$website][$productIndex['_id']])) {
            echo "duplicate!!! " . $productIndex['_id'] . PHP_EOL;

        }

        $skus[$website][$productIndex['_id']] = true;
        if (!isset($productIndex['_source']['product'])) {
            echo $productIndex['_id'] . PHP_EOL;
            continue;
        }

        foreach ($productIndex['_source']['product'] as $storeCode => $productData) {
            if (!isset($storesWebsites[$website][$storeCode])) {
                $storesWebsites[$website][$storeCode] = ['total' => 0, 'displayable' => 0];
            }
            $storesWebsites[$website][$storeCode]['total'] += 1;

            if ($productData['displayable'] ?? false) {
                $storesWebsites[$website][$storeCode]['displayable'] += 1;
            }

            if (!empty($productData['url'])) {
                $urlDomains[getDomain($productData['url'])] = true;
            }
        }

        $catPermEnabled = false;
        foreach ($productIndex['_source']['customerGroups'] ?? [] as $customerGroup) {
            if (array_key_exists('displayable', $customerGroup)) {
                $catPermEnabled = true;
                break;
            }
        }

        if ($catPermEnabled) {
            $catalogPermissionsCount++;
            $isCatalogPermissionsEnabled = true;
        } else {
            $noCatalogPermissionsCount++;
        }
    }
}


echo PHP_EOL;

foreach ($storesWebsites as $website => $stores) {
    echo "Website: {$website}" . PHP_EOL;
    $totalCount = 0;
    echo "Stores: ". PHP_EOL;
    foreach ($stores as $storeCode => $countData) {
        $productsCount = $countData['total'];
        $displayableCount = $countData['displayable'];
        $totalCount = max($productsCount, $totalCount);
        echo "-   {$storeCode} ({$productsCount} products. Displayable - {$displayableCount} products)" . PHP_EOL;
    }
    echo "Total products count for website: {$totalCount} products" . PHP_EOL;
    echo PHP_EOL;
}
echo "Domains: " . PHP_EOL;

foreach ($urlDomains as $domain => $a) {
    echo $domain . PHP_EOL;
}
echo PHP_EOL;

echo "Catalog Permissions: ";
if ($isCatalogPermissionsEnabled) {
    echo "enabled";
    echo PHP_EOL;

    echo "Count products with enabled Catalog Permissions: " . $catalogPermissionsCount;
    echo PHP_EOL;
    echo "Count products without catalog perms: " . $noCatalogPermissionsCount;

} else {
    echo "disabled";
}

echo PHP_EOL;
echo PHP_EOL;



class PrexClient
{
    /** @var PrexCreds */
    private $creds;

    public function __construct($creds)
    {
        $this->creds = $creds;
    }

    public function call($request, array $postData = [])
    {
        $request = urlencode($request);

        $url = $this->creds->getUrl().'/_plugin/kibana/api/console/proxy?path='.$request.'&method=GET';

        $headers = array(
            'Content-Type: application/json',
            'kbn-xsrf: kibana',
            "Authorization: Basic ".base64_encode($this->creds->getUsername() . ":" . $this->creds->getPassword())
        );




        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => json_encode($postData),
                'timeout' => 60
            )
        );

        $context  = stream_context_create($opts);

        $result = file_get_contents($url, false, $context);

        return ['', $result];



        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        curl_setopt($ch, CURLOPT_USERPWD, $this->creds->getUsername() . ":" . $this->creds->getPassword());


        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HEADER, 1);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        //A given cURL operation should only take
        //30 seconds max.
        curl_setopt($ch, CURLOPT_TIMEOUT, 240);

        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 2);

        if(!defined('CURLOPT_IGNORE_CONTENT_LENGTH')){
            define('CURLOPT_IGNORE_CONTENT_LENGTH',136);
        }
        if(!curl_setopt($ch,CURLOPT_IGNORE_CONTENT_LENGTH,1)){
            throw new \RuntimeException('failed to set CURLOPT_IGNORE_CONTENT_LENGTH! - '.curl_errno($ch).': '.curl_error($ch));
        }

        //curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);


        //ob_start();
        $server_output = curl_exec($ch);
        //$data = ob_get_clean();

        $error = curl_errno($ch);

        if ($error) {
            throw new Exception(curl_error($ch));
        }


        // Then, after your curl_exec call:
        //$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close ($ch);

        //$header = substr($server_output, 0, $header_size);
        //$body = substr($server_output, $header_size);

        return ['', $server_output];
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