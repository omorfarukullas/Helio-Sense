<?php
require_once __DIR__ . "/../db.php";

// Fetch MySQL current time and total readings count
$timeQuery = $conn->query("SELECT NOW() as current_db_time, COUNT(*) as total_count FROM sensor_readings");
$timeRow = $timeQuery ? $timeQuery->fetch_assoc() : null;
$currentDbTime = $timeRow ? $timeRow['current_db_time'] : date('Y-m-d H:i:s');
$totalReadings = $timeRow ? (int)$timeRow['total_count'] : 0;

// Fetch latest reading
$latestQuery = $conn->query("
    SELECT * FROM sensor_readings 
    ORDER BY recorded_at DESC, reading_id DESC 
    LIMIT 1
");

$latestRow = $latestQuery ? $latestQuery->fetch_assoc() : null;

if (!$latestRow) {
    sendJsonResponse(true, [
        'latest' => null,
        'is_live' => false,
        'status_text' => 'OFFLINE',
        'seconds_ago' => null,
        'server_time' => $currentDbTime,
        'total_readings' => 0,
        'sensor_availability' => [
            'bmp280' => false,
            'dht_ambient' => false,
            'ds18b20_panel' => false,
            'bh1750_lux' => false,
            'irradiance' => false,
            'fixed_ina219' => false,
            'movable_ina219' => false,
            'tracking' => false
        ],
        'recent_readings' => []
    ], "No sensor readings found");
}

$latestParsed = parseSensorReading($latestRow);

// Compute time delta based on database recorded_at
$recordedTimestamp = strtotime($latestRow['recorded_at']);
$currentTimestamp = strtotime($currentDbTime);
$secondsAgo = max(0, $currentTimestamp - $recordedTimestamp);

// Check if LIVE based on threshold
$isLive = ($secondsAgo <= LIVE_THRESHOLD_SECONDS);

// Determine sensor availability
$sensorAvailability = [
    'bmp280' => ($latestRow['bmp280_temperature_c'] !== null || $latestRow['atmospheric_pressure_hpa'] !== null),
    'dht_ambient' => ($latestRow['ambient_temperature_c'] !== null || $latestRow['humidity_percent'] !== null),
    'ds18b20_panel' => ($latestRow['panel_temperature_c'] !== null),
    'bh1750_lux' => ($latestRow['movable_light_lux'] !== null),
    'irradiance' => ($latestRow['irradiance_w_m2'] !== null),
    'fixed_ina219' => ($latestRow['fixed_voltage_v'] !== null || $latestRow['fixed_current_a'] !== null || $latestRow['fixed_power_w'] !== null),
    'movable_ina219' => ($latestRow['movable_voltage_v'] !== null || $latestRow['movable_current_a'] !== null || $latestRow['movable_power_w'] !== null),
    'tracking' => ($latestRow['ldr_left'] !== null || $latestRow['ldr_right'] !== null || $latestRow['servo_angle_deg'] !== null)
];

// Fetch 5 latest readings for quick table preview
$recentQuery = $conn->query("
    SELECT * FROM sensor_readings 
    ORDER BY recorded_at DESC, reading_id DESC 
    LIMIT 5
");
$recentReadings = [];
if ($recentQuery) {
    while ($r = $recentQuery->fetch_assoc()) {
        $recentReadings[] = parseSensorReading($r);
    }
}

sendJsonResponse(true, [
    'latest' => $latestParsed,
    'is_live' => $isLive,
    'status_text' => $isLive ? 'LIVE' : 'OFFLINE',
    'seconds_ago' => $secondsAgo,
    'server_time' => $currentDbTime,
    'total_readings' => $totalReadings,
    'sensor_availability' => $sensorAvailability,
    'recent_readings' => $recentReadings
], "Latest sensor reading retrieved successfully");
