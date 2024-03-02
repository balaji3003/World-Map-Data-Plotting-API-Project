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
        'seconds' => null,
    ],
    'data' => null
];

function getWorldPopulationByCountry($year) {
    $url = "http://api.worldbank.org/v2/country/all/indicator/SP.POP.TOTL?date={$year}&format=json&per_page=500";
    
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($curl);
    $cURLERROR = curl_errno($curl);
    curl_close($curl);

    if ($cURLERROR) {
        return ['error' => curl_strerror($cURLERROR)];
    }
    
    $data = json_decode($response, true);
    
    if (!is_array($data) || empty($data) || !isset($data[1])) {
        return ['error' => 'Invalid response format or empty data'];
    }
    
    $populationData = [];
    foreach ($data[1] as $countryData) {
        if (isset($countryData['country']['id']) && isset($countryData['value'])) {
            $populationData[$countryData['country']['id']] = $countryData['value'];
        }
    }
    
    return $populationData;
}

function readCountryDataFromFile($filePath) {
    $jsonString = file_get_contents($filePath);
    if ($jsonString === false) {
        return ['error' => "Failed to open or read file at {$filePath}."];
    }
    $data = json_decode($jsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => json_last_error_msg()];
    }
    return $data;
}

function updateCountryDataWithPopulation(&$countries, $populationData) {
    foreach ($countries as &$country) {
        $iso_a2 = $country['iso_a2'];
        if (isset($populationData[$iso_a2])) {
            $country['population'] = $populationData[$iso_a2];
        } else {
            $country['population'] = 'N/A';
        }
    }
    unset($country);
}

$year = isset($_GET['year']) ? $_GET['year'] : '2020';
$filePath = '../files/countryNames.geo.json';

$countryData = readCountryDataFromFile($filePath);
if (isset($countryData['error'])) {
    $output['status']['code'] = 'error';
    $output['status']['name'] = 'Failed to read file';
    $output['status']['description'] = $countryData['error'];
    $output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
    echo json_encode($output);
    exit;
}

$populationData = getWorldPopulationByCountry($year);
if (isset($populationData['error'])) {
    $output['status']['code'] = 'error';
    $output['status']['name'] = 'Failed to fetch data';
    $output['status']['description'] = $populationData['error'];
    $output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
    echo json_encode($output);
    exit;
}

updateCountryDataWithPopulation($countryData, $populationData);

$output['status']['code'] = 200;
$output['status']['name'] = "success";
$output['status']['description'] = "Data successfully updated";
$output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);
$output['data'] = $countryData;

echo json_encode($output, JSON_PRETTY_PRINT);

?>
