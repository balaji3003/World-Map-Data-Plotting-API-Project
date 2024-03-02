<?php

function httpGet($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function getCountryDetails($isoCode) {
    $url = "http://api.worldbank.org/v2/country/$isoCode?format=json";
    $response = json_decode(httpGet($url), true);
    if (isset($response[1][0]['longitude']) && isset($response[1][0]['latitude'])) {
        return [
            'longitude' => $response[1][0]['longitude'],
            'latitude' => $response[1][0]['latitude']
        ];
    }
    return null;
}

function getWeatherData($longitude, $latitude, $apiKey) {
    $apiEndpoint = "https://api.openweathermap.org/data/2.5/weather";
    $params = [
        'lat' => $latitude,
        'lon' => $longitude,
        'appid' => $apiKey,
        'units' => 'metric' 
    ];
    $url = $apiEndpoint . '?' . http_build_query($params);
    return httpGet($url);
}

function getForecastData($longitude, $latitude, $apiKey) {
    $apiEndpoint = "https://api.openweathermap.org/data/2.5/forecast";
    $params = [
        'lat' => $latitude,
        'lon' => $longitude,
        'appid' => $apiKey,
        'units' => 'metric' 
    ];
    $url = $apiEndpoint . '?' . http_build_query($params);
    return httpGet($url);
}

$apiKey = 'c787dab8838e6164839124590fb67df9'; 

if (isset($_GET['iso_a2']) && !empty($_GET['iso_a2'])) {
    $isoCode = $_GET['iso_a2'];
    $countryDetails = getCountryDetails($isoCode);

    if ($countryDetails) {
        $currentWeatherData = getWeatherData($countryDetails['longitude'], $countryDetails['latitude'], $apiKey);
        $forecastData = getForecastData($countryDetails['longitude'], $countryDetails['latitude'], $apiKey);

        // Decode the JSON responses
        $currentWeather = json_decode($currentWeatherData, true);
        $forecast = json_decode($forecastData, true);

      
        $combinedData = [
            'current_weather' => $currentWeather,
            'forecast' => $forecast
        ];

        header('Content-Type: application/json');
        echo json_encode($combinedData);
    } else {
        echo json_encode(['error' => 'Unable to find country details']);
    }
} else {
    echo json_encode(['error' => 'No ISO alpha-2 code provided']);
}

?>
