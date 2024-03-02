<?php
$jsonFile = '../files/countryBorders.geo.json';
$jsonData = file_get_contents($jsonFile);
$dataArray = json_decode($jsonData, true);
$namesAndCodes = [];

if (isset($dataArray['features']) && is_array($dataArray['features'])) {
    foreach ($dataArray['features'] as $feature) {
        if (isset($feature['properties']['name']) && isset($feature['properties']['iso_a2'])) {
            $namesAndCodes[] = [
                'name' => $feature['properties']['name'],
                'iso_a2' => $feature['properties']['iso_a2']
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($namesAndCodes);
?>
