//on load function
/*
$(window).on("load", function () {
  if ($("#preloader").length) {
    $("#preloader")
      .delay(1000)
      .fadeOut("slow", function () {
        $(this).remove();
      });
  }
});*/

//global variables

let currentGeoJsonLayer = null;
let lat = 0;
let lng = 0;
let map;
let isChoroplethVisible = false; // Track whether the choropleth map is displayed
let currentBorderLayer = null; // New variable for country borders
let exchangeRate = 0;
let markerClusters; // Holds the marker cluster group
let markersLayer;

//on document ready initialise map and run the main functions
$(document).ready(function () {
  initializeMap().then(() => {
    //setupGeolocation();
    populateCountrySelectDropdown();
    handleCountrySelectionChange();

    setupCurrencyConversion();
  });
});

function hidePreloader() {
  $("#preloader")
    .delay(1000)
    .fadeOut("slow", function () {
      $(this).remove();
    });
}

// Call the function to initialize the map with geolocation or fallback

function initializeMap() {
  return new Promise((resolve, reject) => {
    // Initialize the map inside the Promise
    map = L.map("map").setView([54.5, -4], 6);

    // Execute geolocation setup and then add tile layers and controls
    setupGeolocation()
      .then(() => {
        L.tileLayer(
          "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}",
          {}
        ).addTo(map);

        // Add various easy buttons for different modal dialogs
        L.easyButton("fa-cloud", function () {
          $("#weatherModal").modal("show");
        }).addTo(map);

        L.easyButton("fa-money-bill-alt", function () {
          $("#currencyModal").modal("show");
        }).addTo(map);

        L.easyButton("fa-book-open", function () {
          $("#wikipediaModal").modal("show");
        }).addTo(map);

        L.easyButton("fa-newspaper", function () {
          $("#newsModal").modal("show");
        }).addTo(map);

        L.easyButton("fa-info", function () {
          $("#countryDetailsModal").modal("show");
        }).addTo(map);

        L.easyButton(
          "fa-map",
          function () {
            toggleChoroplethMap();
          },
          "Toggle Choropleth Map"
        ).addTo(map);

        // Initialize layer control with base layers
        var layerControl = L.control
          .layers({
            Streets: L.tileLayer(
              "https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}"
            ),
            Satellite: L.tileLayer(
              "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}"
            ),
          })
          .addTo(map);

        // Once the map is ready, perform additional setup like adding custom checkboxes
        map.whenReady(function () {
          var $layerControlContainer = $(layerControl.getContainer());
          var $overlaysList = $layerControlContainer.find(
            ".leaflet-control-layers-overlays"
          );

          $overlaysList.append(`
          <label><input type="checkbox" class="leaflet-control-layers-selector" id="extraLayerCheckbox1"> Towns/Cities</label>
          <label><input type="checkbox" class="leaflet-control-layers-selector" id="extraLayerCheckbox2"> POI in Capital</label>
        `);

          // Setup event listeners for the custom checkboxes
          $("#extraLayerCheckbox1").change(function () {
            if (this.checked) {
              getDataForMarkers();
            } else {
              removeMarketClusters();
            }
          });

          $("#extraLayerCheckbox2").change(function () {
            if (this.checked) {
              getDataForMarkers2();
            } else {
              removeMarkers();
            }
          });

          // Resolve the promise after all the setup is done
          resolve();
        });
      })
      .catch((error) => {
        console.error("Error during map initialization:", error);
        reject(error);
      });
  });
}

function populateCountrySelectDropdown() {
  $.ajax({
    url: "/project1/php/getCountriesList.php",
    type: "GET",
    dataType: "json",
    success: function (response) {
      // Sort the countries alphabetically by name
      response.sort(function (a, b) {
        return a.name.localeCompare(b.name);
      });

      // After sorting, append each country to the dropdown
      response.forEach(function (countryObj) {
        $("#countrySelect").append(
          $("<option>", { value: countryObj.iso_a2, text: countryObj.name })
        );
      });
    },
    error: function (xhr, status, error) {},
  });
}

