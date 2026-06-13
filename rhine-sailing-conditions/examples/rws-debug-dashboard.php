<?php
/**
 * RWS Water Data Debug Dashboard
 * Shows real Rhine water measurements from Rijkswaterstaat API.
 *
 * UI language defaults to Dutch; set RSC_LANG=fy (etc.) to switch.
 * See lang/i18n.php.
 */

require __DIR__ . '/lang/i18n.php';
require __DIR__ . '/lib/data.php';

// Fetch latest data from RWS DDAPI (cURL + on-disk cache, resilient to outages).
$url = 'https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen';

$payload = json_encode([
    'LocatieLijst' => [['Code' => 'arnhem.nederrijn']],
    'AquoPlusWaarnemingMetadataLijst' => [
        ['AquoMetadata' => ['MessageID' => 1]]
    ]
]);

$data = rsc_fetch_json($url, 'debug_rws_arnhem', RSC_CACHE_TTL_CURRENT, 'POST', $payload);

// Extract measurements
$measurements = [];
$waterhoogte = null;
$debiet = null;

if (isset($data['WaarnemingenLijst'])) {
    foreach ($data['WaarnemingenLijst'] as $waarneming) {
        $code = $waarneming['AquoMetadata']['Grootheid']['Code'] ?? '';
        $value = $waarneming['MetingenLijst'][0]['Meetwaarde']['Waarde_Numeriek'] ?? null;
        $time = $waarneming['MetingenLijst'][0]['Tijdstip'] ?? '';
        $method = $waarneming['AquoMetadata']['WaardeBewerkingsMethode']['Code'] ?? 'NVT';

        if ($code === 'WATHTE' && $method !== 'GEM24H' && $waterhoogte === null) {
            $waterhoogte = [
                'value' => $value,
                'time' => $time,
                'cm' => $value,
                'meters' => round($value / 100, 2)
            ];
        }

        if ($code === 'Q' && $method !== 'GEM24H' && $debiet === null) {
            $debiet = [
                'value' => $value,
                'time' => $time,
                'method' => $method
            ];
        }

        $measurements[] = [
            'code' => $code,
            'value' => $value,
            'method' => $method,
            'time' => $time
        ];
    }
}

// Format timestamp
$last_update = date('Y-m-d H:i:s');
if ($waterhoogte && $waterhoogte['time']) {
    $last_update = substr($waterhoogte['time'], 0, 19);
    $last_update = str_replace('T', ' ', $last_update);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Body: Open Sans. Headings: site uses commercial "Panton"; Montserrat
         is a free geometric stand-in. -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@1,700;1,800&family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <title><?php echo htmlspecialchars( t( 'Rhine Water Conditions' ) ); ?></title>
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
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 100%;
            padding: 40px;
        }

        header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #326bff;
            padding-bottom: 20px;
        }

        h1 {
            font-family: 'Montserrat', 'Open Sans', Arial, sans-serif;
            font-style: italic;
            font-weight: 800;
            color: #222e65;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .location {
            color: #666;
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .coords {
            color: #999;
            font-size: 0.9em;
            font-family: monospace;
        }

        .last-update {
            color: #326bff;
            font-size: 0.95em;
            margin-top: 15px;
        }

        .metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: linear-gradient(135deg, #326bff 0%, #222e65 100%);
            border-radius: 12px;
            padding: 30px;
            color: white;
            text-align: center;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .metric-label {
            font-size: 0.95em;
            opacity: 0.9;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .metric-value {
            font-size: 2.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .metric-unit {
            font-size: 1.1em;
            opacity: 0.8;
        }

        .metric-secondary {
            font-size: 0.9em;
            opacity: 0.85;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table thead {
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }

        table th {
            padding: 15px;
            text-align: left;
            color: #333;
            font-weight: 600;
            font-size: 0.95em;
        }

        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            color: #555;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .code {
            font-family: monospace;
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-current {
            background: #d4edda;
            color: #155724;
        }

        .status-avg {
            background: #fff3cd;
            color: #856404;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #999;
            font-size: 0.9em;
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

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars( t( 'Rhine Water Conditions' ) ); ?></h1>
            <div class="location">Arnhem, Nederrijn</div>
            <div class="coords">51.975408°N, 5.912023°E</div>
            <div class="last-update"><?php echo htmlspecialchars( t( 'Last updated' ) ); ?>: <strong><?php echo htmlspecialchars($last_update); ?></strong> (CET)</div>
        </header>

        <?php if (!$waterhoogte || !$debiet): ?>
            <div class="error">
                <?php echo htmlspecialchars( t( 'Unable to fetch real-time data from RWS API. Check your internet connection or API availability.' ) ); ?>
            </div>
        <?php else: ?>

        <section class="metrics">
            <div class="metric-card">
                <div class="metric-label"><?php echo htmlspecialchars( t( 'Water Height' ) ); ?></div>
                <div class="metric-value"><?php echo number_format($waterhoogte['meters'], 2); ?></div>
                <div class="metric-unit"><?php echo htmlspecialchars( t( 'meters (NAP)' ) ); ?></div>
                <div class="metric-secondary">
                    <?php echo number_format($waterhoogte['cm'], 0); ?> cm
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-label"><?php echo htmlspecialchars( t( 'Discharge Rate' ) ); ?></div>
                <div class="metric-value"><?php echo number_format($debiet['value'], 1); ?></div>
                <div class="metric-unit">m³/s</div>
                <div class="metric-secondary">
                    <?php echo htmlspecialchars($debiet['method']); ?> <?php echo htmlspecialchars( t( 'measurement' ) ); ?>
                </div>
            </div>
        </section>

        <section>
            <h2 style="color: #333; margin-bottom: 20px; font-size: 1.3em;"><?php echo htmlspecialchars( t( 'All Measurements' ) ); ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?php echo htmlspecialchars( t( 'Type' ) ); ?></th>
                        <th><?php echo htmlspecialchars( t( 'Value' ) ); ?></th>
                        <th><?php echo htmlspecialchars( t( 'Method' ) ); ?></th>
                        <th><?php echo htmlspecialchars( t( 'Timestamp' ) ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($measurements as $m): ?>
                    <tr>
                        <td>
                            <span class="code"><?php echo htmlspecialchars($m['code']); ?></span>
                        </td>
                        <td>
                            <strong><?php echo number_format($m['value'], 2); ?></strong>
                            <?php if ($m['code'] === 'WATHTE'): ?>
                                cm / <?php echo number_format($m['value']/100, 2); ?> m
                            <?php elseif ($m['code'] === 'Q'): ?>
                                m³/s
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo ($m['method'] === 'GEM24H') ? 'status-avg' : 'status-current'; ?>">
                                <?php echo htmlspecialchars($m['method']); ?>
                            </span>
                        </td>
                        <td style="font-family: monospace; font-size: 0.9em;">
                            <?php echo htmlspecialchars(substr($m['time'], 0, 19)); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php endif; ?>

        <div style="text-align: center;">
            <button class="refresh-btn" onclick="location.reload()"><?php echo htmlspecialchars( t( 'Refresh Data' ) ); ?></button>
        </div>

        <footer class="footer">
            <p><?php echo htmlspecialchars( t( 'Data source:' ) ); ?> <strong>Rijkswaterstaat DDAPI</strong> <?php echo htmlspecialchars( t( '(Dutch Ministry of Infrastructure)' ) ); ?></p>
            <p><?php echo htmlspecialchars( t( 'Updated every 30 seconds on RWS servers • This dashboard refreshes on page load' ) ); ?></p>
        </footer>
    </div>
</body>
</html>
