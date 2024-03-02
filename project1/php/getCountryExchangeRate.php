<?php

/*
function buildQueryString($params) {
    return http_build_query($params);
}

$geocodeApiEndpoint = 'https://api.opencagedata.com/geocode/v1/json';
$geocodeApiKey = '10241517e5f448f7b37d77650f3bcef2';

$queryParams = ['key' => $geocodeApiKey, 'language' => 'en', 'pretty' => 1];
if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
    $queryParams['q'] = $_GET['latitude'] . ',' . $_GET['longitude'];
} elseif (isset($_GET['countryName'])) {
    $queryParams['q'] = $_GET['countryName'];
} else {
    $queryParams['q'] = 'germany';
}

$url = $geocodeApiEndpoint . '?' . buildQueryString($queryParams);

$countryResponse = file_get_contents($url);
$countryData = json_decode($countryResponse, true);

$currency = $countryData['results'][0]['annotations']['currency']['iso_code'];

function getExchangeRates($appId, $currency) {
    $apiEndpoint = "https://openexchangerates.org/api/latest.json";
    $params = [
        'app_id' => $appId,
        'symbols' => $currency
    ];
    $url = $apiEndpoint . '?' . buildQueryString($params);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);
    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

$appId = 'f3f96a3bce6c435abd8c55ffc2d36df2'; 

$exchangeRatesData = getExchangeRates($appId, $currency);

header('Content-Type: application/json');
echo json_encode([
    'currency' => $currency,
    'exchangeRates' => json_decode($exchangeRatesData, true)
]);

*/



ini_set('display_errors', 'On');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$executionStartTime = microtime(true);

function buildQueryString($params) {
    return http_build_query($params);
}

$geocodeApiEndpoint = 'https://api.opencagedata.com/geocode/v1/json';
$geocodeApiKey = '10241517e5f448f7b37d77650f3bcef2';

$queryParams = ['key' => $geocodeApiKey, 'language' => 'en', 'pretty' => 1];
if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
    $queryParams['q'] = $_GET['latitude'] . ',' . $_GET['longitude'];
} elseif (isset($_GET['countryName'])) {
    $queryParams['q'] = $_GET['countryName'];
} else {
    $queryParams['q'] = 'germany';
}

$url = $geocodeApiEndpoint . '?' . buildQueryString($queryParams);

$countryResponse = file_get_contents($url);
if ($countryResponse === FALSE) {
    $output['status']['code'] = "Failure - file_get_contents";
    $output['status']['description'] = "Error fetching geocode data";
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$countryData = json_decode($countryResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $output['status']['code'] = json_last_error();
    $output['status']['name'] = "Failure - JSON";
    $output['status']['description'] = json_last_error_msg();
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$currency = $countryData['results'][0]['annotations']['currency']['iso_code'];

function getExchangeRates($appId, $currency) {
    $apiEndpoint = "https://openexchangerates.org/api/latest.json";
    $params = [
        'app_id' => $appId,
        'symbols' => $currency
    ];
    $url = $apiEndpoint . '?' . buildQueryString($params);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);
    $response = curl_exec($curl);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if ($curlError) {
        return json_encode(['error' => curl_strerror($curlError)]);
    }

    return $response;
}

$appId = 'f3f96a3bce6c435abd8c55ffc2d36df2'; 

$exchangeRatesData = getExchangeRates($appId, $currency);
$exchangeRatesDataDecoded = json_decode($exchangeRatesData, true);

if (isset($exchangeRatesDataDecoded['error'])) {
    $output['status']['code'] = "Failure - cURL";
    $output['status']['description'] = $exchangeRatesDataDecoded['error'];
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$output['status']['code'] = 200;
$output['status']['name'] = "success";
$output['status']['description'] = "all ok";
$output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
$output['data'] = [
    'currency' => $currency,
    'exchangeRates' => $exchangeRatesDataDecoded
];

echo json_encode($output, JSON_NUMERIC_CHECK);

?>

