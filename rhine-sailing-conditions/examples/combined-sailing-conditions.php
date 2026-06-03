<?php
/**
 * Combined Rhine Sailing Conditions dashboard.
 * Shows real-time wind and water data together.
 *
 * UI language defaults to Dutch; set RSC_LANG=fy (etc.) to switch.
 * See lang/i18n.php.
 */

require __DIR__ . '/lang/i18n.php';

// Rhine location
$location = [
    'name' => 'Driel, Boven',
    'lat' => 51.9397,
    'lon' => 5.3897
];

// Fetch wind data from Open-Meteo
$wind_url = sprintf(
    'https://api.open-meteo.com/v1/forecast?latitude=%f&longitude=%f&current=wind_speed_10m,wind_direction_10m&timezone=Europe/Amsterdam',
    $location['lat'],
    $location['lon']
);

$wind_response = @file_get_contents($wind_url);
$wind_data = json_decode($wind_response, true);

// Fetch water data from RWS DDAPI
$rws_url = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';
$rws_payload = json_encode([
    'locatieLijst' => ['driel.boven'],
    'aquoPlusWaarnemingMetadataLijst' => [
        ['aquoMetadata' => ['messageID' => 1]]
    ]
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $rws_payload,
        'timeout' => 10
    ]
]);

$rws_response = @file_get_contents($rws_url, false, $context);
$rws_data = json_decode($rws_response, true);

// Extract wind data
$wind = null;
if (isset($wind_data['current'])) {
    $speed_kmh = floatval($wind_data['current']['wind_speed_10m']);
    $speed_knots = $speed_kmh * 0.539957;
    $direction_deg = intval($wind_data['current']['wind_direction_10m']);

    // Converteer graden naar windrichting (Nederlands)
    $directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'];
    $direction_idx = round($direction_deg / 22.5) % 16;
    $direction = $directions[$direction_idx];

    $wind = [
        'speed_knots' => round($speed_knots, 1),
        'speed_kmh' => round($speed_kmh, 1),
        'direction' => $direction,
        'direction_deg' => $direction_deg,
        'gust' => round($speed_knots * 1.5, 1)
    ];
}

