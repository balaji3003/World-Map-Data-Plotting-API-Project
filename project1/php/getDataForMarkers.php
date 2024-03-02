

<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$executionStartTime = microtime(true);

function httpGet($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: getGeoNamesData' 
    ]);
    $response = curl_exec($ch);
    $curlError = curl_errno($ch);
    if ($curlError) {
        return json_encode([
            'status' => [
                'code' => $curlError,
                'name' => 'Failure - cURL',
                'description' => curl_strerror($curlError),
                'seconds' => number_format((microtime(true) - $GLOBALS['executionStartTime']), 3)
            ],
            'data' => null
        ]);
    }
    curl_close($ch);
    return $response;
}

function getGeoNamesDataByIsoA2($iso_a2) {
    $username = 'balajikrishnamurthy'; 
    $url = "http://api.geonames.org/searchJSON?country=" . urlencode($iso_a2) . "&featureClass=P&maxRows=500&username=" . urlencode($username);
    $response = httpGet($url);
    
    // Attempt to decode the JSON response
    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return json_encode([
            'status' => [
                'code' => json_last_error(),
                'name' => 'Failure - JSON',
                'description' => json_last_error_msg(),
                'seconds' => number_format((microtime(true) - $GLOBALS['executionStartTime']), 3)
            ],
            'data' => null
        ]);
    }
    
    // Check for errors in the decoded response
    if (isset($decodedResponse['status'])) {
        return json_encode([
            'status' => [
                'code' => $decodedResponse['status']['message'],
                'name' => 'Failure - API',
                'description' => $decodedResponse['status']['value'],
                'seconds' => number_format((microtime(true) - $GLOBALS['executionStartTime']), 3)
            ],
            'data' => null
        ]);
    }
    
    // Success response
    return json_encode([
        'status' => [
            'code' => 200,
            'name' => 'success',
            'description' => 'all ok',
            'seconds' => number_format((microtime(true) - $GLOBALS['executionStartTime']), 3)
        ],
        'data' => $decodedResponse
    ]);
}

if (isset($_GET['country']) && !empty($_GET['country'])) {
    $iso_a2 = $_GET['country'];
    echo getGeoNamesDataByIsoA2($iso_a2);
} else {
    echo json_encode([
        'status' => [
            'code' => 'Failure',
            'name' => 'No country code provided',
            'description' => 'The country code parameter is missing.',
            'seconds' => number_format((microtime(true) - $executionStartTime), 3)
        ],
        'data' => null
    ]);
}
?>



