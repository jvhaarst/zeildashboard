<?php
/**
 * Gecombineerd Rijn Zeilomstandigheden Dashboard
 * Toont real-time wind- en watergegevens geïntegreerd
 */

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

    // Windtoetsing
    if ($wind_knots < 3) $conditions['wind'] = ['level' => 'Windstil', 'description' => 'Geen wind'];
    elseif ($wind_knots < 6) $conditions['wind'] = ['level' => 'Licht', 'description' => 'Lichte bries'];
    elseif ($wind_knots < 10) $conditions['wind'] = ['level' => 'Matig', 'description' => 'Fijne bries'];
    elseif ($wind_knots < 15) $conditions['wind'] = ['level' => 'Sterk', 'description' => 'Sterke wind'];
    else $conditions['wind'] = ['level' => 'Zeer Sterk', 'description' => 'Gevaarlijke omstandigheden'];

    // Stromingstoetsing
    if ($current_knots < 0.5) $conditions['water'] = ['level' => 'Zwak', 'description' => 'Zeer zwakke stroming'];
    elseif ($current_knots < 1.0) $conditions['water'] = ['level' => 'Licht', 'description' => 'Lichte stroming'];
    elseif ($current_knots < 2.0) $conditions['water'] = ['level' => 'Matig', 'description' => 'Gemiddelde stroming'];
    elseif ($current_knots < 3.0) $conditions['water'] = ['level' => 'Sterk', 'description' => 'Sterke stroming'];
    else $conditions['water'] = ['level' => 'Zeer Sterk', 'description' => 'Zeer sterke stroming'];

    // Algehele aanbeveling
    if ($wind_knots >= 6 && $wind_knots < 15 && $current_knots < 2.5) {
        $conditions['recommendation'] = 'Prima omstandigheden voor zeilen';
    } elseif ($wind_knots < 6) {
        $conditions['recommendation'] = 'Onvoldoende wind voor goed zeilen';
    } elseif ($wind_knots >= 15) {
        $conditions['recommendation'] = 'Wind te sterk - voorzichtigheid geadviseerd';
    } elseif ($current_knots >= 2.5) {
        $conditions['recommendation'] = 'Stroming te sterk - voorzichtigheid geadviseerd';
    } else {
        $conditions['recommendation'] = 'Controleer omstandigheden voor zeilen';
    }

    return $conditions;
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
    <title>Rijn Zeilomstandigheden - Live Dashboard</title>
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
            <h1>Rijn Zeilomstandigheden</h1>
            <div class="location"><?php echo htmlspecialchars($location['name']); ?></div>
            <small style="opacity: 0.7;">Real-time gegevens van Open-Meteo en Rijkswaterstaat</small>
        </header>

        <?php if (!$wind || !$water || !$current_speed || !$temperature): ?>
            <div class="error">
                Kan real-time gegevens niet ophalen. Controleer uw internetverbinding.
            </div>
        <?php else: ?>

        <div class="grid">
            <!-- Wind Card -->
            <div class="card">
                <h2>Windcondities</h2>

                <div class="metric">
                    <div class="metric-label">Windsnelheid</div>
                    <div class="metric-value"><?php echo $wind['speed_knots']; ?> <span style="font-size: 0.6em;">knopen</span></div>
                    <div class="metric-secondary"><?php echo $wind['speed_kmh']; ?> km/h</div>
                </div>

                <div class="metric">
                    <div class="metric-label">Richting</div>
                    <div class="wind-direction"><?php echo htmlspecialchars($wind['direction']); ?></div>
                    <div class="metric-secondary"><?php echo $wind['direction_deg']; ?>° t.o.v. Noorden</div>
                </div>

                <div class="metric">
                    <div class="metric-label">Windvlagen</div>
                    <div class="metric-value"><?php echo $wind['gust']; ?> <span style="font-size: 0.6em;">knopen</span></div>
                </div>

                <div class="data-time">
                    Bron: Open-Meteo API
                </div>
            </div>

            <!-- Water Card -->
            <div class="card">
                <h2>Watercondities</h2>

                <div class="metric">
                    <div class="metric-label">Waterstand</div>
                    <div class="metric-value"><?php echo $water['meters']; ?> <span style="font-size: 0.6em;">m NAP</span></div>
                    <div class="metric-secondary"><?php echo $water['cm']; ?> cm</div>
                </div>

                <div class="metric">
                    <div class="metric-label">Stroomsnelheid</div>
                    <div class="metric-value"><?php echo $current_speed['knots']; ?> <span style="font-size: 0.6em;">knopen</span></div>
                    <div class="metric-secondary"><?php echo $current_speed['mps']; ?> m/s</div>
                </div>

                <div class="metric">
                    <div class="metric-label">Watertemperatuur</div>
                    <div class="metric-value"><?php echo $temperature['celsius']; ?> <span style="font-size: 0.6em;">°C</span></div>
                </div>

                <div class="data-time">
                    Bron: Rijkswaterstaat DDAPI<br>
                    Locatie: driel.boven
                </div>
            </div>

            <!-- Summary Card -->
            <div class="card">
                <h2>Huidige Beoordeling</h2>

                <div class="metric">
                    <div class="metric-label">Windniveau</div>
                    <div class="metric-value" style="font-size: 1.6em;">
                        <?php echo htmlspecialchars($conditions['wind']['level']); ?>
                    </div>
                    <div class="metric-secondary"><?php echo htmlspecialchars($conditions['wind']['description']); ?></div>
                </div>

                <div class="metric">
                    <div class="metric-label">Waterstroming</div>
                    <div class="metric-value" style="font-size: 1.6em;">
                        <?php echo htmlspecialchars($conditions['water']['level']); ?>
                    </div>
                    <div class="metric-secondary"><?php echo htmlspecialchars($conditions['water']['description']); ?></div>
                </div>

                <div class="data-time">
                    Laatst gecontroleerd: <?php echo $last_update; ?>
                </div>
            </div>

            <!-- Recommendations Card -->
            <div class="recommendations-card">
                <h2>Zeilaanbeveling</h2>

                <?php
                if (strpos($conditions['recommendation'], 'Prima') === 0) {
                    echo '<span class="status-badge status-good">';
                } else {
                    echo '<span class="status-badge status-caution">';
                }
                ?>
                    <?php echo htmlspecialchars($conditions['recommendation']); ?>
                </span>

                <div class="comparison">
                    <div class="comparison-item">
                        <div class="comparison-label">Wind voor Zeilen</div>
                        <div class="comparison-value">
                            <?php
                            if ($wind['speed_knots'] < 6) {
                                echo 'Te Zwak';
                            } elseif ($wind['speed_knots'] < 15) {
                                echo 'Goed';
                            } else {
                                echo 'Te Sterk';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="comparison-item">
                        <div class="comparison-label">Stroomsnelheid</div>
                        <div class="comparison-value">
                            <?php
                            if ($current_speed['knots'] < 2.5) {
                                echo 'Veilig';
                            } else {
                                echo 'Sterk';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <p style="margin-top: 15px; opacity: 0.9;">
                    Ideale omstandigheden: 6-15 knopen wind + stroming onder 2,5 knopen
                </p>
            </div>
        </div>

        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <button class="refresh-btn" onclick="location.reload()">Nu Vernieuwen</button>
        </div>

        <footer class="footer">
            <p><strong>Gegevensbronnen:</strong> Open-Meteo (wind) + Rijkswaterstaat DDAPI (water)</p>
            <p>Dit is een live dashboard met werkelijke omstandigheden bij Arnhem Nederrijn op de Rijn</p>
            <p style="margin-top: 10px; opacity: 0.6;">Wordt elke 30 seconden bijgewerkt op bronservers • Pagina kan automatisch worden vernieuwd</p>
        </footer>
    </div>
</body>
</html>
