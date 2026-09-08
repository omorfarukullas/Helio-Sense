<?php
require_once __DIR__ . "/../config/db.php";

$range = isset($_GET['range']) ? trim($_GET['range']) : '24h';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$where = [];
$params = [];
$types = "";

if ($range !== 'all' && $range !== 'custom') {
    $intervalMap = [
        '1h' => 'INTERVAL 1 HOUR',
        '6h' => 'INTERVAL 6 HOUR',
        '12h' => 'INTERVAL 12 HOUR',
        '24h' => 'INTERVAL 24 HOUR',
        '7d' => 'INTERVAL 7 DAY'
    ];
    if (isset($intervalMap[$range])) {
        $intervalSql = $intervalMap[$range];
        $where[] = "recorded_at >= (NOW() - $intervalSql)";
    }
} elseif ($range === 'custom' || ($startDate !== '' || $endDate !== '')) {
    if ($startDate !== '') {
        $where[] = "recorded_at >= ?";
        $params[] = $startDate . (strlen($startDate) === 10 ? ' 00:00:00' : '');
        $types .= "s";
    }
    if ($endDate !== '') {
        $where[] = "recorded_at <= ?";
        $params[] = $endDate . (strlen($endDate) === 10 ? ' 23:59:59' : '');
        $types .= "s";
    }
}

$whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Aggregate metrics
$aggSql = "
    SELECT 
        COUNT(*) as count_readings,
        MAX(fixed_power_w) as peak_fixed_power,
        AVG(fixed_power_w) as avg_fixed_power,
        MIN(fixed_voltage_v) as min_fixed_voltage,
        MAX(fixed_voltage_v) as max_fixed_voltage,
        
        MAX(movable_power_w) as peak_movable_power,
        AVG(movable_power_w) as avg_movable_power,
        MIN(movable_voltage_v) as min_movable_voltage,
        MAX(movable_voltage_v) as max_movable_voltage,
        
        MAX(ambient_temperature_c) as max_ambient_temp,
        MIN(ambient_temperature_c) as min_ambient_temp,
        AVG(ambient_temperature_c) as avg_ambient_temp,
        
        MAX(panel_temperature_c) as max_panel_temp,
        AVG(panel_temperature_c) as avg_panel_temp,
        
        AVG(humidity_percent) as avg_humidity,
        MAX(movable_light_lux) as max_lux,
        AVG(movable_light_lux) as avg_lux,
        MAX(irradiance_w_m2) as max_irradiance,
        AVG(irradiance_w_m2) as avg_irradiance
    FROM sensor_readings
    $whereSql
";

