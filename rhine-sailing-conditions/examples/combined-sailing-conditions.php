<?php
/**
 * Combined Rhine Sailing Conditions dashboard.
 * Shows real-time wind and water data together.
 *
 * UI language defaults to Dutch; set RSC_LANG=fy (etc.) to switch.
 * See lang/i18n.php.
 */

require __DIR__ . '/lang/i18n.php';
require __DIR__ . '/lib/data.php';

// Rhine location
$location = [
    'name' => 'Driel, Boven',
    'lat' => 51.9397,
    'lon' => 5.3897
];

// Fetch current wind (incl. real gusts) from Open-Meteo — cached.
$wind_url = sprintf(
    'https://api.open-meteo.com/v1/forecast?latitude=%f&longitude=%f&current=wind_speed_10m,wind_direction_10m,wind_gusts_10m&timezone=Europe/Amsterdam',
    $location['lat'],
    $location['lon']
);
$wind_data = rsc_fetch_json($wind_url, 'combined_wind', RSC_CACHE_TTL_CURRENT);

// Fetch water data from the RWS DDAPI — cached.
$rws_url = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';
$rws_payload = json_encode([
    'LocatieLijst' => [['Code' => 'driel.boven']],
    'AquoPlusWaarnemingMetadataLijst' => [
        ['AquoMetadata' => ['MessageID' => 1]]
    ]
]);
$rws_data = rsc_fetch_json($rws_url, 'combined_rws', RSC_CACHE_TTL_CURRENT, 'POST', $rws_payload);

// Extract wind data
$wind = null;
if (isset($wind_data['current'])) {
    $speed_kmh = floatval($wind_data['current']['wind_speed_10m']);
    $speed_knots = $speed_kmh * RSC_KMH_TO_KNOTS;
    $direction_deg = intval($wind_data['current']['wind_direction_10m']);

    // Prefer the real measured gust; estimate only if absent.
    $gust_knots = isset($wind_data['current']['wind_gusts_10m'])
        ? floatval($wind_data['current']['wind_gusts_10m']) * RSC_KMH_TO_KNOTS
        : $speed_knots * 1.5;

    $wind = [
        'speed_knots' => round($speed_knots, 1),
        'speed_kmh' => round($speed_kmh, 1),
        'direction' => rsc_degrees_to_direction($direction_deg),
        'direction_deg' => $direction_deg,
        'gust' => round($gust_knots, 1)
    ];
}

// Fetch 6-hour wind + precipitation forecast from Open-Meteo (one request) — cached.
$forecast_url = sprintf(
    'https://api.open-meteo.com/v1/forecast?latitude=%f&longitude=%f&hourly=wind_speed_10m,precipitation,precipitation_probability&forecast_days=1&timezone=Europe/Amsterdam',
    $location['lat'],
    $location['lon']
);
$forecast_data = rsc_fetch_json($forecast_url, 'combined_forecast', RSC_CACHE_TTL_FORECAST);

$forecast = [];
if (isset($forecast_data['hourly']['wind_speed_10m'], $forecast_data['hourly']['precipitation'])) {
    $h = $forecast_data['hourly'];
    $hours = min(6, count($h['wind_speed_10m']));
    for ($i = 0; $i < $hours; $i++) {
        $forecast[] = [
            'hour'          => $i,
            'wind_knots'    => round(floatval($h['wind_speed_10m'][$i]) * RSC_KMH_TO_KNOTS, 1),
            'precipitation' => round(floatval($h['precipitation'][$i] ?? 0), 1),
            'probability'   => isset($h['precipitation_probability'][$i]) ? intval($h['precipitation_probability'][$i]) : null,
        ];
    }
}

