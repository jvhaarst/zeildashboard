<?php
/**
 * RWS Open Data Research - Find actual working endpoints
 * Exploring RWS OpenData portal to find real water measurements
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>RWS Open Data Research</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #0dff00; padding: 20px; line-height: 1.6; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #444; border-radius: 4px; }
        .success { background: #1a3a1a; border-color: #51cf66; }
        .title { color: #fff; margin-bottom: 5px; font-weight: bold; }
        .response { background: #2d2d2d; padding: 10px; margin: 10px 0; border-left: 3px solid #666; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 500px; overflow-y: auto; font-size: 11px; }
        .endpoint { color: #74c0fc; margin: 5px 0; }
        .finding { color: #51cf66; margin: 5px 0; }
    </style>
</head>
<body>
    <h1>🌊 RWS Open Data Portal Research</h1>
    <p>Exploring actual working RWS endpoints for real water measurements</p>
";

// Test 1: Explore RWS OpenData home page for API references
echo '<div class="test success">';
echo '<div class="title">Test 1: RWS OpenData Portal Discovery</div>';

$url = 'https://rijkswaterstaatdata.nl/';
$response = @file_get_contents($url);

if ($response !== false) {
    echo '<p style="color: #51cf66;">✓ RWS portal accessible</p>';

    // Extract all links to potential API endpoints
    if (preg_match_all('/href=["\']([^"\']*(?:api|data|water|service)[^"\']*)["\']|https:\/\/[^\s"<>]*(?:api|data|water|service)[^\s"<>]*/i', $response, $matches)) {
        echo '<p>Found API/data references:</p>';
        echo '<div class="response">';
        foreach (array_unique($matches[0] ?? []) as $link) {
            $link = trim($link, '"\'href=');
            if (!empty($link) && (strpos($link, 'http') === 0 || strpos($link, '/') === 0)) {
                echo htmlspecialchars($link) . "\n";
            }
        }
        echo '</div>';
    }

    // Look for GeoServer or REST API references
    if (strpos($response, 'geoserver') !== false) {
        echo '<p class="finding">✓ GeoServer found (REST API available)</p>';
    }
    if (strpos($response, 'mapserver') !== false) {
        echo '<p class="finding">✓ MapServer found (query services)</p>';
    }
} else {
    echo '<p style="color: #ff6b6b;">✗ Portal not accessible</p>';
}

echo '</div>';

// Test 2: Try RWS WMS/WFS services (if GeoServer is available)
echo '<div class="test">';
echo '<div class="title">Test 2: RWS GeoServer WFS Service (Water Features)</div>';

$endpoints = [
    // Try WFS GetFeature for water level
    'https://www.arcgisonline.nl/arcgis/rest/services/Rijkswaterstaat/Waterstand/MapServer/query?where=1=1&outFields=*&returnGeometry=true&f=json',
    'https://arcgisonline.nl/arcgis/rest/services/Rijkswaterstaat/Waterstand/MapServer/0/query?where=1=1&outFields=*&f=json&returnGeometry=false',
    'https://services.arcgisonline.nl/arcgis/rest/services/Rijkswaterstaat/Waterstand/FeatureServer/0/query?where=1=1&outFields=*&f=json',
    // Alternative: direct RWS geoserver
    'https://services.rijkswaterstaat.nl/arcgis/rest/services/Waterstand/MapServer/query?where=STATION_NAME%20like%20%27%25Arnhem%25%27&outFields=*&f=json',
];

foreach ($endpoints as $endpoint) {
    echo '<div style="margin: 10px 0; padding: 10px; background: #2d2d2d; border-left: 2px solid #444;">';
    echo '<strong>Endpoint:</strong><br>';
    echo '<span class="endpoint">' . htmlspecialchars(substr($endpoint, 0, 100)) . '...</span><br>';

    $response = @file_get_contents($endpoint);
    if ($response !== false) {
        $data = @json_decode($response, true);
        if (is_array($data)) {
            if (isset($data['features']) && !empty($data['features'])) {
                echo '<p class="finding">✓ Found ' . count($data['features']) . ' features!</p>';
                echo '<div class="response">' . htmlspecialchars(substr($response, 0, 400)) . '</div>';
            } elseif (isset($data['error'])) {
                echo '<p>Error: ' . htmlspecialchars($data['error']['message'] ?? 'Unknown') . '</p>';
            } else {
                echo '<p>Response: ' . htmlspecialchars(substr($response, 0, 150)) . '...</p>';
            }
        } else {
            echo '<p>Response length: ' . strlen($response) . ' bytes</p>';
        }
    } else {
        echo '<p style="color: #ff6b6b;">✗ No response</p>';
    }
    echo '</div>';
}

