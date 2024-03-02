<?php

function getCountryData($countryName = '') {
    $url = $countryName 
           ? "https://restcountries.com/v3.1/name/" . urlencode($countryName)
           : "https://restcountries.com/v3.1/all";

    $response = file_get_contents($url);
    return $response;
}

header('Content-Type: application/json');
if (isset($_GET['countryName']) && !empty($_GET['countryName'])) {
    echo getCountryData($_GET['countryName']);
} else {
    echo getCountryData();
}


?>

