<?php
/**
 * Test RWS API using proper HTTP headers and cURL
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>RWS API with cURL</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #0dff00; padding: 20px; line-height: 1.6; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #444; border-radius: 4px; }
        .success { background: #1a3a1a; border-color: #51cf66; }
        .title { color: #fff; margin-bottom: 5px; font-weight: bold; }
        .response { background: #2d2d2d; padding: 10px; margin: 10px 0; border-left: 3px solid #666; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <h1>RWS API Testing with cURL</h1>
";

if (!function_exists('curl_init')) {
    echo '<p style="color: #ff6b6b;">cURL not available on this PHP installation</p>';
    echo '</body></html>';
    exit;
}

// Test 1: RWS Metadata Service with cURL
echo '<div class="test">';
echo '<div class="title">Test 1: RWS Metadata Service (cURL)</div>';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/OphalenCatalogus');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response !== false && $http_code == 200) {
    echo '<p style="color: #51cf66;">✓ Response received (HTTP ' . $http_code . ')</p>';
    $data = json_decode($response, true);
    if (is_array($data)) {
        echo '<p>Valid JSON response</p>';
        echo '<div class="response">' . htmlspecialchars(substr($response, 0, 800)) . '</div>';
        if (isset($data['LocatieLijst']) && is_array($data['LocatieLijst'])) {
            echo '<p style="color: #51cf66;">✓ Found ' . count($data['LocatieLijst']) . ' locations!</p>';
            echo '<p>Sample locations:</p>';
            $count = 0;
            foreach ($data['LocatieLijst'] as $loc) {
                if ($count < 5 && isset($loc['Code'])) {
                    echo '<code style="color: #74c0fc;">' . htmlspecialchars($loc['Code']) . ' - ' . htmlspecialchars($loc['Naam'] ?? 'N/A') . '</code><br>';
                    $count++;
                }
            }
        }
    }
} else {
    echo '<p style="color: #ff6b6b;">✗ Failed (HTTP ' . $http_code . '): ' . htmlspecialchars($error) . '</p>';
}

echo '</div>';

// Test 2: RWS Latest Observations with cURL
if ($http_code == 200 && isset($data['LocatieLijst'])) {
    echo '<div class="test">';
    echo '<div class="title">Test 2: RWS Latest Observations for Arnhem</div>';

    // Find Arnhem code
    $arnhem_code = null;
    foreach ($data['LocatieLijst'] as $loc) {
        if (stripos($loc['Naam'] ?? '', 'Arnhem') !== false) {
            $arnhem_code = $loc['Code'];
            break;
        }
    }

    if ($arnhem_code) {
        echo '<p>Found Arnhem station: <code style="color: #74c0fc;">' . htmlspecialchars($arnhem_code) . '</code></p>';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('Locatie' => $arnhem_code)));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $http_code == 200) {
            echo '<p style="color: #51cf66;">✓ Response received (HTTP ' . $http_code . ')</p>';
            $obs_data = json_decode($response, true);
            if (is_array($obs_data) && isset($obs_data['Waarnemingen'])) {
                echo '<p style="color: #51cf66;">✓ Found ' . count($obs_data['Waarnemingen']) . ' observations!</p>';
                echo '<div class="response">' . htmlspecialchars(substr($response, 0, 600)) . '</div>';
                echo '<p>Need to parse water level and discharge from observations</p>';
            }
        } else {
            echo '<p style="color: #ff6b6b;">✗ Failed (HTTP ' . $http_code . '): ' . htmlspecialchars($error) . '</p>';
        }
    } else {
        echo '<p style="color: #ff6b6b;">✗ Could not find Arnhem in location list</p>';
    }

    echo '</div>';
}

echo '<div class="test" style="background: #2d3d2d; border-color: #51cf66;">';
echo '<div class="title" style="color: #51cf66;">📋 Next Steps</div>';
echo '<p>If tests above succeeded:</p>';
echo '<ol>';
echo '<li>Identify which Waarnemingen parameter contains water level</li>';
echo '<li>Identify which Waarnemingen parameter contains discharge/current</li>';
echo '<li>Implement in RSC_Fetcher::fetch_rijkswaterstaat_water_level()</li>';
echo '<li>Implement in RSC_Fetcher::fetch_rijkswaterstaat_current()</li>';
echo '</ol>';
echo '</div>';

echo '</body></html>';
?>
