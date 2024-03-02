<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$executionStartTime = microtime(true);

$output = [
    'status' => [
        'code' => null,
        'name' => null,
        'description' => null,
        'seconds' => null
    ],
    'data' => null
];

function httpGet($url) {
    $options = [
        'http' => [
            'header' => "User-Agent: PHP",
            'method' => 'GET',
        ],
    ];
    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

if (isset($_GET['iso_a2'])) {
    $iso_a2 = strtoupper($_GET['iso_a2']);
    $username = 'balajikrishnamurthy';
    
    $worldBankApiUrl = "https://api.worldbank.org/v2/country/{$iso_a2}?format=json";
    $worldBankApiResponse = httpGet($worldBankApiUrl);
    if ($worldBankApiResponse === FALSE) {
        $output['status']['code'] = 'Failure - HTTP GET';
        $output['status']['name'] = 'Failure';
        $output['status']['description'] = 'Failed to fetch data from World Bank API';
        $output['data'] = null;
    } else {
        $worldBankData = json_decode($worldBankApiResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output['status']['code'] = json_last_error();
            $output['status']['name'] = "Failure - JSON";
            $output['status']['description'] = json_last_error_msg();
            $output['data'] = null;
        } elseif (isset($worldBankData[1][0]['latitude']) && isset($worldBankData[1][0]['longitude'])) {
            $latitude = $worldBankData[1][0]['latitude'];
            $longitude = $worldBankData[1][0]['longitude'];

            $geoNamesApiUrl = "http://api.geonames.org/findNearbyJSON?lat={$latitude}&lng={$longitude}&username={$username}&radius=200";
            $geoNamesApiResponse = httpGet($geoNamesApiUrl);
            $geoNamesData = json_decode($geoNamesApiResponse, true);
            
            if (isset($geoNamesData['geonames'])) {
                $output['data'] = $geoNamesData['geonames'];
            } else {
                $output['status']['code'] = 'Failure - GeoNames API';
                $output['status']['name'] = 'Failure';
                $output['status']['description'] = 'GeoNames API response missing data';
            }
        } else {
            $output['status']['code'] = 'Failure - World Bank API';
            $output['status']['name'] = 'Failure';
            $output['status']['description'] = 'Coordinates not found in World Bank API response';
        }
    }
} else {
    $output['status']['code'] = 'Failure - Input';
    $output['status']['name'] = 'Failure';
    $output['status']['description'] = 'iso_a2 parameter missing';
}

$output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
echo json_encode($output, JSON_NUMERIC_CHECK);

?>
