<?php
/**
 * Rhine Sailing Conditions - Rijkswaterstaat API Research
 * Tests RWS endpoints to find working water level/current data
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>RWS API Research</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #0dff00; padding: 20px; line-height: 1.6; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #444; border-radius: 4px; }
        .success { background: #1a3a1a; border-color: #51cf66; }
        .error { background: #3a1a1a; border-color: #ff6b6b; }
        .title { color: #fff; margin-bottom: 5px; font-weight: bold; }
        .response { background: #2d2d2d; padding: 10px; margin: 10px 0; border-left: 3px solid #666; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; }
        .endpoint { color: #74c0fc; margin: 5px 0; }
    </style>
</head>
<body>
    <h1>🌊 Rijkswaterstaat API Research</h1>
    <p>Testing RWS endpoints for Rhine water level and current data</p>
";

// Test 1: RWS Metadata Service
echo '<div class="test">';
echo '<div class="title">Test 1: RWS Metadata Service (Catalog)</div>';
echo '<div class="endpoint">POST https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/OphalenCatalogus</div>';

$url = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/OphalenCatalogus';
$context = stream_context_create(array(
    'http' => array(
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => '{}',
        'timeout' => 10,
    )
));

$response = @file_get_contents($url, false, $context);
if ($response !== false) {
    echo '<p style="color: #51cf66;">✓ Response received</p>';
    $data = json_decode($response, true);
    if (is_array($data)) {
        echo '<p>Valid JSON response</p>';
        echo '<div class="response">' . htmlspecialchars(substr($response, 0, 800)) . '...</div>';
        if (isset($data['LocatieLijst'])) {
            echo '<p style="color: #51cf66;">✓ Contains LocatieLijst (locations)</p>';
        }
    }
} else {
    echo '<p style="color: #ff6b6b;">✗ Failed to fetch</p>';
}

echo '</div>';

// Test 2: RWS Observations Service with different station codes
echo '<div class="test">';
echo '<div class="title">Test 2: RWS Latest Observations (Water Level)</div>';
echo '<div class="endpoint">POST https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen</div>';

$stations = array(
    'ARNM' => 'Arnhem (short code)',
    'ARNHEM' => 'Arnhem (full name)',
    'DRIEL' => 'Driel',
    'LOBITH' => 'Lobith (German border)',
    'PANNENERDEN' => 'Pannerden',
);

foreach ($stations as $code => $label) {
    echo '<div style="margin: 10px 0; padding: 10px; background: #2d2d2d; border-left: 2px solid #666;">';
    echo '<strong>Station: ' . htmlspecialchars($code) . ' (' . htmlspecialchars($label) . ')</strong><br>';

    $url = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';
    $payload = json_encode(array('Locatie' => $code));

    $context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $payload,
            'timeout' => 10,
        )
    ));

    $response = @file_get_contents($url, false, $context);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (is_array($data) && isset($data['Waarnemingen']) && !empty($data['Waarnemingen'])) {
            echo '<p style="color: #51cf66;">✓ Data available</p>';
            echo '<div class="response">' . htmlspecialchars(substr($response, 0, 500)) . '...</div>';
        } else if (is_array($data)) {
            echo '<p style="color: #74c0fc;">Got response but no observations</p>';
        } else {
            echo '<p style="color: #ff6b6b;">Invalid JSON response</p>';
        }
    } else {
        echo '<p style="color: #ff6b6b;">✗ Failed to fetch</p>';
    }

    echo '</div>';
}

echo '</div>';

// Test 3: OpenData API alternative
echo '<div class="test">';
echo '<div class="title">Test 3: RWS OpenData Portal (Alternative)</div>';
echo '<div class="endpoint">https://rijkswaterstaatdata.nl/waterdata/</div>';

$url = 'https://rijkswaterstaatdata.nl/waterdata/';
$context = stream_context_create(array(
    'http' => array('timeout' => 10)
));

$response = @file_get_contents($url, false, $context);
if ($response !== false) {
    echo '<p style="color: #51cf66;">✓ Portal accessible</p>';
    // Look for API references
    if (preg_match_all('/https:\/\/[^\s"<>]+api[^\s"<>]*/', $response, $matches)) {
        echo '<p>Found API references:</p>';
        echo '<ul>';
        foreach (array_unique($matches[0]) as $api) {
            echo '<li><code style="color: #74c0fc;">' . htmlspecialchars($api) . '</code></li>';
        }
        echo '</ul>';
    }
} else {
    echo '<p style="color: #ff6b6b;">✗ Portal not accessible</p>';
}

echo '</div>';

echo '<div class="test" style="background: #2d3d2d; border-color: #51cf66;">';
echo '<div class="title" style="color: #51cf66;">📋 Findings & Recommendations</div>';
echo '<p>Based on the API research above:</p>';
echo '<ol>';
echo '<li><strong>Metadata Service</strong> - Use to get list of all stations and measurement types</li>';
echo '<li><strong>Observations Service</strong> - Use to get latest water level and current observations</li>';
echo '<li><strong>Required:</strong> Find correct station code for Arnhem location (ARNM is likely)</li>';
echo '<li><strong>Response Format:</strong> JSON with Waarnemingen (observations) array containing measurement data</li>';
echo '<li><strong>Data Fields:</strong> Need to identify which fields contain water level (m) and current (m³/s)</li>';
echo '</ol>';
echo '</div>';

echo '</body></html>';
?>
