<?php
error_reporting(0);
ini_set('display_errors', 0);

define('LIVE_THRESHOLD_SECONDS', 10);
define('POLL_INTERVAL_MS', 10000);
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_CHART_POINTS', 300);

$host = "localhost";
$dbname = "heliosense";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    if (!headers_sent()) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(500);
    }
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "error" => "Could not connect to database"
    ]);
    exit;
}

$conn->set_charset("utf8mb4");

function sendJsonResponse($success, $data = [], $message = "", $statusCode = 200) {
    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    http_response_code($statusCode);
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function parseSensorReading($row) {
    if (!$row) return null;

    $formatFloat = function($val, $decimals = 2) {
        return ($val !== null && $val !== '') ? round((float)$val, $decimals) : null;
    };
    $formatInt = function($val) {
        return ($val !== null && $val !== '') ? (int)$val : null;
    };

    $fixedPower = ($row['fixed_power_w'] !== null && $row['fixed_power_w'] !== '') ? (float)$row['fixed_power_w'] : null;
    $movablePower = ($row['movable_power_w'] !== null && $row['movable_power_w'] !== '') ? (float)$row['movable_power_w'] : null;

    $powerDiff = null;
    $movableAdvantagePercent = null;
    if ($fixedPower !== null && $movablePower !== null) {
        $powerDiff = round($movablePower - $fixedPower, 3);
        if ($fixedPower > 0.0001) {
            $movableAdvantagePercent = round((($movablePower - $fixedPower) / $fixedPower) * 100, 2);
        }
    }

    $ldrLeft = $formatInt($row['ldr_left']);
    $ldrRight = $formatInt($row['ldr_right']);
    $trackingStatus = 'No Data';
    if ($ldrLeft !== null && $ldrRight !== null) {
        $diff = $ldrLeft - $ldrRight;
        if (abs($diff) <= 60) {
            $trackingStatus = 'Balanced';
        } elseif ($diff > 60) {
            $trackingStatus = 'Light stronger on Left';
        } else {
            $trackingStatus = 'Light stronger on Right';
        }
    }

    return [
        'reading_id' => (int)$row['reading_id'],
        'recorded_at' => $row['recorded_at'],
        'date' => date('d M Y', strtotime($row['recorded_at'])),
        'time' => date('h:i:s A', strtotime($row['recorded_at'])),
        
        // Environment
        'ambient_temperature_c' => $formatFloat($row['ambient_temperature_c'], 1),
        'humidity_percent' => $formatFloat($row['humidity_percent'], 1),
        'panel_temperature_c' => $formatFloat($row['panel_temperature_c'], 1),
        'bmp280_temperature_c' => $formatFloat($row['bmp280_temperature_c'], 1),
        'atmospheric_pressure_hpa' => $formatFloat($row['atmospheric_pressure_hpa'], 1),
        
        // Solar / Light
        'irradiance_w_m2' => $formatFloat($row['irradiance_w_m2'], 2),
        'movable_light_lux' => $formatFloat($row['movable_light_lux'], 1),
        
        // Fixed Panel
        'fixed_voltage_v' => $formatFloat($row['fixed_voltage_v'], 3),
        'fixed_current_a' => $formatFloat($row['fixed_current_a'], 4),
        'fixed_power_w' => $fixedPower !== null ? round($fixedPower, 3) : null,
        
        // Movable Panel
        'movable_voltage_v' => $formatFloat($row['movable_voltage_v'], 3),
        'movable_current_a' => $formatFloat($row['movable_current_a'], 4),
        'movable_power_w' => $movablePower !== null ? round($movablePower, 3) : null,
        
        // Tracking
        'ldr_left' => $ldrLeft,
        'ldr_right' => $ldrRight,
        'servo_angle_deg' => $formatFloat($row['servo_angle_deg'], 1),
        'tracking_status' => $trackingStatus,
        
        // Comparison
        'power_diff_w' => $powerDiff,
        'movable_advantage_percent' => $movableAdvantagePercent
    ];
}
?>
