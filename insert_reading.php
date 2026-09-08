<?php

header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once "db.php";


// ==================================================
// Only POST allowed
// ==================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Only POST method is allowed"
    ]);

    exit;
}


// ==================================================
// Check JSON Content-Type
// ==================================================

$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

if (stripos($contentType, "application/json") === false) {

    http_response_code(415);

    echo json_encode([
        "success" => false,
        "message" => "Content-Type must be application/json"
    ]);

    exit;
}


// ==================================================
// Read JSON
// ==================================================

$rawData = file_get_contents("php://input");

$data = json_decode($rawData, true);


if (json_last_error() !== JSON_ERROR_NONE) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON"
    ]);

    exit;
}


// ==================================================
// Helper: Nullable field extractor with range guard
// Missing, null, or out-of-range values -> stored as NULL
// ==================================================

function nullableFloat(array $data, string $key, float $min, float $max): ?float {
    if (!array_key_exists($key, $data) || $data[$key] === null) {
        return null;
    }
    $val = floatval($data[$key]);
    if ($val < $min || $val > $max) {
        return null;
    }
    return $val;
}

function nullableInt(array $data, string $key, int $min, int $max): ?int {
    if (!array_key_exists($key, $data) || $data[$key] === null) {
        return null;
    }
    $val = intval($data[$key]);
    if ($val < $min || $val > $max) {
        return null;
    }
    return $val;
}


// ==================================================
// Timestamp validation with Server-Time Fallback
// ==================================================

$timeQuery = $conn->query("SELECT NOW() as current_db_time");
$currentServerTime = ($timeQuery && $tRow = $timeQuery->fetch_assoc()) ? $tRow['current_db_time'] : date('Y-m-d H:i:s');
$serverTimestamp = strtotime($currentServerTime);

$recordedAt = $data["recorded_at"];

if (
    !is_string($recordedAt) ||
    !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $recordedAt)
) {
    // If format is invalid, fallback to current server time
    $recordedAt = $currentServerTime;
} else {
    $espTime = strtotime($recordedAt);
    // If invalid timestamp or drifted by more than 5 minutes (e.g. unsynced RTC/NTP year 2000)
    if ($espTime === false || abs($serverTimestamp - $espTime) > 300) {
        $recordedAt = $currentServerTime;
    }
}


// ==================================================
// Extract all sensor fields (all optional/nullable)
// Values out-of-range or missing are stored as NULL
// ==================================================

$ambientTemperature  = nullableFloat($data, 'ambient_temperature_c',    -40,    85);
$humidity            = nullableFloat($data, 'humidity_percent',           0,   100);
$panelTemperature    = nullableFloat($data, 'panel_temperature_c',      -55,   125);
$bmp280Temperature   = nullableFloat($data, 'bmp280_temperature_c',     -40,    85);
$atmosphericPressure = nullableFloat($data, 'atmospheric_pressure_hpa', 300,  1100);
$irradiance          = nullableFloat($data, 'irradiance_w_m2',            0,  2000);
$fixedVoltage        = nullableFloat($data, 'fixed_voltage_v',            0,    50);
$fixedCurrent        = nullableFloat($data, 'fixed_current_a',            0,    20);
$movableLux          = nullableFloat($data, 'movable_light_lux',          0, 100000);
$movableVoltage      = nullableFloat($data, 'movable_voltage_v',          0,    50);
$movableCurrent      = nullableFloat($data, 'movable_current_a',          0,    20);
$ldrLeft             = nullableInt($data,   'ldr_left',                   0,  4095);
$ldrRight            = nullableInt($data,   'ldr_right',                  0,  4095);
$servoAngle          = nullableFloat($data, 'servo_angle_deg',            0,   180);


// ==================================================
// Power Calculation (server-side: V x A)
// Only calculated when both V and A are available
// ==================================================

$fixedPower   = ($fixedVoltage !== null && $fixedCurrent !== null)
    ? round($fixedVoltage * $fixedCurrent, 4)
    : null;

$movablePower = ($movableVoltage !== null && $movableCurrent !== null)
    ? round($movableVoltage * $movableCurrent, 4)
    : null;


// ==================================================
// Insert into Database (all sensor columns, NULLs allowed)
// ==================================================

$sql = "
INSERT INTO sensor_readings (
    recorded_at,
    ambient_temperature_c,
    humidity_percent,
    panel_temperature_c,
    bmp280_temperature_c,
    atmospheric_pressure_hpa,
    irradiance_w_m2,
    fixed_voltage_v,
    fixed_current_a,
    fixed_power_w,
    movable_light_lux,
    movable_voltage_v,
    movable_current_a,
    movable_power_w,
    ldr_left,
    ldr_right,
    servo_angle_deg
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param(
    "sdddddddddddddiid",
    $recordedAt,
    $ambientTemperature,
    $humidity,
    $panelTemperature,
    $bmp280Temperature,
    $atmosphericPressure,
    $irradiance,
    $fixedVoltage,
    $fixedCurrent,
    $fixedPower,
    $movableLux,
    $movableVoltage,
    $movableCurrent,
    $movablePower,
    $ldrLeft,
    $ldrRight,
    $servoAngle
);

if ($stmt->execute()) {

    http_response_code(201);

    echo json_encode([
        "success"         => true,
        "message"         => "Sensor reading inserted successfully",
        "reading_id"      => $stmt->insert_id,
        "recorded_at"     => $recordedAt,
        "fixed_power_w"   => $fixedPower,
        "movable_power_w" => $movablePower
    ]);

} else {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database insert failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

?>

