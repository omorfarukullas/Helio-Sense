# HelioSense Arduino ESP32 Firmware

This folder contains the ESP32 Arduino sketch for the **HelioSense** dual solar monitoring and active solar tracker station.

## File
- [heliosense_esp32.ino](file:///c:/xampp/htdocs/heliosense_api/arduino/heliosense_esp32.ino) - Full commented ESP32 firmware code backup.

---

## Hardware & Pin Configuration

| Component | Pin / Interface | Description |
|-----------|-----------------|-------------|
| **I2C SDA** | `GPIO 21` | SDA line for INA219 (x2), BMP280, BH1750 |
| **I2C SCL** | `GPIO 22` | SCL line for INA219 (x2), BMP280, BH1750 |
| **DHT11** | `GPIO 4` | Ambient humidity / temperature sensor |
| **LDR Left** | `GPIO 34` (ADC) | Left light dependent resistor |
| **LDR Right** | `GPIO 35` (ADC) | Right light dependent resistor |
| **Servo Motor** | `GPIO 18` (PWM) | Micro servo for solar tracker rotation |
| **Fixed INA219** | I2C Address `0x40` | Voltage, current & power for Fixed Solar Panel |
| **Movable INA219** | I2C Address `0x41` | Voltage, current & power for Movable Solar Panel |
| **BMP280** | I2C Address `0x76` / `0x77` | Barometric pressure & ambient temperature |
| **BH1750** | I2C Address `0x23` | Light illuminance (Lux) |

---

## Required Libraries (Arduino Library Manager)
1. **Adafruit INA219** (`Adafruit_INA219`)
2. **Adafruit BMP280 Library** (`Adafruit_BMP280`)
3. **BH1750** by Christopher Laws (`BH1750`)
4. **DHT sensor library** by Adafruit (`DHT`)
5. **ESP32Servo** by Kevin Harrington (`ESP32Servo`)
6. **ArduinoJson** by Benoit Blanchon (`ArduinoJson` v6+)
7. **WiFi** & **HTTPClient** (Built into ESP32 board package)

---

## Upload Interval & API
- **Solar Tracking Check**: Every 500 ms
- **Sensor Snapshot**: Every 5 seconds
- **Database Upload Interval**: Every 3 minutes (180,000 ms)
- **Destination API Endpoint**: `http://<YOUR_LOCAL_IP>/heliosense_api/insert_reading.php`