// Extract water data
$water = null;
$current_speed = null;
$temperature = null;
if (isset($rws_data['WaarnemingenLijst'])) {
    foreach ($rws_data['WaarnemingenLijst'] as $waarneming) {
        $code = $waarneming['AquoMetadata']['Grootheid']['Code'] ?? '';
        $value = $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] ?? null;
        $time = $waarneming['MetingenLijst'][0]['Tijdstip'] ?? '';
        $method = $waarneming['AquoMetadata']['WaardeBewerkingsMethode']['Code'] ?? '';

        if ($code === 'WATHTE' && $method !== 'GEM24H' && !$water) {
            $water = [
                'cm' => $value,
                'meters' => round($value / 100, 2),
                'time' => $time
            ];
        }

        if ($code === 'STROOMSHD' && !$current_speed) {
            $current_speed = [
                'mps' => round($value, 2),
                'knots' => round($value * RSC_MPS_TO_KNOTS, 2),
                'time' => $time
            ];
        }

        if ($code === 'T' && !$temperature) {
            $temperature = [
                'celsius' => round($value, 1),
                'time' => $time
            ];
        }
    }
}

// Qualitative assessment via the shared RSC_Assessment (same as the plugin).
$conditions = null;
if ($wind && $current_speed && $temperature) {
    $conditions = rsc_get_sailing_conditions($wind['speed_knots'], $current_speed['knots']);
}

