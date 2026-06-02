<?php
/**
 * RWS Live Water Data Test
 * Testing documented RWS public data endpoints
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>RWS Live Water Data</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #0dff00; padding: 20px; line-height: 1.6; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #444; border-radius: 4px; }
        .success { background: #1a3a1a; border-color: #51cf66; }
        .title { color: #fff; margin-bottom: 5px; font-weight: bold; }
        .response { background: #2d2d2d; padding: 10px; margin: 10px 0; border-left: 3px solid #666; overflow-x: auto; white-space: pre-wrap; max-height: 400px; overflow-y: auto; font-size: 12px; }
        .data { color: #51cf66; }
    </style>
</head>
<body>
    <h1>🌊 RWS Live Water Data Testing</h1>
    <p>Testing publicly documented RWS data endpoints</p>
";

// Test 1: RWS water.overheid.nl - Dutch government water data
echo '<div class="test">';
echo '<div class="title">Test 1: water.overheid.nl API</div>';

$url = 'https://www.water.overheid.nl/';
$response = @file_get_contents($url);

if ($response !== false && strpos($response, 'api') !== false) {
    echo '<p style="color: #51cf66;">✓ Water portal found - searching for API...<br>';

    // Extract API references
    if (preg_match_all('/data\.rijkswaterstaat\.nl[^\s"<>]*|api[^\s"<>]*water[^\s"<>]*/i', $response, $matches)) {
        echo 'Found ' . count($matches[0]) . ' potential API references</p>';
        foreach (array_unique($matches[0]) as $ref) {
            echo '<code style="color: #74c0fc;">' . htmlspecialchars($ref) . '</code><br>';
        }
    }
} else {
    echo '<p>Portal not directly accessible, trying direct API endpoints...</p>';
}

echo '</div>';

// Test 2: CKAN data.rijkswaterstaat.nl (Dutch CKAN instance)
echo '<div class="test">';
echo '<div class="title">Test 2: RWS CKAN Data Portal</div>';

$ckan_endpoints = [
    'https://data.rijkswaterstaat.nl/api/3/action/package_search?q=water+level&rows=10',
    'https://data.rijkswaterstaat.nl/api/3/action/package_search?q=Arnhem&rows=10',
    'https://data.rijkswaterstaat.nl/api/3/action/package_search?q=discharge&rows=10',
];

foreach ($ckan_endpoints as $endpoint) {
    echo '<div style="margin: 10px 0;">';
    echo '<strong>Search:</strong> ' . htmlspecialchars(substr($endpoint, strpos($endpoint, 'q=') + 2)) . '<br>';

    $response = @file_get_contents($endpoint);
    if ($response !== false) {
        $data = @json_decode($response, true);
        if ($data['success'] && isset($data['result']['results'])) {
            $results = $data['result']['results'];
            echo '<p class="data">✓ Found ' . count($results) . ' datasets</p>';

            foreach (array_slice($results, 0, 2) as $dataset) {
                echo '<code style="color: #74c0fc;">' . htmlspecialchars($dataset['title'] ?? $dataset['name']) . '</code><br>';

                // Look for data resources (actual data files/APIs)
                if (isset($dataset['resources'])) {
                    foreach (array_slice($dataset['resources'], 0, 1) as $resource) {
                        echo '  → ' . htmlspecialchars($resource['name'] ?? 'Resource') . '<br>';
                        if (isset($resource['url'])) {
                            echo '  URL: <code style="color: #74c0fc;">' . htmlspecialchars(substr($resource['url'], 0, 60)) . '...</code><br>';
                        }
                    }
                }
            }

            echo '<div class="response">' . htmlspecialchars(substr($response, 0, 300)) . '</div>';
        } else {
            echo '<p>Response: ' . htmlspecialchars(substr($response, 0, 150)) . '</p>';
        }
    } else {
        echo '<p style="color: #ff6b6b;">✗ Endpoint not accessible</p>';
    }
    echo '</div>';
}

echo '</div>';

// Test 3: Direct WaterInfo API (possible public endpoint)
echo '<div class="test">';
echo '<div class="title">Test 3: Direct RWS WaterInfo Services</div>';

$waterinfo_urls = [
    // Try FEWS OpenData endpoint documented in github
    'https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/timeseries/id',
    'https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/locations',
    'https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/qualifiers',
];

foreach ($waterinfo_urls as $endpoint) {
    echo '<div style="margin: 10px 0;">';
    echo '<code style="color: #74c0fc;">' . htmlspecialchars($endpoint) . '</code><br>';

    $response = @file_get_contents($endpoint . '?documentFormat=json');
    if ($response !== false && strlen($response) > 20) {
        $data = @json_decode($response, true);
        if (is_array($data)) {
            echo '<p class="data">✓ Response received</p>';
            echo '<div class="response">' . htmlspecialchars(substr($response, 0, 400)) . '</div>';
        }
    } else {
        echo '<p style="color: #ff6b6b;">✗ No response</p>';
    }
    echo '</div>';
}

echo '</div>';

// Test 4: Alternative: Search for public water data sources
echo '<div class="test" style="background: #2d3d2d; border-color: #51cf66;">';
echo '<div class="title" style="color: #51cf66;">📋 Research Strategy</div>';

echo '<p><strong>Finding Real RWS Data:</strong></p>';
echo '<ol>';
echo '<li>Check data.rijkswaterstaat.nl for publicly available datasets</li>';
echo '<li>Look for "waterstand" (water level) or "afvoer" (discharge) datasets</li>';
echo '<li>Find exact data URL and test with curl</li>';
echo '<li>Check GitHub: github.com/wstolte/rwsapi for working implementation examples</li>';
echo '<li>Consider contacting RWS directly for API documentation</li>';
echo '</ol>';

echo '<p><strong>Known Public Data Sources:</strong></p>';
echo '<ul>';
echo '<li>data.rijkswaterstaat.nl - CKAN data portal (may have water data)</li>';
echo '<li>water.overheid.nl - Dutch water authority information</li>';
echo '<li>rijkswaterstaatdata.nl - Raw water data portal</li>';
echo '</ul>';

echo '</div>';

echo '</body></html>';
?>