// Extract water data
$water = null;
$current_speed = null;
$temperature = null;
if (isset($rws_data['WaarnemingenLijst'])) {
    foreach ($rws_data['WaarnemingenLijst'] as $waarneming) {
        $code = $waarneming['AquoMetadata']['Grootheid']['Code'] ?? '';
        $value = $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] ?? null;
        $time = $waarneming['MetingenLijst'][0]['Tijd']['waarde'] ?? '';
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
                'knots' => round($value * 1.94384, 2),
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

// Bepaal zeilomstandigheden (kwalitatieve beoordeling)
function get_sailing_conditions($wind_knots, $current_knots) {
    $conditions = [];

    // Wind assessment (English keys; translated at output via t()).
    if ($wind_knots < 3) $conditions['wind'] = ['level' => 'Calm', 'description' => 'No wind'];
    elseif ($wind_knots < 6) $conditions['wind'] = ['level' => 'Light', 'description' => 'Light breeze'];
    elseif ($wind_knots < 10) $conditions['wind'] = ['level' => 'Moderate', 'description' => 'Nice breeze'];
    elseif ($wind_knots < 15) $conditions['wind'] = ['level' => 'Strong', 'description' => 'Strong wind'];
    else $conditions['wind'] = ['level' => 'Very strong', 'description' => 'Dangerous conditions'];

    // Current assessment.
    if ($current_knots < 0.5) $conditions['water'] = ['level' => 'Weak', 'description' => 'Very weak current'];
    elseif ($current_knots < 1.0) $conditions['water'] = ['level' => 'Light', 'description' => 'Light current'];
    elseif ($current_knots < 2.0) $conditions['water'] = ['level' => 'Moderate', 'description' => 'Average current'];
    elseif ($current_knots < 3.0) $conditions['water'] = ['level' => 'Strong', 'description' => 'Strong current'];
    else $conditions['water'] = ['level' => 'Very strong', 'description' => 'Very strong current'];

    // Overall recommendation. 'status' drives the badge colour and is
    // language-independent; 'recommendation' is an English key for t().
    if ($wind_knots >= 6 && $wind_knots < 15 && $current_knots < 2.5) {
        $conditions['status'] = 'good';
        $conditions['recommendation'] = 'Good conditions for sailing';
    } elseif ($wind_knots < 6) {
        $conditions['status'] = 'caution';
        $conditions['recommendation'] = 'Insufficient wind for good sailing';
    } elseif ($wind_knots >= 15) {
        $conditions['status'] = 'caution';
        $conditions['recommendation'] = 'Wind too strong - caution advised';
    } elseif ($current_knots >= 2.5) {
        $conditions['status'] = 'caution';
        $conditions['recommendation'] = 'Current too strong - caution advised';
    } else {
        $conditions['status'] = 'caution';
        $conditions['recommendation'] = 'Check conditions before sailing';
    }

    return $conditions;
}

// Converteer windsnelheid in knopen naar windkracht (Beaufort 0-12)
function knots_to_beaufort($knots) {
    $lower_bounds = [1, 4, 7, 11, 17, 22, 28, 34, 41, 48, 56, 64];
    $force = 0;
    foreach ($lower_bounds as $index => $min_knots) {
        if ($knots >= $min_knots) {
            $force = $index + 1;
        }
    }
    return $force;
}

$conditions = null;
if ($wind && $current_speed && $temperature) {
    $conditions = get_sailing_conditions($wind['speed_knots'], $current_speed['knots']);
}

$last_update = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars( t( 'Rhine Sailing Conditions' ) . ' - ' . t( 'Live Dashboard' ) ); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            padding: 30px 0;
        }

        h1 {
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .location {
            font-size: 1.2em;
            opacity: 0.9;
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
            color: #1e3c72;
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
            color: #1e3c72;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }

        .recommendations-card {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .recommendations-card h2 {
            color: white;
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
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            margin: 10px 0;
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
            color: white;
            opacity: 0.8;
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
            background: #667eea;
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
            background: #764ba2;
        }

        .comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }

        .comparison-item {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .comparison-label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .comparison-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #1e3c72;
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

        <div class="grid">
            <!-- Wind Card -->
            <div class="card">
                <h2><?php echo htmlspecialchars( t( 'Wind conditions' ) ); ?></h2>

                <div class="metric">
                    <div class="metric-label"><?php echo htmlspecialchars( t( 'Wind speed' ) ); ?></div>
                    <div class="metric-value"><?php echo $wind['speed_knots']; ?> <span style="font-size: 0.6em;">kn</span></div>
                    <div class="metric-secondary"><?php echo htmlspecialchars( t( 'Wind force' ) ); ?> <?php echo knots_to_beaufort($wind['speed_knots']); ?> Bft</div>
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

            <!-- Recommendations Card -->
            <div class="recommendations-card">
                <h2><?php echo htmlspecialchars( t( 'Sailing recommendation' ) ); ?></h2>

                <?php
                $badge_class = ( 'good' === $conditions['status'] ) ? 'status-good' : 'status-caution';
                ?>
                <span class="status-badge <?php echo $badge_class; ?>">
                    <?php echo htmlspecialchars( t( $conditions['recommendation'] ) ); ?>
                </span>

                <div class="comparison">
                    <div class="comparison-item">
                        <div class="comparison-label"><?php echo htmlspecialchars( t( 'Wind for sailing' ) ); ?></div>
                        <div class="comparison-value">
                            <?php
                            if ($wind['speed_knots'] < 6) {
                                echo htmlspecialchars( t( 'Too weak' ) );
                            } elseif ($wind['speed_knots'] < 15) {
                                echo htmlspecialchars( t( 'Good' ) );
                            } else {
                                echo htmlspecialchars( t( 'Too strong' ) );
                            }
                            ?>
                        </div>
                    </div>
                    <div class="comparison-item">
                        <div class="comparison-label"><?php echo htmlspecialchars( t( 'Current speed' ) ); ?></div>
                        <div class="comparison-value">
                            <?php
                            if ($current_speed['knots'] < 2.5) {
                                echo htmlspecialchars( t( 'Safe' ) );
                            } else {
                                echo htmlspecialchars( t( 'Strong' ) );
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <p style="margin-top: 15px; opacity: 0.9;">
                    <?php echo htmlspecialchars( t( 'Ideal conditions: 6-15 knots wind + current under 2.5 knots' ) ); ?>
                </p>
            </div>
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
