/*
================================================================================
  HELIOSENSE - ESP32 DUAL SOLAR MONITORING & SOLAR TRACKER FIRMWARE (BACKUP)
================================================================================
  Description:
    Arduino ESP32 firmware for the HelioSense IoT solar tracking and dual-panel
    monitoring station. Measures environment data, fixed vs movable solar panel
    electrical parameters (voltage, current, power via dual INA219s), light
    intensity (BH1750), pressure & temperature (BMP280), humidity & temperature
    (DHT11), dual LDR solar tracking via servo, and uploads reading payloads
    over WiFi to the HelioSense PHP REST API every 3 minutes.

  Note:
    This entire code is commented out for backup and future reference.
    To use: uncomment this file or copy the sketch contents into Arduino IDE.
================================================================================
*/

/*
#include <Wire.h>
#include <Adafruit_INA219.h>
#include <Adafruit_BMP280.h>
#include <BH1750.h>
#include <DHT.h>
#include <ESP32Servo.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// =====================================================
// WiFi & API
// =====================================================

const char* WIFI_SSID = "habibi...Assalamualaikum!";
const char* WIFI_PASSWORD = "tukituki";

const char* SERVER_URL =
  "http://192.168.1.5/heliosense_api/insert_reading.php";

// =====================================================
// PIN CONFIGURATION
// =====================================================

#define SDA_PIN       21
#define SCL_PIN       22

#define DHT_PIN       4
#define DHT_TYPE      DHT11

#define LDR_LEFT      34
#define LDR_RIGHT     35

#define SERVO_PIN     18

// =====================================================
// TRACKING CONFIGURATION
// =====================================================

Servo trackerServo;

int servoPosition = 90;

const int tolerance = 100;
const int stepSize = 1;

const int minAngle = 10;
const int maxAngle = 170;

// =====================================================
// SENSOR OBJECTS
// =====================================================

Adafruit_INA219 fixedINA219(0x40);
Adafruit_INA219 movableINA219(0x41);

Adafruit_BMP280 bmp280;
BH1750 bh1750;

DHT dht(DHT_PIN, DHT_TYPE);

// =====================================================
// SENSOR STATUS
// =====================================================

bool fixedINA219Available = false;
bool movableINA219Available = false;
bool bmp280Available = false;
bool bh1750Available = false;
bool dhtAvailable = false;

// =====================================================
// SENSOR DATA
// =====================================================

// Environment
float ambientTemperature = NAN;
float humidity = NAN;
float panelTemperature = NAN;
float bmp280Temperature = NAN;
float atmosphericPressure = NAN;

// Fixed panel
float fixedVoltage = NAN;
float fixedCurrent = NAN;
float fixedPower = NAN;

// Movable panel
float movableLightLux = NAN;
float irradiance = NAN;
float movableVoltage = NAN;
float movableCurrent = NAN;
float movablePower = NAN;

// Tracking
int ldrLeft = 0;
int ldrRight = 0;
float servoAngle = 90;

// =====================================================
// TIMING
// =====================================================

// Solar tracking
const unsigned long TRACKING_INTERVAL = 500;

// Sensor reading
const unsigned long SENSOR_INTERVAL = 5000;

// Database upload
const unsigned long UPLOAD_INTERVAL = 180000; // 3 minutes

// WiFi reconnect
const unsigned long WIFI_RECONNECT_INTERVAL = 10000;

// =====================================================
// TIMERS
// =====================================================

unsigned long lastTrackingTime = 0;
unsigned long lastSensorTime = 0;
unsigned long lastUploadTime = 0;
unsigned long lastWiFiReconnectTime = 0;

// =====================================================
// SETUP
// =====================================================

void setup() {

  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("======================================");
  Serial.println("        HELIOSENSE STARTING");
  Serial.println("======================================");

  // ---------------------------------------------------
  // I2C
  // ---------------------------------------------------

  Wire.begin(SDA_PIN, SCL_PIN);

  // ---------------------------------------------------
  // ADC
  // ---------------------------------------------------

  analogReadResolution(12);

  // ---------------------------------------------------
  // SERVO
  // ---------------------------------------------------

  trackerServo.setPeriodHertz(50);

  trackerServo.attach(
    SERVO_PIN,
    500,
    2400
  );

  trackerServo.write(servoPosition);

  delay(500);

  // ---------------------------------------------------
  // INA219 FIXED
  // ---------------------------------------------------

  if (fixedINA219.begin()) {

    fixedINA219.setCalibration_32V_2A();

    fixedINA219Available = true;

    Serial.println("INA219 Fixed  : OK (0x40)");

  } else {

    Serial.println("INA219 Fixed  : NOT FOUND");
  }

  // ---------------------------------------------------
  // INA219 MOVABLE
  // ---------------------------------------------------

  if (movableINA219.begin()) {

    movableINA219.setCalibration_32V_2A();

    movableINA219Available = true;

    Serial.println("INA219 Movable: OK (0x41)");

  } else {

    Serial.println("INA219 Movable: NOT FOUND");
  }

  // ---------------------------------------------------
  // BMP280
  // ---------------------------------------------------

  if (bmp280.begin(0x76)) {

    bmp280Available = true;

    Serial.println("BMP280        : OK (0x76)");

  } else if (bmp280.begin(0x77)) {

    bmp280Available = true;

    Serial.println("BMP280        : OK (0x77)");

  } else {

    Serial.println("BMP280        : NOT FOUND");
  }

  // ---------------------------------------------------
  // BH1750
  // ---------------------------------------------------

  if (bh1750.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {

    bh1750Available = true;

    Serial.println("BH1750        : OK");

  } else {

    Serial.println("BH1750        : NOT FOUND");
  }

  // ---------------------------------------------------
  // DHT11
  // ---------------------------------------------------

  dht.begin();

  dhtAvailable = true;

  Serial.println("DHT11         : INITIALIZED");

  // ---------------------------------------------------
  // LDR
  // ---------------------------------------------------

  pinMode(LDR_LEFT, INPUT);
  pinMode(LDR_RIGHT, INPUT);

  Serial.println("LDR           : GPIO34 / GPIO35");

  // ---------------------------------------------------
  // WIFI
  // ---------------------------------------------------

  connectWiFi();

  // ---------------------------------------------------
  // FIRST SENSOR READING
  // ---------------------------------------------------

  readAllSensors();
  printSensorData();

  lastTrackingTime = millis();
  lastSensorTime = millis();

  // First upload after 3 minutes
  lastUploadTime = millis();

  Serial.println();
  Serial.println("======================================");
  Serial.println("       HELIOSENSE READY");
  Serial.println("======================================");
  Serial.println();
}

// =====================================================
// MAIN LOOP
// =====================================================

void loop() {

  unsigned long currentMillis = millis();

  // ===================================================
  // 1. SOLAR TRACKING
  // Every 500 ms
  // ===================================================

  if (currentMillis - lastTrackingTime >= TRACKING_INTERVAL) {

    lastTrackingTime = currentMillis;

    updateSolarTracker();
  }

  // ===================================================
  // 2. SENSOR READING
  // Every 5 seconds
  // ===================================================

  if (currentMillis - lastSensorTime >= SENSOR_INTERVAL) {

    lastSensorTime = currentMillis;

    readAllSensors();
    printSensorData();
  }

  // ===================================================
  // 3. WIFI RECONNECT
  // ===================================================

  if (WiFi.status() != WL_CONNECTED) {

    if (currentMillis - lastWiFiReconnectTime >=
        WIFI_RECONNECT_INTERVAL) {

      lastWiFiReconnectTime = currentMillis;

      connectWiFi();
    }
  }

  // ===================================================
  // 4. DATABASE UPLOAD
  // Every 3 minutes
  // ===================================================

  if (currentMillis - lastUploadTime >= UPLOAD_INTERVAL) {

    lastUploadTime = currentMillis;

    sendToServer();
  }
}

// =====================================================
// WIFI CONNECTION
// =====================================================

void connectWiFi() {

  if (WiFi.status() == WL_CONNECTED) {
    return;
  }

  Serial.println();
  Serial.println("Connecting to WiFi...");

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long startAttempt = millis();

  while (
    WiFi.status() != WL_CONNECTED &&
    millis() - startAttempt < 10000
  ) {

    delay(250);

    Serial.print(".");
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {

    Serial.println("WiFi connected!");

    Serial.print("ESP32 IP: ");
    Serial.println(WiFi.localIP());

  } else {

    Serial.println("WiFi connection failed.");
  }
}

// =====================================================
// SOLAR TRACKER
// =====================================================

void updateSolarTracker() {

  ldrLeft = analogRead(LDR_LEFT);
  ldrRight = analogRead(LDR_RIGHT);

  int difference = ldrLeft - ldrRight;

  // ---------------------------------------------------
  // Move according to light difference
  // ---------------------------------------------------

  if (difference > tolerance) {

    servoPosition += stepSize;

    if (servoPosition > maxAngle) {
      servoPosition = maxAngle;
    }

    trackerServo.write(servoPosition);
  }

  else if (difference < -tolerance) {

    servoPosition -= stepSize;

    if (servoPosition < minAngle) {
      servoPosition = minAngle;
    }

    trackerServo.write(servoPosition);
  }

  // Store current angle
  servoAngle = servoPosition;
}

// =====================================================
// READ ALL SENSORS
// =====================================================

void readAllSensors() {

  Serial.println();
  Serial.println("Reading sensors...");

  // ===================================================
  // DHT11
  // ===================================================

  if (dhtAvailable) {

    float dhtTemperature = dht.readTemperature();
    float dhtHumidity = dht.readHumidity();

    if (!isnan(dhtTemperature)) {

      Serial.print("DHT11 Temperature: ");
      Serial.print(dhtTemperature, 2);
      Serial.println(" C");
    }

    if (!isnan(dhtHumidity)) {

      humidity = dhtHumidity;

    } else {

      humidity = NAN;
    }
  }

  // ===================================================
  // BMP280
  // ===================================================

  if (bmp280Available) {

    bmp280Temperature = bmp280.readTemperature();

    atmosphericPressure =
      bmp280.readPressure() / 100.0;

    // BMP280 temperature -> ambient temperature
    ambientTemperature = bmp280Temperature;
  }

  // ===================================================
  // BH1750
  // ===================================================

  if (bh1750Available) {

    float lux = bh1750.readLightLevel();

    if (lux >= 0) {

      movableLightLux = lux;

      // Estimated irradiance
      // IMPORTANT:
      // This is an estimated conversion, not calibrated
      // solar irradiance measurement.
      irradiance = lux / 120.0;

    } else {

      movableLightLux = NAN;
      irradiance = NAN;
    }
  }

  // ===================================================
  // INA219 FIXED PANEL
  // ===================================================

  if (fixedINA219Available) {

    fixedVoltage =
      fixedINA219.getBusVoltage_V();

    // INA219 returns current in mA
    // Convert mA -> A
    fixedCurrent =
      fixedINA219.getCurrent_mA() / 1000.0;

    fixedPower =
      fixedVoltage * fixedCurrent;
  }

  // ===================================================
  // INA219 MOVABLE PANEL
  // ===================================================

  if (movableINA219Available) {

    movableVoltage =
      movableINA219.getBusVoltage_V();

    // INA219 returns current in mA
    // Convert mA -> A
    movableCurrent =
      movableINA219.getCurrent_mA() / 1000.0;

    movablePower =
      movableVoltage * movableCurrent;
  }

  // ===================================================
  // PANEL TEMPERATURE
  // ===================================================

  // DS18B20 integration will be added later.
  // Current test value retained.

  panelTemperature = 35.0;

  // ===================================================
  // LDR
  // ===================================================

  ldrLeft = analogRead(LDR_LEFT);
  ldrRight = analogRead(LDR_RIGHT);

  servoAngle = servoPosition;
}

// =====================================================
// PRINT SENSOR DATA
// =====================================================

void printSensorData() {

  Serial.println();
  Serial.println("======================================");
  Serial.println("        SENSOR SNAPSHOT");
  Serial.println("======================================");

  // ===================================================
  // ENVIRONMENT
  // ===================================================

  Serial.println("--- ENVIRONMENT ---");

  Serial.print("Ambient Temperature : ");

  if (!isnan(ambientTemperature))
    Serial.print(ambientTemperature, 2);
  else
    Serial.print("No Data");

  Serial.println(" C");

  Serial.print("Humidity            : ");

  if (!isnan(humidity))
    Serial.print(humidity, 2);
  else
    Serial.print("No Data");

  Serial.println(" %RH");

  Serial.print("Panel Temperature   : ");

  if (!isnan(panelTemperature))
    Serial.print(panelTemperature, 2);
  else
    Serial.print("No Data");

  Serial.println(" C");

  Serial.print("BMP280 Temperature  : ");

  if (!isnan(bmp280Temperature))
    Serial.print(bmp280Temperature, 2);
  else
    Serial.print("No Data");

  Serial.println(" C");

  Serial.print("Atmospheric Pressure: ");

  if (!isnan(atmosphericPressure))
    Serial.print(atmosphericPressure, 2);
  else
    Serial.print("No Data");

  Serial.println(" hPa");

  // ===================================================
  // FIXED PANEL
  // ===================================================

  Serial.println();
  Serial.println("--- FIXED PANEL ---");

  Serial.print("Voltage : ");

  if (!isnan(fixedVoltage))
    Serial.print(fixedVoltage, 3);
  else
    Serial.print("No Data");

  Serial.println(" V");

  Serial.print("Current : ");

  if (!isnan(fixedCurrent)) {

    // Show Amps with enough precision
    Serial.print(fixedCurrent, 4);

    Serial.print(" A  (");

    // Also show mA
    Serial.print(fixedCurrent * 1000.0, 2);

    Serial.print(" mA)");

  } else {

    Serial.print("No Data");
  }

  Serial.println();

  Serial.print("Power   : ");

  if (!isnan(fixedPower))
    Serial.print(fixedPower, 4);
  else
    Serial.print("No Data");

  Serial.println(" W");

  // ===================================================
  // MOVABLE PANEL
  // ===================================================

  Serial.println();
  Serial.println("--- MOVABLE PANEL ---");

  Serial.print("Light Lux : ");

  if (!isnan(movableLightLux))
    Serial.print(movableLightLux, 2);
  else
    Serial.print("No Data");

  Serial.println(" lux");

  Serial.print("Irradiance: ");

  if (!isnan(irradiance))
    Serial.print(irradiance, 2);
  else
    Serial.print("No Data");

  Serial.println(" W/m2");

  Serial.print("Voltage   : ");

  if (!isnan(movableVoltage))
    Serial.print(movableVoltage, 3);
  else
    Serial.print("No Data");

  Serial.println(" V");

  Serial.print("Current   : ");

  if (!isnan(movableCurrent)) {

    Serial.print(movableCurrent, 4);

    Serial.print(" A  (");

    Serial.print(movableCurrent * 1000.0, 2);

    Serial.print(" mA)");

  } else {

    Serial.print("No Data");
  }

  Serial.println();

  Serial.print("Power     : ");

  if (!isnan(movablePower))
    Serial.print(movablePower, 4);
  else
    Serial.print("No Data");

  Serial.println(" W");

  // ===================================================
  // TRACKING
  // ===================================================

  Serial.println();
  Serial.println("--- TRACKING ---");

  Serial.print("LDR Left  : ");
  Serial.println(ldrLeft);

  Serial.print("LDR Right : ");
  Serial.println(ldrRight);

  Serial.print("Difference: ");
  Serial.println(ldrLeft - ldrRight);

  Serial.print("Servo     : ");
  Serial.print(servoAngle, 1);
  Serial.println(" degree");

  Serial.println("======================================");
}

// =====================================================
// SEND DATA TO SERVER
// =====================================================

void sendToServer() {

  Serial.println();
  Serial.println("======================================");
  Serial.println("      SENDING DATA TO SERVER");
  Serial.println("======================================");

  // ---------------------------------------------------
  // Check WiFi
  // ---------------------------------------------------

  if (WiFi.status() != WL_CONNECTED) {

    Serial.println("WiFi not connected.");
    Serial.println("Upload skipped.");

    return;
  }

  // ---------------------------------------------------
  // JSON
  // ---------------------------------------------------

  StaticJsonDocument<1024> doc;

  // Server generates actual timestamp
  doc["recorded_at"] =
    "0000-00-00 00:00:00";

  // ===================================================
  // ENVIRONMENT
  // ===================================================

  if (!isnan(ambientTemperature)) {

    doc["ambient_temperature_c"] =
      round(ambientTemperature * 100.0) / 100.0;
  }

  if (!isnan(humidity)) {

    doc["humidity_percent"] =
      round(humidity * 100.0) / 100.0;
  }

  if (!isnan(panelTemperature)) {

    doc["panel_temperature_c"] =
      round(panelTemperature * 100.0) / 100.0;
  }

  if (!isnan(bmp280Temperature)) {

    doc["bmp280_temperature_c"] =
      round(bmp280Temperature * 100.0) / 100.0;
  }

  if (!isnan(atmosphericPressure)) {

    doc["atmospheric_pressure_hpa"] =
      round(atmosphericPressure * 100.0) / 100.0;
  }

  // ===================================================
  // FIXED PANEL
  // ===================================================

  if (!isnan(fixedVoltage)) {

    doc["fixed_voltage_v"] =
      round(fixedVoltage * 1000.0) / 1000.0;
  }

  if (!isnan(fixedCurrent)) {

    // Keep 4 decimal places
    doc["fixed_current_a"] =
      round(fixedCurrent * 10000.0) / 10000.0;
  }

  if (!isnan(fixedPower)) {

    doc["fixed_power_w"] =
      round(fixedPower * 10000.0) / 10000.0;
  }

  // ===================================================
  // MOVABLE PANEL
  // ===================================================

  if (!isnan(movableLightLux)) {

    doc["movable_light_lux"] =
      round(movableLightLux * 100.0) / 100.0;
  }

  if (!isnan(irradiance)) {

    doc["irradiance_w_m2"] =
      round(irradiance * 100.0) / 100.0;
  }

  if (!isnan(movableVoltage)) {

    doc["movable_voltage_v"] =
      round(movableVoltage * 1000.0) / 1000.0;
  }

  if (!isnan(movableCurrent)) {

    doc["movable_current_a"] =
      round(movableCurrent * 10000.0) / 10000.0;
  }

  if (!isnan(movablePower)) {

    doc["movable_power_w"] =
      round(movablePower * 10000.0) / 10000.0;
  }

  // ===================================================
  // TRACKING
  // ===================================================

  doc["ldr_left"] = ldrLeft;
  doc["ldr_right"] = ldrRight;

  doc["servo_angle_deg"] =
    round(servoAngle * 100.0) / 100.0;

  // ===================================================
  // SERIALIZE JSON
  // ===================================================

  String jsonData;

  serializeJson(doc, jsonData);

  Serial.println("JSON:");
  Serial.println(jsonData);

  // ===================================================
  // HTTP POST
  // ===================================================

  HTTPClient http;

  http.begin(SERVER_URL);

  http.addHeader(
    "Content-Type",
    "application/json"
  );

  http.setTimeout(10000);

  int httpResponseCode =
    http.POST(jsonData);

  Serial.print("HTTP Response Code: ");
  Serial.println(httpResponseCode);

  if (httpResponseCode > 0) {

    String response =
      http.getString();

    Serial.println("Server Response:");
    Serial.println(response);

  } else {

    Serial.println("HTTP request failed.");
  }

  http.end();

  Serial.println("======================================");
}
*/