function handleCountrySelectionChange() {
  $("#countrySelect").change(function () {
    const selectedIso = $(this).val();
    getCountryBorder(selectedIso, map); // This already zooms to the selected country
    getCountryMajorDetails();
    getCountryWeather();
    getCountryNews();
    getCountryExchangeRate();
    if ($("#extraLayerCheckbox1").is(":checked")) {
      getDataForMarkers();
    } else {
      // Remove layer 1
      removeMarketClusters();
    }

    if ($("#extraLayerCheckbox2").is(":checked")) {
      getDataForMarkers2();
    } else {
      // Remove layer 1
      removeMarkers();
    }

    //getDataForMarkers();
  });
}

async function setupGeolocation() {
  if (navigator.geolocation) {
    await navigator.geolocation.getCurrentPosition(
      function (position) {
        lat = position.coords.latitude;
        lng = position.coords.longitude;
        map.setView([lat, lng], 13);
        // L.marker([lat, lng]).addTo(map).bindPopup("You are here!").openPopup();
        getCountryDetailsAndUpdateSelect(lng, lat, map);
      },
      function (error) {}
    );
  } else {
  }
}

function getCountryDetailsAndUpdateSelect(longitude, latitude, map) {
  $.ajax({
    url: "/project1/php/getCountryMajorDetails.php",
    type: "GET",
    data: { longitude: longitude, latitude: latitude },
    dataType: "json",
    success: function (response) {
      // Check if the response has data and results array is not empty
      if (
        response.data &&
        response.data.results &&
        response.data.results.length > 0
      ) {
        // Accessing the ISO country code from the components section
        var isoCode = response.data.results[0].components["ISO_3166-1_alpha-2"];
        $("#countrySelect").val(isoCode).change(); // Update the select element with the country code
        hidePreloader(); // Assuming this function hides a loading indicator
      } else {
        // Optionally handle cases where no results are found or data is missing
        console.error("No results found or missing data.");
        // Optionally, call hidePreloader here as well if it's being shown
      }
    },
    error: function (xhr, status, error) {
      // Handle AJAX errors
      console.error("Error fetching country details:", status, error);
      // Optionally, call hidePreloader here to hide loading indicator on error
    },
  });
}

function getCountryBorder(isoCode, map) {
  $.ajax({
    url: "/project1/php/getCountryBorder.php",
    type: "GET",
    data: { isoCode: isoCode },
    dataType: "json",
    success: function (response) {
      drawCountryBorder(response, map);
    },
    error: function (xhr, status, error) {},
  });
}

function drawCountryBorder(geojsonData, map) {
  if (currentBorderLayer) {
    map.removeLayer(currentBorderLayer); // Remove the existing border layer if any
  }
  currentBorderLayer = L.geoJSON(geojsonData, {
    onEachFeature: function (feature, layer) {
      if (feature.properties && feature.properties.name) {
        layer.bindPopup(feature.properties.name);
      }
    },
  }).addTo(map);
  map.fitBounds(currentBorderLayer.getBounds());
}

function getCountryMajorDetails() {
  var selectedCountryText = $("#countrySelect option:selected").text();
  var modifiedCountryText = selectedCountryText.replace(/\s+/g, "-");
  $.ajax({
    url: "/project1/php/getCountryMajorDetails.php",
    type: "GET",
    data: { countryName: modifiedCountryText },
    dataType: "json",
    success: function (response) {
      populateModalWithDetails(extractCountryDetailsFromJson(response));
    },
    error: function (xhr, status, error) {
      // Update the modal content to show the error message
      $("#countryDetailsModal .modal-body").html(
        "<table><tr><td>Country Details Currently Unavailable</td></tr></table>"
      );
    },
  });
}

function getCountryWeather() {
  var selectedCountryText = $("#countrySelect option:selected").val();
  $.ajax({
    url: "/project1/php/getNearbyWeather.php",
    type: "GET",
    data: { iso_a2: selectedCountryText },
    dataType: "json",
    success: function (response) {
      const weatherDetails = extractWeatherDetails(response);
      updateWeatherModal(weatherDetails);
    },
    error: function (xhr, status, error) {},
  });
}

