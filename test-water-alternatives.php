<?php
/**
 * Test alternative water data sources
 * Since RWS API requires complex setup, test other options
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Water Data Alternatives</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #0dff00; padding: 20px; line-height: 1.6; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #444; border-radius: 4px; }
        .success { background: #1a3a1a; border-color: #51cf66; }
        .error { background: #3a1a1a; border-color: #ff6b6b; }
        .title { color: #fff; margin-bottom: 5px; font-weight: bold; }
        .response { background: #2d2d2d; padding: 10px; margin: 10px 0; border-left: 3px solid #666; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <h1>💧 Water Data Source Research</h1>
    <p>Testing alternative water level APIs since RWS requires complex POST requests</p>
";

// Test 1: KNMI Water/precipitation data
echo '<div class="test">';
echo '<div class="title">Test 1: KNMI Water Level/Discharge Data</div>';

$url = 'https://api.dataplatform.knmi.nl/open-data/v1/datasets?source=WATER';
$response = @file_get_contents($url);
if ($response !== false) {
    echo '<p style="color: #51cf66;">✓ KNMI water data available</p>';
    $data = json_decode($response, true);
    if (isset($data['datasets'])) {
        echo '<p>Available datasets: ' . count($data['datasets']) . '</p>';
        echo '<div class="response">' . htmlspecialchars(substr($response, 0, 600)) . '</div>';
    }
} else {
    echo '<p style="color: #ff6b6b;">✗ KNMI water data not accessible</p>';
}

echo '</div>';

// Test 2: Open-Meteo has precipitation/soil data
echo '<div class="test">';
echo '<div class="title">Test 2: Open-Meteo Precipitation Data (Rhine Arnhem)</div>';

$url = 'https://api.open-meteo.com/v1/forecast?latitude=51.9850&longitude=5.8910&hourly=precipitation,runoff&forecast_days=1&timezone=Europe/Amsterdam';
$response = @file_get_contents($url);
if ($response !== false) {
    echo '<p style="color: #51cf66;">✓ Precipitation data available</p>';
    $data = json_decode($response, true);
    if (isset($data['hourly'])) {
        echo '<div class="response">';
        echo 'Available hourly fields: ' . implode(', ', array_keys($data['hourly'])) . "\n\n";
        if (isset($data['hourly']['precipitation'])) {
            echo 'Precipitation today: ' . $data['hourly']['precipitation'][0] . ' mm' . "\n";
        }
        if (isset($data['hourly']['runoff'])) {
            echo 'Runoff: ' . $data['hourly']['runoff'][0] . "\n";
        }
        echo '</div>';
    }
} else {
    echo '<p style="color: #ff6b6b;">✗ Open-Meteo precipitation not accessible</p>';
}

echo '</div>';

// Test 3: EUMETSATs water data (if available)
echo '<div class="test">';
echo '<div class="title">Test 3: EUMETSAT Flood Monitoring (if available)</div>';

$url = 'https://www.eumetsat.int/flood-monitoring';
$response = @file_get_contents($url);
if ($response !== false && strlen($response) > 100) {
    echo '<p style="color: #51cf66;">✓ EUMETSAT portal accessible</p>';
    if (strpos($response, 'api') !== false || strpos($response, 'data') !== false) {
        echo '<p>Portal contains water/flood data references</p>';
    }
} else {
    echo '<p style="color: #ff6b6b;">✗ EUMETSAT portal not accessible</p>';
}

echo '</div>';

// Test 4: RWS API with GET instead of POST
echo '<div class="test">';
echo '<div class="title">Test 4: RWS API Alternative Access Methods</div>';

$endpoints = array(
    'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen?Locatie=ARNM',
    'https://rijkswaterstaatdata.nl/geoservices/rest/services/DGA_Waterhuishouding/Oppervlaktewaterstand/MapServer/0/query?where=station_naam=ARNHEM',
);

foreach ($endpoints as $url) {
    echo '<div style="margin: 10px 0; padding: 10px; background: #2d2d2d; border-left: 2px solid #666;">';
    echo '<strong>Endpoint:</strong> ' . htmlspecialchars(substr($url, 0, 80)) . '...<br>';

    $response = @file_get_contents($url);
    if ($response !== false) {
        echo '<p style="color: #51cf66;">✓ Response received</p>';
        echo '<div class="response">' . htmlspecialchars(substr($response, 0, 300)) . '</div>';
    } else {
        echo '<p style="color: #ff6b6b;">✗ No response</p>';
    }
    echo '</div>';
}

echo '</div>';

// Summary
echo '<div class="test" style="background: #2d3d2d; border-color: #51cf66;">';
echo '<div class="title" style="color: #51cf66;">📋 Recommendations</div>';
echo '<p><strong>Findings:</strong></p>';
echo '<ol>';
echo '<li><strong>RWS API Complex:</strong> POST-based API with metadata requirements, may need special authentication</li>';
echo '<li><strong>Alternative Approach:</strong> Use precipitation/runoff data from Open-Meteo as proxy for water level</li>';
echo '<li><strong>Best Option:</strong> Implement RWS API properly with correct POST format and location codes</li>';
echo '<li><strong>Fallback:</strong> Use realistic mock water level based on Rhine standard (1.3-1.5m at Arnhem)</li>';
echo '</ol>';

echo '<p><strong>Decision for Plugin:</strong></p>';
echo '<ul>';
echo '<li>Keep wind API (Open-Meteo) - fully working ✓</li>';
echo '<li>Water level: Keep mock data OR implement RWS API with proper research</li>';
echo '<li>Water level indicator: Could use precipitation as secondary indicator</li>';
echo '</ul>';

echo '</div>';

echo '</body></html>';
?>
