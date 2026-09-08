<?php
require_once __DIR__ . "/../db.php";

$startBench = microtime(true);

$timeQuery = $conn->query("SELECT NOW() as current_db_time, COUNT(*) as total_count FROM sensor_readings");
$timeRow = $timeQuery ? $timeQuery->fetch_assoc() : null;
$currentDbTime = $timeRow ? $timeRow['current_db_time'] : date('Y-m-d H:i:s');
$totalReadings = $timeRow ? (int)$timeRow['total_count'] : 0;

$latestQuery = $conn->query("
    SELECT * FROM sensor_readings 
    ORDER BY recorded_at DESC, reading_id DESC 
    LIMIT 1
");
$latestRow = $latestQuery ? $latestQuery->fetch_assoc() : null;

$dbPingMs = round((microtime(true) - $startBench) * 1000, 2);

if (!$latestRow) {
    sendJsonResponse(true, [
        'database' => [
            'status' => 'Connected',
            'ping_ms' => $dbPingMs,
            'name' => $dbname
        ],
        'live_status' => [
            'is_live' => false,
            'status' => 'OFFLINE',
            'data_state' => 'No Data in Database',
            'seconds_ago' => null,
            'threshold_seconds' => LIVE_THRESHOLD_SECONDS,
            'latest_recorded_at' => null
        ],
        'total_readings' => 0,
        'sensors' => [],
        'overall_availability' => 'No Data'
    ]);
}

$recordedTimestamp = strtotime($latestRow['recorded_at']);
$currentTimestamp = strtotime($currentDbTime);
$secondsAgo = max(0, $currentTimestamp - $recordedTimestamp);
$isLive = ($secondsAgo <= LIVE_THRESHOLD_SECONDS);

$sensors = [
    [
        'name' => 'BMP280 Barometric Sensor',
        'fields' => ['bmp280_temperature_c', 'atmospheric_pressure_hpa'],
        'available' => ($latestRow['bmp280_temperature_c'] !== null || $latestRow['atmospheric_pressure_hpa'] !== null),
        'status' => ($latestRow['bmp280_temperature_c'] !== null || $latestRow['atmospheric_pressure_hpa'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'temp_c' => $latestRow['bmp280_temperature_c'],
            'pressure_hpa' => $latestRow['atmospheric_pressure_hpa']
        ]
    ],
    [
        'name' => 'DHT11 / Ambient Sensor',
        'fields' => ['ambient_temperature_c', 'humidity_percent'],
        'available' => ($latestRow['ambient_temperature_c'] !== null || $latestRow['humidity_percent'] !== null),
        'status' => ($latestRow['ambient_temperature_c'] !== null || $latestRow['humidity_percent'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'temp_c' => $latestRow['ambient_temperature_c'],
            'humidity_percent' => $latestRow['humidity_percent']
        ]
    ],
    [
        'name' => 'DS18B20 / Panel Thermometer',
        'fields' => ['panel_temperature_c'],
        'available' => ($latestRow['panel_temperature_c'] !== null),
        'status' => ($latestRow['panel_temperature_c'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'temp_c' => $latestRow['panel_temperature_c']
        ]
    ],
    [
        'name' => 'BH1750 / Light Sensor',
        'fields' => ['movable_light_lux'],
        'available' => ($latestRow['movable_light_lux'] !== null),
        'status' => ($latestRow['movable_light_lux'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'lux' => $latestRow['movable_light_lux']
        ]
    ],
    [
        'name' => 'Solar Irradiance Pyranometer',
        'fields' => ['irradiance_w_m2'],
        'available' => ($latestRow['irradiance_w_m2'] !== null),
        'status' => ($latestRow['irradiance_w_m2'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'irradiance_w_m2' => $latestRow['irradiance_w_m2']
        ]
    ],
    [
        'name' => 'INA219 Fixed Panel Monitor',
        'fields' => ['fixed_voltage_v', 'fixed_current_a', 'fixed_power_w'],
        'available' => ($latestRow['fixed_voltage_v'] !== null || $latestRow['fixed_current_a'] !== null || $latestRow['fixed_power_w'] !== null),
        'status' => ($latestRow['fixed_voltage_v'] !== null || $latestRow['fixed_current_a'] !== null || $latestRow['fixed_power_w'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'voltage_v' => $latestRow['fixed_voltage_v'],
            'current_a' => $latestRow['fixed_current_a'],
            'power_w' => $latestRow['fixed_power_w']
        ]
    ],
    [
        'name' => 'INA219 Movable Panel Monitor',
        'fields' => ['movable_voltage_v', 'movable_current_a', 'movable_power_w'],
        'available' => ($latestRow['movable_voltage_v'] !== null || $latestRow['movable_current_a'] !== null || $latestRow['movable_power_w'] !== null),
        'status' => ($latestRow['movable_voltage_v'] !== null || $latestRow['movable_current_a'] !== null || $latestRow['movable_power_w'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'voltage_v' => $latestRow['movable_voltage_v'],
            'current_a' => $latestRow['movable_current_a'],
            'power_w' => $latestRow['movable_power_w']
        ]
    ],
    [
        'name' => 'Solar Tracking Mechanism',
        'fields' => ['ldr_left', 'ldr_right', 'servo_angle_deg'],
        'available' => ($latestRow['ldr_left'] !== null || $latestRow['ldr_right'] !== null || $latestRow['servo_angle_deg'] !== null),
        'status' => ($latestRow['ldr_left'] !== null || $latestRow['ldr_right'] !== null || $latestRow['servo_angle_deg'] !== null) ? 'Data Available' : 'Data Unavailable',
        'details' => [
            'ldr_left' => $latestRow['ldr_left'],
            'ldr_right' => $latestRow['ldr_right'],
            'servo_angle_deg' => $latestRow['servo_angle_deg']
        ]
    ]
];

$availableCount = 0;
foreach ($sensors as $s) {
    if ($s['available']) $availableCount++;
}

$overallAvailability = "No Data";
if ($availableCount === count($sensors)) {
    $overallAvailability = "Full (All Sensors Active)";
} elseif ($availableCount > 0) {
    $overallAvailability = "Partial ($availableCount / " . count($sensors) . " Modules Active)";
}

sendJsonResponse(true, [
    'database' => [
        'status' => 'Connected',
        'ping_ms' => $dbPingMs,
        'name' => $dbname
    ],
    'live_status' => [
        'is_live' => $isLive,
        'status' => $isLive ? 'LIVE' : 'OFFLINE',
        'data_state' => $isLive ? 'Receiving' : 'No Recent Data',
        'seconds_ago' => $secondsAgo,
        'threshold_seconds' => LIVE_THRESHOLD_SECONDS,
        'latest_recorded_at' => $latestRow['recorded_at']
    ],
    'total_readings' => $totalReadings,
    'overall_availability' => $overallAvailability,
    'available_sensor_count' => $availableCount,
    'total_sensor_count' => count($sensors),
    'sensors' => $sensors
], "System status retrieved successfully");
