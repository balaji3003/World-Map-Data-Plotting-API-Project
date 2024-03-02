<?php


ini_set('display_errors', 'On');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

function httpGet($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: getCountryNews'
    ]);
    $response = curl_exec($ch);
    $curlError = curl_errno($ch);
    if ($curlError) {
        $error = [
            'status' => [
                'code' => $curlError,
                'name' => 'Failure - cURL',
                'description' => curl_strerror($curlError),
                'seconds' => microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]
            ],
            'data' => null
        ];
        curl_close($ch);
        return json_encode($error);
    }
    curl_close($ch);
    return $response;
}

function getNewsByCountryName($countryName) {
    $executionStartTime = microtime(true);
    $url = "https://gnews.io/api/v4/search?q=" . urlencode($countryName) . "&lang=en&max=5&token=9005b789c51dbba7928ff7539b6640af";
    $response = httpGet($url);
    $jsonData = json_decode($response, true);

    if (isset($jsonData['error'])) {
        return json_encode([
            'status' => [
                'code' => 'Failure - API',
                'name' => $jsonData['error'],
                'description' => $jsonData['message'] ?? 'Error fetching news data',
                'seconds' => microtime(true) - $executionStartTime
            ],
            'data' => null
        ]);
    }

    return json_encode([
        'status' => [
            'code' => 200,
            'name' => 'success',
            'description' => 'all ok',
            'seconds' => microtime(true) - $executionStartTime
        ],
        'data' => $jsonData
    ]);
}

if (isset($_GET['countryName']) && !empty($_GET['countryName'])) {
    $countryName = $_GET['countryName'];
    echo getNewsByCountryName($countryName);
} else {
    echo json_encode([
        'status' => [
            'code' => 'Failure',
            'name' => 'No country name provided',
            'description' => 'The country name parameter is missing.',
            'seconds' => microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]
        ],
        'data' => null
    ]);
}


?>