function extractWeatherDetails(jsonData) {
  // Extract current weather details
  const {
    current_weather: {
      main: { temp: currentTemp, feels_like: currentFeelsLike },
      weather: currentWeather,
      clouds: { all: currentClouds },
      wind: { speed: currentWindSpeed, deg: currentWindDeg },
    },
    forecast: { list },
  } = jsonData;

  // Process current weather
  const currentWeatherDetails = {
    Main: currentWeather[0].main,
    Description: currentWeather[0].description,
    "Temp Celsius": currentTemp, // Append unit to key
    "Feels Like Celsius": currentFeelsLike, // Append unit to key
    "Clouds (%)": currentClouds,
    "Wind Speed (m/s)": currentWindSpeed,
    "Wind Direction (degrees)": currentWindDeg,
  };

  // Process forecast weather details
  const forecastDetails = list.map((forecast) => {
    const {
      main: { temp, feels_like },
      weather,
      clouds: { all: clouds },
      wind: { speed: windSpeed, deg: windDeg },
      dt_txt: dateTime,
    } = forecast;

    return {
      DateTime: dateTime,
      Main: weather[0].main,
      Description: weather[0].description,
      "Temp Celsius": temp, // Append unit to key
      "Feels Like Celsius": feels_like, // Append unit to key
      "Clouds (%)": clouds,
      "Wind Speed (m/s)": windSpeed,
      "Wind Direction (degrees)": windDeg,
    };
  });

  // Return both current weather and forecast details
  return {
    currentWeather: currentWeatherDetails,
    forecast: forecastDetails,
  };
}

function updateWeatherModal(weatherDetails) {
  let modalContent = "";

  // Helper function to get FontAwesome icon class based on weather condition
  function getWeatherIcon(condition) {
    const weatherIcons = {
      Clouds: "fas fa-cloud",
      Rain: "fas fa-cloud-rain",
      Clear: "fas fa-sun",
      Snow: "fas fa-snowflake",
      // Add more mappings as needed
    };
    return weatherIcons[condition] || "fas fa-cloud"; // Default icon
  }

  // Helper function to get FontAwesome icon for wind direction
  function getWindIcon(degrees) {
    if (degrees > 45 && degrees <= 135) {
      return "fas fa-arrow-right"; // East
    } else if (degrees > 135 && degrees <= 225) {
      return "fas fa-arrow-down"; // South
    } else if (degrees > 225 && degrees <= 315) {
      return "fas fa-arrow-left"; // West
    } else {
      return "fas fa-arrow-up"; // North
    }
  }

  // Add current weather details with icons
  modalContent += "<tr><th colspan='2'>Current Weather</th></tr>";
  for (const [key, value] of Object.entries(weatherDetails.currentWeather)) {
    let iconHtml = "";
    if (key === "Main") {
      iconHtml = `<i class="${getWeatherIcon(value)}"></i> `;
    } else if (key.includes("Wind Direction")) {
      iconHtml = `<i class="${getWindIcon(value)}"></i> `;
    }
    modalContent += `<tr><td>${key}</td><td>${iconHtml}${value}</td></tr>`;
  }

  // Add forecast details for the first two forecasts with icons
  modalContent += "<tr><th colspan='2'>Forecast</th></tr>";
  weatherDetails.forecast.slice(0, 2).forEach((forecast, index) => {
    modalContent += `<tr><td colspan='2'><b>Forecast ${index + 1} (${
      forecast.DateTime
    })</b></td></tr>`;
    for (const [key, value] of Object.entries(forecast)) {
      let iconHtml = "";
      if (key === "Main") {
        iconHtml = `<i class="${getWeatherIcon(value)}"></i> `;
      } else if (key.includes("Wind Direction")) {
        iconHtml = `<i class="${getWindIcon(value)}"></i> `;
      }
      if (key !== "DateTime") {
        modalContent += `<tr><td>${key}</td><td>${iconHtml}${value}</td></tr>`;
      }
    }
  });

  // Update the modal's content
  $("#weatherModal .modal-body table").html(modalContent);
}

function extractCountryDetailsFromJson(jsonResponse) {
  if (
    jsonResponse.data &&
    jsonResponse.data.results &&
    jsonResponse.data.results.length > 0
  ) {
    const result = jsonResponse.data.results[0];
    const {
      annotations: {
        currency: { iso_code: currencyIsoCode, name: currencyName },
        roadinfo: { drive_on: roadInfoDriveOn, speed_in: speedIn },
        sun: {
          rise: { apparent: sunRiseApparent },
          set: { apparent: sunSetApparent },
        },
        timezone: { name: timezoneName },
        flag: countryFlag,
      },
      bounds: {
        northeast: { lat: boundsNortheastLat, lng: boundsNortheastLng },
        southwest: { lat: boundsSouthwestLat, lng: boundsSouthwestLng },
      },
      components: { "ISO_3166-1_alpha-2": countryCode },
    } = result;

    return {
      currencyIsoCode,
      currencyName,
      roadInfoDriveOn,
      speedIn,
      sunRiseApparent,
      sunSetApparent,
      timezoneName,
      countryFlag,
      boundsNortheastLat,
      boundsNortheastLng,
      boundsSouthwestLat,
      boundsSouthwestLng,
      countryCode, // ISO 3166-1 alpha-2 country code
    };
  } else {
    return {};
  }
}

