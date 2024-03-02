<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$executionStartTime = microtime(true);

$output = [];

if (!isset($_GET['iso_a2'])) {
    $output['status']['code'] = 400;
    $output['status']['name'] = "Failure";
    $output['status']['description'] = "iso_a2 parameter missing";
    $output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$iso_a2 = $_GET['iso_a2'];
$worldBankApiUrl = "https://api.worldbank.org/v2/country/{$iso_a2}?format=json";
$worldBankApiResponse = file_get_contents($worldBankApiUrl);
if ($worldBankApiResponse === FALSE) {
    $output['status']['code'] = 500;
    $output['status']['name'] = "Failure - API Request";
    $output['status']['description'] = "Failed to fetch data from World Bank API";
    $output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$worldBankData = json_decode($worldBankApiResponse, true);
if (!isset($worldBankData[1][0]['latitude']) || !isset($worldBankData[1][0]['longitude'])) {
    $output['status']['code'] = 404;
    $output['status']['name'] = "Failure";
    $output['status']['description'] = "Coordinates not found";
    $output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$latitude = $worldBankData[1][0]['latitude'];
$longitude = $worldBankData[1][0]['longitude'];

$wikipediaApiUrl = "https://en.wikipedia.org/w/api.php?action=query&list=geosearch&gscoord={$latitude}|{$longitude}&gsradius=10000&gslimit=5&format=json";
$wikipediaApiResponse = file_get_contents($wikipediaApiUrl);
if ($wikipediaApiResponse === FALSE) {
    $output['status']['code'] = 500;
    $output['status']['name'] = "Failure - API Request";
    $output['status']['description'] = "Failed to fetch data from Wikipedia API";
    $output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$wikipediaData = json_decode($wikipediaApiResponse, true);
if (!isset($wikipediaData['query']['geosearch'])) {
    $output['status']['code'] = 404;
    $output['status']['name'] = "Failure";
    $output['status']['description'] = "Wikipedia API response missing geosearch data";
    $output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
    $output['data'] = null;
    echo json_encode($output);
    exit;
}

$pages = $wikipediaData['query']['geosearch'];
$result = [];
foreach ($pages as $page) {
    $pageId = $page['pageid'];
    // Fetch page details including a brief description
    $pageDetailsUrl = "https://en.wikipedia.org/w/api.php?action=query&prop=extracts&exintro&explaintext&format=json&pageids={$pageId}";
    $pageDetailsResponse = file_get_contents($pageDetailsUrl);
    $pageDetailsData = json_decode($pageDetailsResponse, true);
    
    if ($pageDetailsResponse !== FALSE && isset($pageDetailsData['query']['pages'][$pageId]['extract'])) {
        $description = $pageDetailsData['query']['pages'][$pageId]['extract'];
    } else {
        $description = "Description not available"; // Fallback description
    }
    
    $name = $page['title'];
    $url = "https://en.wikipedia.org/wiki/{$name}";
    $result[] = [
        'name' => $name,
        'description' => $description,
        'url' => $url,
    ];
}

$output['status']['code'] = 200;
$output['status']['name'] = "success";
$output['status']['description'] = "all ok";
$output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
$output['data'] = $result;

echo json_encode($output, JSON_NUMERIC_CHECK);

?>