$stmt = $conn->prepare($aggSql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$agg = $stmt->get_result()->fetch_assoc();
$stmt->close();

$countReadings = (int)$agg['count_readings'];

// Calculate Energy using time delta between successive readings
$fixedEnergyWh = null;
$movableEnergyWh = null;
$energyStatus = "Insufficient Data";

if ($countReadings >= 2) {
    // Fetch time and power ordered by recorded_at
    $timeSql = "
        SELECT recorded_at, fixed_power_w, movable_power_w 
        FROM sensor_readings 
        $whereSql 
        ORDER BY recorded_at ASC, reading_id ASC
    ";
    $timeStmt = $conn->prepare($timeSql);
    if (!empty($types)) {
        $timeStmt->bind_param($types, ...$params);
    }
    $timeStmt->execute();
    $timeRes = $timeStmt->get_result();

    $prevTime = null;
    $totalFixedWh = 0;
    $totalMovableWh = 0;
    $validIntervals = 0;

    while ($r = $timeRes->fetch_assoc()) {
        $currentTime = strtotime($r['recorded_at']);
        if ($prevTime !== null) {
            $deltaSeconds = $currentTime - $prevTime;
            // Cap delta to 1 hour to prevent huge erroneous spikes across long offline gaps
            if ($deltaSeconds > 0 && $deltaSeconds <= 3600) {
                if ($r['fixed_power_w'] !== null) {
                    $totalFixedWh += ((float)$r['fixed_power_w'] * ($deltaSeconds / 3600.0));
                }
                if ($r['movable_power_w'] !== null) {
                    $totalMovableWh += ((float)$r['movable_power_w'] * ($deltaSeconds / 3600.0));
                }
                $validIntervals++;
            }
        }
        $prevTime = $currentTime;
    }
    $timeStmt->close();

    if ($validIntervals > 0) {
        $fixedEnergyWh = round($totalFixedWh, 3);
        $movableEnergyWh = round($totalMovableWh, 3);
        $energyStatus = "Calculated";
    }
}

// Movable Advantage calculation
$avgFixedP = ($agg['avg_fixed_power'] !== null) ? round((float)$agg['avg_fixed_power'], 3) : null;
$avgMovableP = ($agg['avg_movable_power'] !== null) ? round((float)$agg['avg_movable_power'], 3) : null;
$powerGainPercent = null;
if ($avgFixedP !== null && $avgMovableP !== null && $avgFixedP > 0.001) {
    $powerGainPercent = round((($avgMovableP - $avgFixedP) / $avgFixedP) * 100, 2);
}

sendJsonResponse(true, [
    'count_readings' => $countReadings,
    'fixed' => [
        'peak_power_w' => ($agg['peak_fixed_power'] !== null) ? round((float)$agg['peak_fixed_power'], 3) : null,
        'avg_power_w' => $avgFixedP,
        'min_voltage_v' => ($agg['min_fixed_voltage'] !== null) ? round((float)$agg['min_fixed_voltage'], 3) : null,
        'max_voltage_v' => ($agg['max_fixed_voltage'] !== null) ? round((float)$agg['max_fixed_voltage'], 3) : null,
        'energy_wh' => $fixedEnergyWh
    ],
    'movable' => [
        'peak_power_w' => ($agg['peak_movable_power'] !== null) ? round((float)$agg['peak_movable_power'], 3) : null,
        'avg_power_w' => $avgMovableP,
        'min_voltage_v' => ($agg['min_movable_voltage'] !== null) ? round((float)$agg['min_movable_voltage'], 3) : null,
        'max_voltage_v' => ($agg['max_movable_voltage'] !== null) ? round((float)$agg['max_movable_voltage'], 3) : null,
        'energy_wh' => $movableEnergyWh
    ],
    'comparison' => [
        'power_gain_percent' => $powerGainPercent,
        'energy_status' => $energyStatus,
        'energy_diff_wh' => ($fixedEnergyWh !== null && $movableEnergyWh !== null) ? round($movableEnergyWh - $fixedEnergyWh, 3) : null
    ],
    'environment' => [
        'max_ambient_temp_c' => ($agg['max_ambient_temp'] !== null) ? round((float)$agg['max_ambient_temp'], 1) : null,
        'min_ambient_temp_c' => ($agg['min_ambient_temp'] !== null) ? round((float)$agg['min_ambient_temp'], 1) : null,
        'avg_ambient_temp_c' => ($agg['avg_ambient_temp'] !== null) ? round((float)$agg['avg_ambient_temp'], 1) : null,
        'max_panel_temp_c' => ($agg['max_panel_temp'] !== null) ? round((float)$agg['max_panel_temp'], 1) : null,
        'avg_panel_temp_c' => ($agg['avg_panel_temp'] !== null) ? round((float)$agg['avg_panel_temp'], 1) : null,
        'avg_humidity_percent' => ($agg['avg_humidity'] !== null) ? round((float)$agg['avg_humidity'], 1) : null,
        'max_lux' => ($agg['max_lux'] !== null) ? round((float)$agg['max_lux'], 1) : null,
        'avg_lux' => ($agg['avg_lux'] !== null) ? round((float)$agg['avg_lux'], 1) : null,
        'max_irradiance_w_m2' => ($agg['max_irradiance'] !== null) ? round((float)$agg['max_irradiance'], 2) : null,
        'avg_irradiance_w_m2' => ($agg['avg_irradiance'] !== null) ? round((float)$agg['avg_irradiance'], 2) : null
    ]
], "Statistics calculated successfully");
