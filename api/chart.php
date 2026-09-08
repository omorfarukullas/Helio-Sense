<?php
require_once __DIR__ . "/../db.php";

$range = isset($_GET['range']) ? trim($_GET['range']) : '1h';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$limit = isset($_GET['limit']) ? min(1000, max(20, (int)$_GET['limit'])) : MAX_CHART_POINTS;

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

// Count total available rows
$countSql = "SELECT COUNT(*) as total FROM sensor_readings $whereSql";
$countStmt = $conn->prepare($countSql);
if (!empty($types)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalCount = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Downsample stride if data count exceeds limit
$stride = 1;
if ($totalCount > $limit) {
    $stride = (int)ceil($totalCount / $limit);
}

// Fetch records in chronological order
$dataSql = "
    SELECT 
        reading_id,
        recorded_at,
        ambient_temperature_c,
        humidity_percent,
        panel_temperature_c,
        bmp280_temperature_c,
        atmospheric_pressure_hpa,
        fixed_voltage_v,
        fixed_current_a,
        fixed_power_w,
        movable_light_lux,
        irradiance_w_m2,
        movable_voltage_v,
        movable_current_a,
        movable_power_w,
        ldr_left,
        ldr_right,
        servo_angle_deg
    FROM (
        SELECT * FROM sensor_readings $whereSql 
        ORDER BY recorded_at DESC, reading_id DESC 
        LIMIT ?
    ) AS recent_subquery
    ORDER BY recorded_at ASC, reading_id ASC
";

$maxFetch = min(2000, $limit * $stride);
$dataStmt = $conn->prepare($dataSql);
$bindTypes = $types . "i";
$bindParams = array_merge($params, [$maxFetch]);
$dataStmt->bind_param($bindTypes, ...$bindParams);
$dataStmt->execute();
$result = $dataStmt->get_result();

$labels = [];
$timestamps = [];
$datasets = [
    'fixed_power_w' => [],
    'movable_power_w' => [],
    'power_diff_w' => [],
    'ambient_temperature_c' => [],
    'panel_temperature_c' => [],
    'bmp280_temperature_c' => [],
    'humidity_percent' => [],
    'atmospheric_pressure_hpa' => [],
    'irradiance_w_m2' => [],
    'movable_light_lux' => [],
    'fixed_voltage_v' => [],
    'movable_voltage_v' => [],
    'fixed_current_a' => [],
    'movable_current_a' => [],
    'servo_angle_deg' => [],
    'ldr_left' => [],
    'ldr_right' => []
];

$counter = 0;
while ($row = $result->fetch_assoc()) {
    if ($stride > 1 && ($counter % $stride !== 0)) {
        $counter++;
        continue;
    }
    $counter++;

    $labels[] = date('H:i:s', strtotime($row['recorded_at']));
    $timestamps[] = $row['recorded_at'];

    $fp = ($row['fixed_power_w'] !== null && $row['fixed_power_w'] !== '') ? (float)$row['fixed_power_w'] : null;
    $mp = ($row['movable_power_w'] !== null && $row['movable_power_w'] !== '') ? (float)$row['movable_power_w'] : null;
    $diff = ($fp !== null && $mp !== null) ? round($mp - $fp, 3) : null;

    $datasets['fixed_power_w'][] = $fp !== null ? round($fp, 3) : null;
    $datasets['movable_power_w'][] = $mp !== null ? round($mp, 3) : null;
    $datasets['power_diff_w'][] = $diff;

    $datasets['ambient_temperature_c'][] = ($row['ambient_temperature_c'] !== null && $row['ambient_temperature_c'] !== '') ? round((float)$row['ambient_temperature_c'], 1) : null;
    $datasets['panel_temperature_c'][] = ($row['panel_temperature_c'] !== null && $row['panel_temperature_c'] !== '') ? round((float)$row['panel_temperature_c'], 1) : null;
    $datasets['bmp280_temperature_c'][] = ($row['bmp280_temperature_c'] !== null && $row['bmp280_temperature_c'] !== '') ? round((float)$row['bmp280_temperature_c'], 1) : null;
    $datasets['humidity_percent'][] = ($row['humidity_percent'] !== null && $row['humidity_percent'] !== '') ? round((float)$row['humidity_percent'], 1) : null;
    $datasets['atmospheric_pressure_hpa'][] = ($row['atmospheric_pressure_hpa'] !== null && $row['atmospheric_pressure_hpa'] !== '') ? round((float)$row['atmospheric_pressure_hpa'], 1) : null;
    $datasets['irradiance_w_m2'][] = ($row['irradiance_w_m2'] !== null && $row['irradiance_w_m2'] !== '') ? round((float)$row['irradiance_w_m2'], 2) : null;
    $datasets['movable_light_lux'][] = ($row['movable_light_lux'] !== null && $row['movable_light_lux'] !== '') ? round((float)$row['movable_light_lux'], 1) : null;
    $datasets['fixed_voltage_v'][] = ($row['fixed_voltage_v'] !== null && $row['fixed_voltage_v'] !== '') ? round((float)$row['fixed_voltage_v'], 3) : null;
    $datasets['movable_voltage_v'][] = ($row['movable_voltage_v'] !== null && $row['movable_voltage_v'] !== '') ? round((float)$row['movable_voltage_v'], 3) : null;
    $datasets['fixed_current_a'][] = ($row['fixed_current_a'] !== null && $row['fixed_current_a'] !== '') ? round((float)$row['fixed_current_a'], 4) : null;
    $datasets['movable_current_a'][] = ($row['movable_current_a'] !== null && $row['movable_current_a'] !== '') ? round((float)$row['movable_current_a'], 4) : null;
    $datasets['servo_angle_deg'][] = ($row['servo_angle_deg'] !== null && $row['servo_angle_deg'] !== '') ? round((float)$row['servo_angle_deg'], 1) : null;
    $datasets['ldr_left'][] = ($row['ldr_left'] !== null && $row['ldr_left'] !== '') ? (int)$row['ldr_left'] : null;
    $datasets['ldr_right'][] = ($row['ldr_right'] !== null && $row['ldr_right'] !== '') ? (int)$row['ldr_right'] : null;
}
$dataStmt->close();

sendJsonResponse(true, [
    'range' => $range,
    'total_points' => count($labels),
    'labels' => $labels,
    'timestamps' => $timestamps,
    'datasets' => $datasets
], "Chart data retrieved successfully");
