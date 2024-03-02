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
            $isoCode = $countryData['country']['id'];
            $populationData[$isoCode] = [
                'country' => $countryData['country']['value'],
                'population' => $countryData['value'],
                'year' => $year
            ];
        }
    }
    
    return $populationData;
}

function getCountryBorders() {
    $filePath = '../files/countryBorders.geo.json'; // Adjust the file path as needed
    if (!file_exists($filePath)) {
        return ['error' => "File {$filePath} not found."];
    }
    $jsonString = file_get_contents($filePath);
    if ($jsonString === false) {
        return ['error' => "Failed to open or read the file {$filePath}."];
    }
    $bordersData = json_decode($jsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => json_last_error_msg()];
    }
    return $bordersData;
}

function mergePopulationAndBorders($populationData, $bordersData) {
    if (isset($populationData['error'])) {
        return $populationData;
    }
    if (isset($bordersData['error'])) {
        return $bordersData;
    }

    $mergedData = [];
    foreach ($bordersData['features'] as $feature) {
        $isoCode = $feature['properties']['iso_a2'];
        if (isset($populationData[$isoCode])) {
            $mergedData[] = array_merge($populationData[$isoCode], [
                'iso_a2' => $isoCode,
                'borders' => $feature['geometry']
            ]);
        }
    }
    
    return $mergedData;
}

$year = isset($_GET['year']) ? $_GET['year'] : 2020;

$populationData = getWorldPopulationByCountry($year);
$bordersData = getCountryBorders();

$mergedData = mergePopulationAndBorders($populationData, $bordersData);

if (isset($mergedData['error'])) {
    $output['status']['code'] = "error";
    $output['status']['name'] = "Failure";
    $output['status']['description'] = $mergedData['error'];
} else {
    $output['status']['code'] = 200;
    $output['status']['name'] = "Success";
    $output['status']['description'] = "Data merged successfully";
    $output['data'] = $mergedData;
}

$output['status']['seconds'] = number_format((microtime(true) - $executionStartTime), 3);

echo json_encode($output, JSON_NUMERIC_CHECK);

?>
