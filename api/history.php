<?php
require_once __DIR__ . "/../db.php";

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(5, min(200, (int)$_GET['per_page'])) : DEFAULT_PAGE_SIZE;
$sort = (isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc') ? 'ASC' : 'DESC';
$range = isset($_GET['range']) ? trim($_GET['range']) : 'all';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';

// Build WHERE conditions
$where = [];
$params = [];
$types = "";

if ($search !== '') {
    if (is_numeric($search)) {
        $where[] = "reading_id = ?";
        $params[] = (int)$search;
        $types .= "i";
    }
}

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

// If CSV format is requested
if ($format === 'csv') {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=heliosense_readings_" . date('Ymd_His') . ".csv");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'Reading ID',
        'Recorded At',
        'Date',
        'Time',
        'Ambient Temp (°C)',
        'Humidity (%RH)',
        'Panel Temp (°C)',
        'BMP280 Temp (°C)',
        'Atmospheric Pressure (hPa)',
        'Solar Irradiance (W/m²)',
        'Light Intensity (lux)',
        'Fixed Voltage (V)',
        'Fixed Current (A)',
        'Fixed Power (W)',
        'Movable Voltage (V)',
        'Movable Current (A)',
        'Movable Power (W)',
        'LDR Left',
        'LDR Right',
        'Servo Angle (°)'
    ]);

    $csvSql = "SELECT * FROM sensor_readings $whereSql ORDER BY recorded_at $sort, reading_id $sort LIMIT 10000";
    $stmt = $conn->prepare($csvSql);
    if ($stmt) {
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['reading_id'],
                $row['recorded_at'],
                date('Y-m-d', strtotime($row['recorded_at'])),
                date('H:i:s', strtotime($row['recorded_at'])),
                $row['ambient_temperature_c'] !== null ? $row['ambient_temperature_c'] : 'No Data',
                $row['humidity_percent'] !== null ? $row['humidity_percent'] : 'No Data',
                $row['panel_temperature_c'] !== null ? $row['panel_temperature_c'] : 'No Data',
                $row['bmp280_temperature_c'] !== null ? $row['bmp280_temperature_c'] : 'No Data',
                $row['atmospheric_pressure_hpa'] !== null ? $row['atmospheric_pressure_hpa'] : 'No Data',
                $row['irradiance_w_m2'] !== null ? $row['irradiance_w_m2'] : 'No Data',
                $row['movable_light_lux'] !== null ? $row['movable_light_lux'] : 'No Data',
                $row['fixed_voltage_v'] !== null ? $row['fixed_voltage_v'] : 'No Data',
                $row['fixed_current_a'] !== null ? $row['fixed_current_a'] : 'No Data',
                $row['fixed_power_w'] !== null ? $row['fixed_power_w'] : 'No Data',
                $row['movable_voltage_v'] !== null ? $row['movable_voltage_v'] : 'No Data',
                $row['movable_current_a'] !== null ? $row['movable_current_a'] : 'No Data',
                $row['movable_power_w'] !== null ? $row['movable_power_w'] : 'No Data',
                $row['ldr_left'] !== null ? $row['ldr_left'] : 'No Data',
                $row['ldr_right'] !== null ? $row['ldr_right'] : 'No Data',
                $row['servo_angle_deg'] !== null ? $row['servo_angle_deg'] : 'No Data'
            ]);
        }
        $stmt->close();
    }
    fclose($output);
    exit;
}

// JSON Paginated Response
$countSql = "SELECT COUNT(*) as total FROM sensor_readings $whereSql";
$countStmt = $conn->prepare($countSql);
if (!empty($types)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalFiltered = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$offset = ($page - 1) * $perPage;

$dataSql = "SELECT * FROM sensor_readings $whereSql ORDER BY recorded_at $sort, reading_id $sort LIMIT ? OFFSET ?";
$dataStmt = $conn->prepare($dataSql);
$bindTypes = $types . "ii";
$bindParams = array_merge($params, [$perPage, $offset]);
$dataStmt->bind_param($bindTypes, ...$bindParams);
$dataStmt->execute();
$dataResult = $dataStmt->get_result();

$readings = [];
while ($row = $dataResult->fetch_assoc()) {
    $readings[] = parseSensorReading($row);
}
$dataStmt->close();

sendJsonResponse(true, [
    'readings' => $readings,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total_records' => $totalFiltered,
        'total_pages' => $totalPages,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ]
], "Historical readings retrieved successfully");
