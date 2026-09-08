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
// Required fields (Power fields calculated server-side)
// ==================================================

$requiredFields = [
    "recorded_at",
    "ambient_temperature_c",
    "humidity_percent",
    "atmospheric_pressure_hpa",
    "panel_temperature_c",
    "bmp280_temperature_c",
    "irradiance_w_m2",
    "fixed_voltage_v",
    "fixed_current_a",
    "movable_light_lux",
    "movable_voltage_v",
    "movable_current_a",
    "ldr_left",
    "ldr_right",
    "servo_angle_deg"
];


foreach ($requiredFields as $field) {

    if (!array_key_exists($field, $data)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Missing required field: " . $field
        ]);

        exit;
    }
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
// Numeric conversion
// ==================================================

$ambientTemperature  = floatval($data["ambient_temperature_c"]);
$humidity            = floatval($data["humidity_percent"]);
$atmosphericPressure = floatval($data["atmospheric_pressure_hpa"]);
$panelTemperature    = floatval($data["panel_temperature_c"]);
$bmp280Temperature   = floatval($data["bmp280_temperature_c"]);
$irradiance          = floatval($data["irradiance_w_m2"]);

$fixedVoltage        = floatval($data["fixed_voltage_v"]);
$fixedCurrent        = floatval($data["fixed_current_a"]);

$movableLux          = floatval($data["movable_light_lux"]);
$movableVoltage      = floatval($data["movable_voltage_v"]);
$movableCurrent      = floatval($data["movable_current_a"]);

$ldrLeft             = intval($data["ldr_left"]);
$ldrRight            = intval($data["ldr_right"]);
$servoAngle          = floatval($data["servo_angle_deg"]);


// ==================================================
// Range validation
// ==================================================

if ($ambientTemperature < -40 || $ambientTemperature > 85) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ambient_temperature_c must be between -40 and 85"]);
    exit;
}

if ($humidity < 0 || $humidity > 100) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "humidity_percent must be between 0 and 100"]);
    exit;
}

if ($atmosphericPressure < 300 || $atmosphericPressure > 1100) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "atmospheric_pressure_hpa must be between 300 and 1100"]);
    exit;
}

if ($panelTemperature < -55 || $panelTemperature > 125) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "panel_temperature_c must be between -55 and 125"]);
    exit;
}

if ($bmp280Temperature < -40 || $bmp280Temperature > 85) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "bmp280_temperature_c must be between -40 and 85"]);
    exit;
}

if ($irradiance < 0 || $irradiance > 2000) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "irradiance_w_m2 must be between 0 and 2000"]);
    exit;
}

if ($fixedVoltage < 0 || $fixedVoltage > 50) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "fixed_voltage_v must be between 0 and 50"]);
    exit;
}

if ($fixedCurrent < 0 || $fixedCurrent > 20) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "fixed_current_a must be between 0 and 20"]);
    exit;
}

if ($movableLux < 0 || $movableLux > 100000) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "movable_light_lux must be between 0 and 100000"]);
    exit;
}

if ($movableVoltage < 0 || $movableVoltage > 50) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "movable_voltage_v must be between 0 and 50"]);
    exit;
}

if ($movableCurrent < 0 || $movableCurrent > 20) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "movable_current_a must be between 0 and 20"]);
    exit;
}

if ($ldrLeft < 0 || $ldrLeft > 4095) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ldr_left must be between 0 and 4095"]);
    exit;
}

if ($ldrRight < 0 || $ldrRight > 4095) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ldr_right must be between 0 and 4095"]);
    exit;
}

if ($servoAngle < 0 || $servoAngle > 180) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "servo_angle_deg must be between 0 and 180"]);
    exit;
}


// ==================================================
// Power Calculation (Calculated accurately on server)
// ==================================================

$fixedPower   = round($fixedVoltage * $fixedCurrent, 4);
$movablePower = round($movableVoltage * $movableCurrent, 4);


// ==================================================
// Insert into Database (All 17 fields)
// ==================================================

$sql = "
INSERT INTO sensor_readings (
    recorded_at,
    ambient_temperature_c,
    humidity_percent,
    atmospheric_pressure_hpa,
    panel_temperature_c,
    bmp280_temperature_c,
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
    $atmosphericPressure,
    $panelTemperature,
    $bmp280Temperature,
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
        "success" => true,
        "message" => "Sensor reading inserted successfully",
        "reading_id" => $stmt->insert_id,
        "recorded_at" => $recordedAt,
        "fixed_power_w" => $fixedPower,
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