function populateModalWithDetails(details) {
  const modalBodyTable = $("#countryDetailsModal .modal-body table");

  modalBodyTable.find("tr").remove();
  const detailItems = [
    {
      icon: "fa-euro-sign",
      attribute: "Currency ISO Code",
      value: details.currencyIsoCode,
    },
    {
      icon: "fa-money-bill-wave",
      attribute: "Currency Name",
      value: details.currencyName,
    },
    { icon: "fa-road", attribute: "Drive On", value: details.roadInfoDriveOn },
    {
      icon: "fa-tachometer-alt",
      attribute: "Speed In",
      value: details.speedIn,
    },
    {
      icon: "fa-sun",
      attribute: "Sun Rise (apparent)",
      value: new Date(details.sunRiseApparent * 1000).toLocaleTimeString(),
    },
    {
      icon: "fa-moon",
      attribute: "Sun Set (apparent)",
      value: new Date(details.sunSetApparent * 1000).toLocaleTimeString(),
    },
    {
      icon: "fa-clock",
      attribute: "Timezone Name",
      value: details.timezoneName,
    },
    {
      icon: "fa-map-marker-alt",
      attribute: "Bounds Northeast Lat",
      value: details.boundsNortheastLat,
    },
    {
      icon: "fa-map-marker-alt",
      attribute: "Bounds Northeast Lng",
      value: details.boundsNortheastLng,
    },
    {
      icon: "fa-map-marker-alt",
      attribute: "Bounds Southwest Lat",
      value: details.boundsSouthwestLat,
    },
    {
      icon: "fa-map-marker-alt",
      attribute: "Bounds Southwest Lng",
      value: details.boundsSouthwestLng,
    },
  ];
  detailItems.forEach((item) => {
    const row = `<tr><td class="text-center"><i class="fas ${item.icon} fa-xl text-success"></i></td><td>${item.attribute}</td><td class="text-end">${item.value}</td></tr>`;
    modalBodyTable.append(row);
  });
}

function getWikipediaArticlesAndPopulateModal() {
  var iso_a2 = $("#countrySelect").val(); // Ensure this is correctly obtaining the selected country's value

  $.ajax({
    url: "/project1/php/getWikipediaArticles.php",
    type: "GET",
    data: { iso_a2: iso_a2 },
    dataType: "json",
    success: function (response) {
      if (response && response.data && response.data.length > 0) {
        var tableContent = "";

        response.data.forEach(function (article) {
          tableContent += `<tr>
              <td><a href="${article.url}" target="_blank">${article.name}</a></td>
              <td>${article.description}</td>
          </tr>`;
        });

        // Ensure the table's tbody within the modal is correctly targeted
        $("#wikipediaModal .modal-body table tbody").html(tableContent);
      } else {
        $("#wikipediaModal .modal-body table tbody").html(
          "<tr><td colspan='3'>No articles found.</td></tr>"
        );
      }
    },
    error: function (xhr, status, error) {
      console.error("Error fetching Wikipedia articles:", error);
    },
  });
}

$("#countrySelect").change(function () {
  getWikipediaArticlesAndPopulateModal();
});

function getPopulationColor(population) {
  const color =
    population > 100000000
      ? "#800026"
      : population > 50000000
      ? "#BD0026"
      : population > 20000000
      ? "#E31A1C"
      : population > 10000000
      ? "#FC4E2A"
      : population > 5000000
      ? "#FD8D3C"
      : population > 2000000
      ? "#FEB24C"
      : population > 1000000
      ? "#FED976"
      : "#FFEDA0";

  return color;
}