$last_update = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Body: Open Sans (as on peterhensen.nl). Headings: the site uses the
         commercial font "Panton"; Montserrat is a free geometric stand-in. -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@1,700;1,800&family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <title><?php echo htmlspecialchars( t( 'Rhine Sailing Conditions' ) . ' - ' . t( 'Live Dashboard' ) ); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Open Sans', Arial, sans-serif;
            background: #eef1f7;
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            color: #222e65;
            margin-bottom: 30px;
            padding: 30px 0;
        }

        h1 {
            font-family: 'Montserrat', 'Open Sans', Arial, sans-serif;
            font-style: italic;
            font-weight: 800;
            font-size: 3em;
            color: #222e65;
            margin-bottom: 10px;
        }

        h1::after {
            content: "";
            display: block;
            width: 80px;
            height: 5px;
            background: #f4d011;
            border-radius: 3px;
            margin: 14px auto 0;
        }

        .location {
            font-size: 1.2em;
            color: #4f5883;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .card h2 {
            font-family: 'Montserrat', 'Open Sans', Arial, sans-serif;
            font-style: italic;
            font-weight: 700;
            color: #222e65;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .metric {
            margin-bottom: 20px;
        }

        .metric-label {
            color: #666;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #222e65;
        }

        .metric-secondary {
            color: #999;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .wind-direction {
            display: inline-block;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #326bff 0%, #222e65 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }

        .recommendations-card {
            background: linear-gradient(135deg, #326bff 0%, #222e65 100%);
            color: white;
            border-radius: 15px;
            padding: 28px 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(34,46,101,0.25);
        }

        .recommendations-card h2 {
            font-family: 'Montserrat', 'Open Sans', Arial, sans-serif;
            font-style: italic;
            font-weight: 800;
            font-size: 1.5em;
            color: white;
            margin-bottom: 14px;
        }

        .reco-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }

        .reco-hint {
            margin-top: 14px;
            opacity: 0.9;
            font-size: 0.9em;
        }

        .recommendation-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid white;
        }

        .recommendation-item strong {
            display: block;
            margin-bottom: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 11px 20px;
            border-radius: 24px;
            font-size: 1.1em;
            font-weight: 700;
            margin: 0;
        }

        .status-good {
            background: #d4edda;
            color: #155724;
        }

        .status-caution {
            background: #fff3cd;
            color: #856404;
        }

        .status-strong {
            background: #f8d7da;
            color: #721c24;
        }

        .footer {
            text-align: center;
            color: #4f5883;
            margin-top: 30px;
            padding: 20px;
        }

        .data-time {
            font-size: 0.85em;
            color: #999;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .refresh-btn {
            background: #326bff;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .refresh-btn:hover {
            background: #222e65;
        }

        .comparison {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 0;
        }

        .comparison-item {
            background: #fff;
            padding: 10px 16px;
            border-radius: 10px;
            border-left: 4px solid #f4d011;
            display: inline-flex;
            align-items: baseline;
            gap: 8px;
        }

        .comparison-label {
            color: #666;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .comparison-value {
            font-size: 1.15em;
            font-weight: bold;
            color: #222e65;
        }

        .forecast-card {
            grid-column: 1 / -1;
        }

        .forecast-grid {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-top: 10px;
        }

        .forecast-cell {
            flex: 1 1 0;
            min-width: 80px;
            text-align: center;
            background: #f9f9f9;
            border-radius: 8px;
            border-top: 3px solid #f4d011;
            padding: 12px 8px;
        }

        .forecast-hour {
            font-weight: bold;
            color: #222e65;
            margin-bottom: 8px;
        }

        .forecast-line {
            font-size: 0.95em;
            color: #333;
            margin: 2px 0;
        }

        .forecast-sub {
            font-size: 0.8em;
            color: #888;
        }

        .forecast-rain {
            color: #326bff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars( t( 'Rhine Sailing Conditions' ) ); ?></h1>
            <div class="location"><?php echo htmlspecialchars($location['name']); ?></div>
            <small style="opacity: 0.7;"><?php echo htmlspecialchars( t( 'Real-time data from Open-Meteo and Rijkswaterstaat' ) ); ?></small>
        </header>

        <?php if (!$wind || !$water || !$current_speed || !$temperature): ?>
            <div class="error">
                <?php echo htmlspecialchars( t( 'Unable to fetch real-time data. Please check your internet connection.' ) ); ?>
            </div>
        <?php else: ?>

        <!-- Sailing recommendation: the eyecatcher, shown first for info at a glance -->
        <div class="recommendations-card">
            <h2><?php echo htmlspecialchars( t( 'Sailing recommendation' ) ); ?></h2>

            <?php $badge_class = ( 'good' === $conditions['status'] ) ? 'status-good' : 'status-caution'; ?>
            <div class="reco-row">
                <span class="status-badge <?php echo $badge_class; ?>">
                    <?php echo htmlspecialchars( t( $conditions['recommendation'] ) ); ?>
                </span>
                <div class="comparison">
                    <div class="comparison-item">
                        <span class="comparison-label"><?php echo htmlspecialchars( t( 'Wind for sailing' ) ); ?></span>
                        <span class="comparison-value"><?php
                            if ($wind['speed_knots'] < 6) { echo htmlspecialchars( t( 'Too weak' ) ); }
                            elseif ($wind['speed_knots'] < 15) { echo htmlspecialchars( t( 'Good' ) ); }
                            else { echo htmlspecialchars( t( 'Too strong' ) ); }
                        ?></span>
                    </div>
                    <div class="comparison-item">
                        <span class="comparison-label"><?php echo htmlspecialchars( t( 'Current speed' ) ); ?></span>
                        <span class="comparison-value"><?php
                            echo ($current_speed['knots'] < 2.5) ? htmlspecialchars( t( 'Safe' ) ) : htmlspecialchars( t( 'Strong' ) );
                        ?></span>
                    </div>
                </div>
            </div>

            <p class="reco-hint"><?php echo htmlspecialchars( t( 'Ideal conditions: 6-15 knots wind + current under 2.5 knots' ) ); ?></p>
        </div>

        <div class="grid">
            <!-- Wind Card -->
            <div class="card">
                <h2><?php echo htmlspecialchars( t( 'Wind conditions' ) ); ?></h2>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Wind speed' ) ); ?></div>
                    <div class="metric-value"><?php echo $wind['speed_knots']; ?> <span style="font-size: 0.6em;">kn</span></div>
                    <div class="metric-secondary"><?php echo htmlspecialchars( t( 'Wind force' ) ); ?> <?php echo rsc_knots_to_beaufort($wind['speed_knots']); ?> Bft</div>
                </div>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Direction' ) ); ?></div>
                    <div class="wind-direction"><?php echo htmlspecialchars($wind['direction']); ?></div>
                    <div class="metric-secondary"><?php echo $wind['direction_deg']; ?>° <?php echo htmlspecialchars( t( 'relative to North' ) ); ?></div>
                </div>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Wind gusts' ) ); ?></div>
                    <div class="metric-value"><?php echo $wind['gust']; ?> <span style="font-size: 0.6em;">kn</span></div>
                </div>

                <div class="data-time">
                    <?php echo htmlspecialchars( t( 'Source: Open-Meteo API' ) ); ?>
                </div>
            </div>

            <!-- Water Card -->
            <div class="card">
                <h2><?php echo htmlspecialchars( t( 'Water conditions' ) ); ?></h2>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Water level' ) ); ?></div>
                    <div class="metric-value"><?php echo $water['meters']; ?> <span style="font-size: 0.6em;">m NAP</span></div>
                    <div class="metric-secondary"><?php echo $water['cm']; ?> cm</div>
                </div>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Current speed' ) ); ?></div>
                    <div class="metric-value"><?php echo $current_speed['knots']; ?> <span style="font-size: 0.6em;">kn</span></div>
                    <div class="metric-secondary"><?php echo $current_speed['mps']; ?> m/s</div>
                </div>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Water temperature' ) ); ?></div>
                    <div class="metric-value"><?php echo $temperature['celsius']; ?> <span style="font-size: 0.6em;">°C</span></div>
                </div>

                <div class="data-time">
                    <?php echo htmlspecialchars( t( 'Source: Rijkswaterstaat DDAPI' ) ); ?><br>
                    <?php echo htmlspecialchars( t( 'Location' ) ); ?>: driel.boven
                </div>
            </div>

            <!-- Summary Card -->
            <div class="card">
                <h2><?php echo htmlspecialchars( t( 'Current assessment' ) ); ?></h2>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Wind level' ) ); ?></div>
                    <div class="metric-value" style="font-size: 1.6em;">
                        <?php echo htmlspecialchars( t( $conditions['wind']['level'] ) ); ?>
                    </div>
                    <div class="metric-secondary"><?php echo htmlspecialchars( t( $conditions['wind']['description'] ) ); ?></div>
                </div>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Water current' ) ); ?></div>
                    <div class="metric-value" style="font-size: 1.6em;">
                        <?php echo htmlspecialchars( t( $conditions['water']['level'] ) ); ?>
                    </div>
                    <div class="metric-secondary"><?php echo htmlspecialchars( t( $conditions['water']['description'] ) ); ?></div>
                </div>

                <div class="data-time">
                    <?php echo htmlspecialchars( t( 'Last checked' ) ); ?>: <?php echo $last_update; ?>
                </div>
            </div>

            <!-- Forecast Card -->
            <?php if (!empty($forecast)): ?>
            <div class="card forecast-card">
                <h2><?php echo htmlspecialchars( t( 'Forecast (next 6 hours)' ) ); ?></h2>
                <div class="forecast-grid">
                    <?php foreach ($forecast as $f): ?>
                    <div class="forecast-cell">
                        <div class="forecast-hour">+<?php echo (int)$f['hour']; ?>u</div>
                        <div class="forecast-line"><?php echo $f['wind_knots']; ?> kn</div>
                        <div class="forecast-sub"><?php echo rsc_knots_to_beaufort($f['wind_knots']); ?> Bft</div>
                        <div class="forecast-line forecast-rain"><?php echo $f['precipitation']; ?> mm</div>
                        <?php if ($f['probability'] !== null): ?>
                        <div class="forecast-sub"><?php echo (int)$f['probability']; ?>%</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <button class="refresh-btn" onclick="location.reload()"><?php echo htmlspecialchars( t( 'Refresh now' ) ); ?></button>
        </div>

        <footer class="footer">
            <p><strong><?php echo htmlspecialchars( t( 'Data sources:' ) ); ?></strong> Open-Meteo (wind) + Rijkswaterstaat DDAPI (water)</p>
            <p><?php echo htmlspecialchars( t( 'This is a live dashboard showing actual conditions at Driel boven on the Rhine' ) ); ?></p>
            <p style="margin-top: 10px; opacity: 0.6;"><?php echo htmlspecialchars( t( 'Updated every 30 seconds on source servers • Page can refresh automatically' ) ); ?></p>
        </footer>
    </div>
</body>
</html>