echo '</div>';

// Test 3: Search for FEWS (Flood Early Warning System) data
echo '<div class="test">';
echo '<div class="title">Test 3: RWS FEWS Water Measurements</div>';

$fews_endpoints = [
    'https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/timeseries?moduleInstanceId=ImportEUMETSAT&filter=Arnhem&documentFormat=json&showThresholds=true',
    'https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/timeseries?moduleInstanceId=ImportEUMETSAT&filter=ARNM&documentFormat=json',
    'https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/timeseries?moduleInstanceId=Waterdata&filter=Arnhem&documentFormat=json',
];

foreach ($fews_endpoints as $endpoint) {
    echo '<div style="margin: 10px 0; padding: 10px; background: #2d2d2d; border-left: 2px solid #444;">';
    echo '<span class="endpoint">' . htmlspecialchars(substr($endpoint, 0, 90)) . '...</span><br>';

    $response = @file_get_contents($endpoint);
    if ($response !== false && strlen($response) > 50) {
        $data = @json_decode($response, true);
        if (is_array($data)) {
            echo '<p class="finding">✓ JSON response received</p>';
            echo '<div class="response">' . htmlspecialchars(substr($response, 0, 500)) . '</div>';
        }
    } else {
        echo '<p style="color: #ff6b6b;">✗ No response or timeout</p>';
    }
    echo '</div>';
}

echo '</div>';

// Test 4: Try actual KNMI water data endpoint
echo '<div class="test">';
echo '<div class="title">Test 4: KNMI Water Discharge Data</div>';

$knmi_endpoints = [
    'https://api.dataplatform.knmi.nl/open-data/v1/datasets?source=KNMI&limit=100',
    'https://api.dataplatform.knmi.nl/open-data/v1/datasets?name=*water*',
    'https://api.dataplatform.knmi.nl/open-data/v1/datasets?name=*discharge*',
];

foreach ($knmi_endpoints as $endpoint) {
    echo '<div style="margin: 10px 0; padding: 10px; background: #2d2d2d; border-left: 2px solid #444;">';
    echo '<span class="endpoint">' . htmlspecialchars(substr($endpoint, 0, 90)) . '...</span><br>';

    $response = @file_get_contents($endpoint);
    if ($response !== false) {
        $data = @json_decode($response, true);
        if (isset($data['datasets'])) {
            echo '<p>Datasets found: ' . count($data['datasets']) . '</p>';
            foreach (array_slice($data['datasets'], 0, 3) as $ds) {
                echo '<p style="color: #74c0fc;">' . htmlspecialchars($ds['name'] ?? 'Unknown') . '</p>';
            }
        }
    }
    echo '</div>';
}

echo '</div>';

echo '<div class="test" style="background: #2d3d2d; border-color: #51cf66;">';
echo '<div class="title" style="color: #51cf66;">📋 Recommendations</div>';
echo '<ol>';
echo '<li><strong>ArcGIS Server:</strong> Try above ArcGIS REST API endpoints - many governments use this</li>';
echo '<li><strong>Station Code:</strong> Need to find exact code for Rhine Arnhem (ARNM? ARNHEM? NEDR?)</li>';
echo '<li><strong>Field Names:</strong> Look for "waterstand" (water level NAP m) and "afvoer" (discharge m³/s)</li>';
echo '<li><strong>Test in Browser:</strong> Visit working endpoint URLs directly to see data structure</li>';
echo '<li><strong>Backup Option:</strong> Check if KNMI has official water discharge datasets</li>';
echo '</ol>';
echo '</div>';

echo '</body></html>';
?>