function toggleChoroplethMap() {
  if (isChoroplethVisible && currentGeoJsonLayer) {
    // Hide choropleth map
    map.removeLayer(currentGeoJsonLayer);
    togglePopulationMessage(false); // Hide message
    isChoroplethVisible = false;
  } else {
    // Show choropleth map
    addChoroplethLayer();
    togglePopulationMessage(true); // Show message
    isChoroplethVisible = true;
  }
}

function togglePopulationMessage(isVisible) {
  // Check if the message element exists, if not create it
  if ($("#populationMessage").length === 0) {
    // Append the message element after the country select dropdown
    $("#countrySelect").after(
      '<div id="populationMessage" style="margin-top: 10px;"></div>'
    );
  }
  // Update the message based on the choropleth visibility
  if (isVisible) {
    $("#populationMessage")
      .html(
        '<div class="alert alert-warning alert-dismissible fade show text-center" role="alert"><strong>World Population Heatmap is visible</strong> You can toggle off by clicking the icon again.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
      )
      .show();
  } else {
    $("#populationMessage").html("").hide();
  }
}

/*function addChoroplethLayer() {
  if (!currentGeoJsonLayer || !map.hasLayer(currentGeoJsonLayer)) {
    // Check if the layer doesn't already exist
    // Fetch and add choropleth layer without toggling it off
    $.ajax({
      url: "/project1/php/getWorldPopulationData.php",
      type: "GET",
      dataType: "json",
      success: function (response) {
        if (response.success && response.data.length) {
          const geoJsonFeatures = response.data.map((country) => ({
            type: "Feature",
            properties: {
              name: country.country,
              population: country.population,
              iso_a2: country.iso_a2,
            },
            geometry: country.borders,
          }));

          currentGeoJsonLayer = L.geoJSON(geoJsonFeatures, {
            style: (feature) => ({
              fillColor: getPopulationColor(feature.properties.population),
              weight: 2,
              opacity: 1,
              color: "white",
              fillOpacity: 0.7,
            }),
            onEachFeature: (feature, layer) =>
              layer.bindPopup(
                `${
                  feature.properties.name
                }: ${feature.properties.population.toLocaleString()} people`
              ),
          }).addTo(map);
        }
      },
      error: function (xhr, status, error) {},
    });
  }
}
*/
function addChoroplethLayer() {
  if (!currentGeoJsonLayer || !map.hasLayer(currentGeoJsonLayer)) {
    // Fetch and add choropleth layer
    $.ajax({
      url: "/project1/php/getWorldPopulationData.php",
      type: "GET",
      dataType: "json",
      success: function (response) {
        if (response.status.code === 200 && response.data.length) {
          const geoJsonFeatures = response.data.map((country) => ({
            type: "Feature",
            properties: {
              name: country.country,
              population: country.population,
              iso_a2: country.iso_a2,
              year: country.year,
            },
            geometry: country.borders,
          }));

          currentGeoJsonLayer = L.geoJSON(geoJsonFeatures, {
            style: (feature) => ({
              fillColor: getPopulationColor(feature.properties.population),
              weight: 2,
              opacity: 1,
              color: "white",
              fillOpacity: 0.7,
            }),
            onEachFeature: (feature, layer) =>
              layer.bindPopup(
                `${feature.properties.name} (${
                  feature.properties.year
                }): ${feature.properties.population.toLocaleString()} people`
              ),
          }).addTo(map);
        } else {
          console.error("Failed to retrieve population data or borders data.");
        }
      },
      error: function (xhr, status, error) {
        console.error("An error occurred: " + error);
      },
    });
  }
}

