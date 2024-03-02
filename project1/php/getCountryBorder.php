<?php

$jsonFile = '../files/countryBorders.geo.json';
$jsonData = file_get_contents($jsonFile);
$dataArray = json_decode($jsonData, true);

function filterByIsoCode($features, $isoCode) {
    $filteredData = [];
    foreach ($features as $feature) {
        if (isset($feature['properties']['iso_a2']) && $feature['properties']['iso_a2'] === $isoCode) {
            $filteredData[] = $feature;
        }
    }
    return $filteredData;
}

$searchIsoCode = isset($_GET['isoCode']) && !empty($_GET['isoCode']) ? $_GET['isoCode'] : 'BS';
$filteredFeatures = filterByIsoCode($dataArray['features'], $searchIsoCode);

header('Content-Type: application/json');
echo json_encode($filteredFeatures);
?>
