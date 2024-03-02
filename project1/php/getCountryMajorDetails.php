<?php
/*
function buildQueryString($params) {
    return http_build_query($params);
}

$apiEndpoint = 'https://api.opencagedata.com/geocode/v1/json';
$apiKey = '10241517e5f448f7b37d77650f3bcef2';

$queryParams = ['key' => $apiKey, 'language' => 'en', 'pretty' => 1];
if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
    $queryParams['q'] = $_GET['latitude'] . ',' . $_GET['longitude'];
} elseif (isset($_GET['countryName'])) {
    $queryParams['q'] = $_GET['countryName'];
} else {
    $queryParams['q'] = 'germany';
}

$url = $apiEndpoint . '?' . buildQueryString($queryParams);

$response = file_get_contents($url);

header('Content-Type: application/json');

echo $response;

*/

ini_set('display_errors', 'On');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$executionStartTime = microtime(true);

function buildQueryString($params) {
    return http_build_query($params);
}

$apiEndpoint = 'https://api.opencagedata.com/geocode/v1/json';
$apiKey = '10241517e5f448f7b37d77650f3bcef2'; // Replace with your actual API key

$queryParams = ['key' => $apiKey, 'language' => 'en', 'pretty' => 1];
if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
    $queryParams['q'] = $_GET['latitude'] . ',' . $_GET['longitude'];
} elseif (isset($_GET['countryName'])) {
    $queryParams['q'] = $_GET['countryName'];
} else {
    $queryParams['q'] = 'germany';
}

$url = $apiEndpoint . '?' . buildQueryString($queryParams);

$response = file_get_contents($url);

if ($response === FALSE) {
    $output['status']['code'] = "Failure - file_get_contents";
    $output['status']['description'] = "Error fetching geocode data";
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$geocodeData = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $output['status']['code'] = json_last_error();
    $output['status']['name'] = "Failure - JSON";
    $output['status']['description'] = json_last_error_msg();
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

// Check if the API returned an error
if (isset($geocodeData['error'])) {
    $output['status']['code'] = $geocodeData['error']['code'];
    $output['status']['name'] = "Failure - API";
    $output['status']['description'] = $geocodeData['error']['message'];
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$output['status']['code'] = 200;
$output['status']['name'] = "success";
$output['status']['description'] = "all ok";
$output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
$output['data'] = $geocodeData;

echo json_encode($output, JSON_NUMERIC_CHECK);

?>