function getCountryNews() {
  var countryName = $("#countrySelect").find(":selected").text();

  $.ajax({
    url: "/project1/php/getCountryNews.php",
    type: "GET",
    data: { countryName: countryName },
    dataType: "json",
    success: function (response) {
      // Check if the request was successful and there are articles
      if (
        response.status.code === 200 &&
        response.data.totalArticles > 0 &&
        response.data.articles.length > 0
      ) {
        var tableContent = "";

        response.data.articles.forEach(function (article) {
          tableContent +=
            "<tr>" +
            "<td><img src='" +
            article.image +
            "' class='img-fluid' alt='Article Image' style='max-width: 200px;'></td>" + // Use max-width for responsive image sizing
            "<td><strong><a href='" +
            article.url +
            "' target='_blank'>" +
            article.title +
            "</a></strong><br>" +
            article.description +
            "<br>" +
            "<small class='text-muted'>Published: " +
            new Date(article.publishedAt).toLocaleString() +
            "</small><br>" +
            "<small class='text-muted'>Source: " +
            article.source.name +
            "</small></td>" +
            "</tr>";
        });

        // Correctly target the modal body table and ensure tbody is present
        $("#newsModal .modal-body table tbody").html(tableContent);
        $("#newsModal .modal-title").text("Breaking News");
      } else {
        // Handle the case where no news articles are found
        $("#newsModal .modal-body table tbody").html(
          "<tr><td colspan='2'>No news found for this country.</td></tr>"
        );
        $("#newsModal").modal("show"); // Show the modal with the no news message
      }
    },
    error: function (xhr, status, error) {
      // Handle errors from the AJAX request
      $("#newsModal .modal-body table tbody").html(
        "<tr><td colspan='2'>Error fetching news. Please try again later.</td></tr>"
      );
      $("#newsModal").modal("show"); // Show the modal with the error message
    },
  });
}

$("#countrySelect").change(function () {
  getCountryNews();
});

function getCountryExchangeRate() {
  var selectedCountryText = $("#countrySelect option:selected").text();
  var modifiedCountryText = selectedCountryText.replace(/\s+/g, "-");
  $.ajax({
    url: "/project1/php/getCountryExchangeRate.php",
    type: "GET",
    data: { countryName: modifiedCountryText },
    dataType: "json",
    success: function (response) {
      populateModalWithExchangeRateDetails(
        extractExchangeRateDetailsFromJson(response)
      );
    },
    error: function (xhr, status, error) {},
  });
}

function populateModalWithExchangeRateDetails(details) {
  // Update the label for the local currency with the currency name
  $('label[for="localCurrency"]').text(details.currencyName + " Currency");

  // Update the value of the foreign currency input with the exchange rate, limited to 4 decimal places
  $("#foreignCurrency").val(details.exchangeRate.toFixed(4));

  $("#foreignCurrency").attr("readonly", true);
}

function extractExchangeRateDetailsFromJson(jsonData) {
  // Ensure that 'jsonData' has the 'data' property and it contains 'currency' and 'exchangeRates'
  if (
    jsonData &&
    jsonData.data &&
    jsonData.data.currency &&
    jsonData.data.exchangeRates &&
    jsonData.data.exchangeRates.rates
  ) {
    var currencyName = jsonData.data.currency;
    exchangeRate = jsonData.data.exchangeRates.rates[currencyName];

    // Check if the exchange rate is valid and a number. If not, set it to a default or error value
    if (typeof exchangeRate !== "number" || isNaN(exchangeRate)) {
      console.error("Invalid exchange rate encountered.");
      exchangeRate = "Exchange rate not available"; // You might want to handle this error differently
    }

    return {
      currencyName: currencyName,
      exchangeRate: exchangeRate,
    };
  } else {
    // Handle the case where the necessary data is missing or in an unexpected format
    console.error("Expected data missing from JSON response.");
    return {
      currencyName: "Unknown",
      exchangeRate: "Exchange rate not available", // Adjust based on how you want to handle this error case
    };
  }
}

function setupCurrencyConversion() {
  $("#localCurrency").on("input", function () {
    this.value = this.value
      .replace(/[^0-9.]/g, "")
      .replace(/(\..*?)\..*/g, "$1");
  });

  $("#localCurrency").on("change keyup", function () {
    let localCurrencyValue = parseFloat($(this).val());

    if (!isNaN(localCurrencyValue)) {
      let foreignCurrencyValue = localCurrencyValue * exchangeRate;

      $("#foreignCurrency").val(foreignCurrencyValue.toFixed(4));
    } else {
      $("#foreignCurrency").val("");
    }
  });
}
/*function getDataForMarkers() {
  var selectedCountry = $("#countrySelect").val();
  var requestURL = "/project1/php/getDataForMarkers.php";
  $.ajax({
    url: requestURL,
    type: "GET",
    data: { country: selectedCountry },
    dataType: "json",
    success: function (data) {
      if (data && data.geonames) {
        var marketLocations = data.geonames.map(function (location) {
          return {
            lat: parseFloat(location.lat),
            lng: parseFloat(location.lng),
            name: location.name,
            population: location.population,
          };
        });
        addMarketClusters(marketLocations);
      } else {
      }
    },
    error: function (xhr, status, error) {},
  });
}*/
function getDataForMarkers() {
  var selectedCountry = $("#countrySelect").val();
  var requestURL = "/project1/php/getDataForMarkers.php";
  $.ajax({
    url: requestURL,
    type: "GET",
    data: { country: selectedCountry },
    dataType: "json",
    success: function (response) {
      // Check if the response has a 'data' object and 'geonames' is an array within it
      if (response && response.data && Array.isArray(response.data.geonames)) {
        var marketLocations = response.data.geonames.map(function (location) {
          return {
            lat: parseFloat(location.lat),
            lng: parseFloat(location.lng),
            name: location.name,
            population: location.population,
          };
        });
        addMarketClusters(marketLocations);
      } else {
        // Handle the case where data is not in the expected format
        console.error("Data format is not as expected.");
      }
    },
    error: function (xhr, status, error) {
      // Handle any errors that occur during the request
      console.error("An error occurred: " + error);
    },
  });
}

