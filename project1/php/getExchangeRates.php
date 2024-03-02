

<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$executionStartTime = microtime(true);

$appId = 'f3f96a3bce6c435abd8c55ffc2d36df2';
$apiEndpoint = "https://openexchangerates.org/api/latest.json";
$params = [
    'app_id' => $appId
];

$url = $apiEndpoint . '?' . http_build_query($params);

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_URL, $url);
$response = curl_exec($curl);
$cURLERROR = curl_errno($curl);
curl_close($curl);

if ($cURLERROR) {
    $output['status']['code'] = $cURLERROR;
    $output['status']['name'] = "Failure - cURL";
    $output['status']['description'] = curl_strerror($cURLERROR);
    $output['data'] = null;
} else {
    $exchangeRates = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $output['status']['code'] = json_last_error();
        $output['status']['name'] = "Failure - JSON";
        $output['status']['description'] = json_last_error_msg();
        $output['data'] = null;
    } else {
        if (isset($exchangeRates['error'])) {
            $output['status']['code'] = $exchangeRates['error']['code'];
            $output['status']['name'] = "Failure - API";
            $output['status']['description'] = $exchangeRates['error']['message'];
            $output['data'] = null;
        } else {
            $output['status']['code'] = 200;
            $output['status']['name'] = "success";
            $output['status']['description'] = "all ok";
            $output['data'] = $exchangeRates;
        }
    }
}

$output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);

echo json_encode($output, JSON_NUMERIC_CHECK);

?>