function addMarketClusters(marketLocations) {
  if (markerClusters) {
    markerClusters.clearLayers();
  } else {
    markerClusters = L.markerClusterGroup();
  }

  marketLocations.forEach(function (location) {
    var iconUrl;
    var shadowURL = "/project1/files/leaf-shadow.png";
    if (location.population > 500000) {
      iconUrl = "/project1/files/leaf-red.png";
    } else if (location.population > 100000) {
      iconUrl = "/project1/files/leaf-orange.png";
    } else {
      iconUrl = "/project1/files/leaf-green.png";
    }

    var customIcon = L.icon({
      iconUrl: iconUrl,
      shadowUrl: shadowURL,
      shadowSize: [25, 32],
      iconSize: [19, 47.5],
      iconAnchor: [11, 47],
      popupAnchor: [-1.5, -38],
    });

    var marker = L.marker([location.lat, location.lng], {
      icon: customIcon,
    }).bindPopup(
      `<b>${location.name}</b><br>Population: ${location.population}<br>lat: ${location.lat}<br>lng: ${location.lng}`
    );
    markerClusters.addLayer(marker);
  });

  map.addLayer(markerClusters);
}

function removeMarketClusters() {
  if (markerClusters) {
    markerClusters.clearLayers();
    map.removeLayer(markerClusters);
  }
}

// Global variable for markers layer

// Function to get data for markers with ISO A2 code
function getDataForMarkers2() {
  var selectedCountry = $("#countrySelect").val();
  var requestURL = "/project1/php/getNearbyFeatures.php";

  $.ajax({
    url: requestURL,
    type: "GET",
    data: { iso_a2: selectedCountry }, // Correctly using iso_a2 as data key
    dataType: "json",
    success: function (response) {
      // Check if response has a 'data' key and it's an array
      if (response && response.data && Array.isArray(response.data)) {
        // Now pass response.data which is the array of locations
        addMarkersWithClusters(response.data); // Correctly passing the array of data
      } else {
        // Optionally handle the case where data is not in the expected format or missing
        console.error("Data is not in the expected format or missing.");
      }
    },
    error: function (xhr, status, error) {
      // Optionally handle AJAX request errors
      console.error("An error occurred: " + error);
    },
  });
}

// Function to add markers with clusters to the map
function addMarkersWithClusters(locations) {
  // Check if markersLayer is initialized; if not, create it
  if (!markersLayer) {
    markersLayer = L.markerClusterGroup();
  } else {
    markersLayer.clearLayers(); // Clear existing markers
  }

  var iconUrl = "/project1/files/icons8-map-48.png";
  var customIcon = L.icon({
    iconUrl: iconUrl,
    iconSize: [38, 38], // Icon size
    shadowSize: [50, 64], // Shadow size
    iconAnchor: [22, 94], // Anchor point
    popupAnchor: [-3, -76], // Popup anchor
  });

  locations.forEach(function (location) {
    var marker = L.marker([location.lat, location.lng], {
      icon: customIcon,
    }).bindPopup(`<b>${location.toponymName}</b>`);
    markersLayer.addLayer(marker);
  });

  map.addLayer(markersLayer);
}

function removeMarkers() {
  if (markersLayer) {
    markersLayer.clearLayers();
    map.removeLayer(markersLayer);
    markersLayer = null;
  }
}
