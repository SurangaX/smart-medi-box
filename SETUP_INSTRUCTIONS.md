# Smart Medi Box - Complete Setup Instructions

> **TL;DR:** This is a complete IoT medicine management system with Arduino hardware (ESP32 + Leonardo), GSM communication, QR authentication, automatic scheduling, and temperature-controlled storage.

---

## 📦 System Overview

### Hardware Components
- **Arduino Leonardo** - Sensor controller (DHT22, DS18B20, RTC, RFID, LCD)
- **ESP32** - Gateway module (GSM/GPRS, WiFi, Server communication)
- **SIM800L** - GSM module for SMS/HTTP communication
- **LCD Display** (ST7920 128x64) - User interface
- **Real-Time Clock** (DS3231) - Scheduling & timestamp
- **Temperature Sensors** (DHT22 + DS18B20) - Environmental monitoring
- **RFID Reader** (RC522) - Override access control
- **Solenoid Lock** - Medicine box access control
- **Peltier Cooler** (TEC) - Temperature-controlled storage (4-8°C)
- **Buzzer** - Alarm notifications
- **Door Sensor** - Unauthorized access detection

### Software Stack
- **Arduino Firmware** - Real-time control and scheduling (2 sketches)
- **PHP REST API** (6 modules) - Backend system with database
- **MySQL Database** (13 tables) - User profiles, schedules, logs
- **Web Dashboard** - Real-time monitoring interface

---

## 🚀 Quick Start (30 Minutes)

### Phase 1: Database Setup (5 minutes)

```bash
# 1. Create database
mysql -u root -p
CREATE DATABASE smart_medi_box;
CREATE USER 'medi_user'@'localhost' IDENTIFIED BY 'password123';
GRANT ALL PRIVILEGES ON smart_medi_box.* TO 'medi_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 2. Import schema
mysql -u medi_user -p smart_medi_box < robot_api/database_schema_postgresql.sql

# 3. Verify (should show 13 tables)
mysql -u medi_user -p smart_medi_box -e "SHOW TABLES;"
```

### Phase 2: Web API Setup (5 minutes)

```bash
# 1. Copy API to web directory
cp -r robot_api /var/www/html/smart-medi-box/

# 2. Update database credentials
nano robot_api/db_config.php
# Change: DB_HOST, DB_USER, DB_PASSWORD, DB_NAME

# 3. Test API
curl http://localhost/smart-medi-box/robot_api/index.php
# Expected: {"status":"SUCCESS","message":"Smart Medi Box API Online"}

# 4. Install Composer dependencies
cd robot_api
composer install
```

### Phase 3: Arduino Firmware Setup (10 minutes)

#### Download & Install Arduino IDE
1. Download from [arduino.cc](https://www.arduino.cc/en/software)
2. Install on your system

#### Install Required Libraries
Open Arduino IDE → **Sketch** → **Include Library** → **Manage Libraries**

**Required Libraries:**
1. **U8g2** - LCD display controller (Oliver Krause)
2. **RTClib** - Real-Time Clock (Adafruit)
3. **DHT** - Temperature/Humidity sensor (Adafruit)
4. **DallasTemperature** - DS18B20 sensor (Miles Burton)
5. **OneWire** - DS18B20 protocol (Paul Stoffregen)
6. **MFRC522** - RFID reader (Miguel Balboa) - _Optional_
7. **ArduinoJson** - JSON parsing (Benoit Blanchon)
8. **TinyGSM** - GSM module support (Volodymyr Shymanskyy)

#### Upload Leonardo Firmware
1. Open `arduino_leonardo_sensors.ino`
2. Select: **Tools** → **Board** → **Arduino Leonardo**
3. Select: **Tools** → **Port** → `COM3` (or your port)
4. Select: **Tools** → **Upload Speed** → `57600`
5. Click **Upload** or press `Ctrl+U`

**Configuration variables to update:**
```cpp
// In arduino_leonardo_sensors.ino
const char* SERVER_URL = "http://your-server.com/api";
const char* GSM_APN = "gprs.mobitel.lk";      // Update for your carrier
const char* DEVICE_ID = "MEDIBOX_001";
const float TARGET_TEMP = 4.0;                // 4°C for medicine storage
```

#### Upload ESP32 Firmware
1. Open `arduino_esp32_gateway.ino`
2. Select: **Tools** → **Board** → **ESP32 Dev Module** (or your ESP32 board)
3. Select: **Tools** → **Port** → `COM4` (or your port)
4. Select: **Tools** → **Upload Speed** → `921600`
5. Click **Upload** or press `Ctrl+U`

**Configuration variables to update:**
```cpp
// In arduino_esp32_gateway.ino
const char* SERVER_HOST = "smart-medi-box.onrender.com";
const char* GSM_APN = "hutch3g";              // Update for your carrier
const char* WIFI_SSID = "YourNetwork";        // Optional WiFi
const char* WIFI_PASS = "YourPassword";
```

### Phase 4: Hardware Wiring (10 minutes)

Reference [WIRING_MASTER_SHEET.md](WIRING_MASTER_SHEET.md) for detailed connections:

**Quick Summary:**
- **LCD**: Pins D10, D11, D13, D8 (Leonardo)
- **RTC**: SDA/SCL headers (I2C on Leonardo)
- **Temperature**: A0 (DHT22), A1 (DS18B20) with 4.7K pullups
- **Door Sensor**: D2 (INPUT_PULLUP)
- **Solenoid**: D5 via MOSFET
- **Buzzer**: D7 via BC337 transistor
- **Cooling**: D3 via MOSFET
- **RFID**: SPI (D9=CS, D6=RST)
- **ESP32-Leonardo**: Serial connection TX/RX with cross-over

---

## ⚙️ Configuration Guide

### Arduino Leonardo Configuration

```cpp
// ==================== CONFIGURATION ====================

// Server Details (for GSM HTTP requests)
const char* SERVER_URL = "http://your-server.com/api";
const char* GSM_APN = "gprs.mobitel.lk";      // Sri Lanka carriers
const char* GSM_USER = "";
const char* GSM_PASS = "";
const char* DEVICE_ID = "MEDIBOX_001";

// Timezone (for RTC)
const int TIMEZONE_OFFSET = +5.5;  // Sri Lanka (UTC+5:30)

// Temperature Settings
const float TARGET_TEMP = 4.0;      // Refrigerator: 4°C
const float TEMP_HYSTERESIS = 0.5; // ±0.5°C

// Alarm Settings
const unsigned long ALARM_INTERVAL = 300000;   // 5 minutes between SMS
const int BUZZER_LEVEL = 255;                   // PWM level (0-255)

// Debug Mode
const boolean DEBUG_MODE = true;    // Set to false in production

// ========================================================
```

### Arduino ESP32 Configuration

```cpp
// ==================== CONFIGURATION ====================

// Server Configuration
const char* SERVER_HOST = "smart-medi-box.onrender.com";
const uint16_t SERVER_PORT = 80;
const char* API_BASE_URL = "/api";

// GSM Configuration (SIM800L)
const char* GSM_APN = "hutch3g";
const char* GSM_USER = "";
const char* GSM_PASS = "";

// WiFi Configuration (Optional)
const char* WIFI_SSID = "YourNetwork";
const char* WIFI_PASSWORD = "YourPassword";

// Debug Mode
const boolean DEBUG_MODE = true;

// ========================================================
```

---

## 🔌 Pin Wiring Reference

### Arduino Leonardo Pinout

**Digital Pins:**
| Pin | Device | Purpose |
|-----|--------|---------|
| D0 | ESP32 | RX |
| D1 | ESP32 | TX |
| D2 | Door Sensor | Motion detection |
| D3 | MOSFET | Cooling/Peltier control |
| D4 | (Reserved) | Stepper STEP |
| D5 | MOSFET | Solenoid lock control |
| D6 | RC522 | RFID RST |
| D7 | BC337 | Buzzer signal |
| D8 | LCD | RST |
| D9 | RC522 | CS/SS |
| D10 | LCD | RS |
| D11 | LCD | RW |
| D12 | (Reserved) | Stepper DIR |
| D13 | LCD | E (Enable) |

**Analog Pins:**
| Pin | Device | Purpose |
|-----|--------|---------|
| A0 | DHT22 | Temperature/Humidity |
| A1 | DS18B20 | Internal temperature |
| A2 | Servo | Mechanical lock (future) |
| A3-A5 | (Reserved) | - |

**SPI Pins:**
- MOSI: ICSP MOSI (RC522)
- MISO: ICSP MISO (RC522)
- SCK: ICSP SCK (RC522)

**I2C Pins:**
- SDA: SDA header (RTC DS3231)
- SCL: SCL header (RTC DS3231)

### ESP32 Pinout

**Serial Ports:**
- **Serial0 (USB)**: Debug output (115200 baud)
- **Serial1 (GPIO9/10)**: GSM module SIM800L
- **Serial2 (GPIO16/17)**: Optional secondary

**Pin Assignments:**
| Pin | Device | Purpose |
|-----|--------|---------|
| GPIO16 | SIM800L | RX |
| GPIO17 | SIM800L | TX |
| GPIO3 | Leonardo | Serial RX |
| GPIO1 | Leonardo | Serial TX |

---

## 🧪 Testing Procedure

### 1. Serial Monitor Test (Arduino Leonardo)
```
Open: Tools → Serial Monitor
Baud: 9600
Expected: Initialization messages, RTC time display, sensor readings
```

### 2. LCD Display Test
```
Should display:
Start → "System Init..."
Then → Device ID and timestamp
Then → Current temperature, humidity, door status
```

### 3. RTC (Real-Time Clock) Test
```
Serial output should show current time every 60 seconds
If fails: Check I2C wiring (SDA/SCL), verify address 0x68
Command: AT+CCLK? (check via serial)
```

### 4. Temperature Sensor Tests
```
DHT22 (A0):
  - Should read 30-80% humidity
  - Should read 15-35°C in normal conditions

DS18B20 (A1):
  - Should read very close to DHT22 temperature
  - Check: 4.7K pullup resistor installed
```

### 5. Door Sensor Test
```
Open/Close door repeatedly
Expected Serial output:
  "DOOR: OPEN"
  "DOOR: CLOSED"
```

### 6. Buzzer Test
```
Should beep pattern during startup test
Check: Volume level, consistency, no stuck tones
```

### 7. Solenoid Lock Test
```
Should click/buzz when triggered
Should unlock (D5=HIGH) and lock (D5=LOW) smoothly
Check: 1N4007 diode installed for back-EMF protection
```

### 8. RFID Reader Test (Optional)
```
Hold RFID card near RC522 module
Should print card ID to serial monitor
If fails: Check SPI wiring (MOSI, MISO, SCK, CS on D9, RST on D6)
```

### 9. GSM Module Test (ESP32)
```
ESP32 Serial Monitor at 115200:
Look for: "Initializing GSM modem..."
Send AT command: AT
Expected response: OK

AT+CMGF=1     → Set text mode
AT+COPS?      → Check network operator
AT+CREG?      → Check registration status
```

---

## 🔄 System Flow & Operations

### User Registration Flow
```
1. User opens mobile app
2. App generates QR code containing: 
   AUTH|USER_ID|MAC_ADDRESS|DEVICE_ID
3. Arduino displays QR on LCD
4. User scans with mobile camera
5. ESP32 sends to API via GSM/HTTP
6. API creates user record in database
7. Arduino receives response, stores locally
8. System loads user schedules
```

### Daily Medicine Reminder Flow
```
08:00 AM:
  1. Arduino RTC triggers schedule alarm
  2. Solenoid lock releases (D5 goes HIGH)
  3. Buzzer sounds (D7 alarm pattern)
  4. LCD displays: "Take Medicine - Blood Pressure"
  5. User opens box
  6. Door sensor (D2) detects OPEN
  7. Buzzer stops
  8. User takes medicine and closes box
  9. Arduino logs action
  10. ESP32 sends HTTP POST to API: mark as completed
  11. SMS notification sent to user's phone
```

### Temperature Control Flow
```
Current temp: 5°C
Target temp: 4°C (medicine storage standard)

If Current > Target + Hysteresis (4.5°C):
  → Turn on cooling (D3 = HIGH)
  → Status: Cooling active
  
If Current < Target - Hysteresis (3.5°C):
  → Turn off cooling (D3 = LOW)
  → Status: Target reached
  
If Current < 2°C or Current > 8°C:
  → Sound alarm
  → Send alert SMS
  → Log alert in database
```

---

## 📱 API Quick Reference

### Base URL
```
http://smart-medi-box.onrender.com/api
```

### User Registration
```bash
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "age": 45,
  "phone": "0777154321",
  "mac_address": "AA:BB:CC:DD:EE:FF"
}

Response:
{
  "status": "SUCCESS",
  "user_id": "USER_20260413_A1B2C3",
  "device_id": "MEDIBOX_001"
}
```

### Create Schedule
```bash
POST /api/schedule/create
Content-Type: application/json

{
  "user_id": "USER_20260413_A1B2C3",
  "type": "MEDICINE",
  "hour": 8,
  "minute": 30,
  "description": "Blood pressure medicine"
}

Response:
{
  "status": "SUCCESS",
  "schedule_id": "SCHED_1713000000_A1B2C3"
}
```

### Get Today's Schedules
```bash
GET /api/schedule/get-today?user_id=USER_20260413_A1B2C3

Response:
{
  "status": "SUCCESS",
  "schedules": [
    {
      "schedule_id": "SCHED_1713000000_A1B2C3",
      "type": "MEDICINE",
      "hour": 8,
      "minute": 30,
      "is_completed": 0
    }
  ]
}
```

### Temperature Status
```bash
GET /api/temperature/current?user_id=USER_20260413_A1B2C3

Response:
{
  "status": "SUCCESS",
  "internal_temp": 4.2,
  "humidity": 45,
  "target_temp": 4.0,
  "cooling_active": false,
  "timestamp": "2026-04-13 14:30:00"
}
```

### Mark Schedule Complete
```bash
POST /api/schedule/complete
Content-Type: application/json

{
  "schedule_id": "SCHED_1713000000_A1B2C3",
  "timestamp": "2026-04-13 08:32:00"
}

Response:
{
  "status": "SUCCESS",
  "message": "Schedule marked as completed"
}
```

---

## 🔐 Security Configuration

### Before Deployment

1. **Change all default passwords** in `db_config.php`
2. **Update server URLs** to your production domain
3. **Disable DEBUG_MODE** in both Arduino sketches
4. **Configure HTTPS/SSL** for API endpoints
5. **Validate all incoming Arduino data** on server side
6. **Implement rate limiting** on API endpoints
7. **Never hardcode** API keys in firmware
8. **Enable database backups** (daily recommended)
9. **Audit access logs** weekly
10. **Use strong user authentication** tokens

### Arduino Security Best Practices

```cpp
// DO:
✓ Validate input from sensors before processing
✓ Implement timeout for GSM commands
✓ Log all security events
✓ Use checksums for sensor data
✓ Implement watchdog timer for recovery

// DON'T:
✗ Store user credentials in EEPROM
✗ Send sensitive data in plain HTTP
✗ Leave DEBUG_MODE enabled in production
✗ Ignore sensor anomalies
✗ Skip error handling
```

---

## 🐛 Troubleshooting Guide

### Compilation Errors

| Error | Solution |
|-------|----------|
| `U8g2 not found` | Install U8g2 via Library Manager |
| `RTClib not found` | Install Adafruit RTClib |
| `no matching function for call` | Check library versions match IDE |
| `expected ';' before '}'` | Check syntax, missing semicolons |
| `undefined reference to` | Verify all libraries installed |

### Runtime Issues

| Problem | Solution |
|---------|----------|
| LCD shows garbage | Check PSB pin to GND, verify SPI pins |
| RTC not responding | Verify SDA/SCL to headers, check address 0x68, I2C pull-ups |
| Temperature wrong | Check 4.7K pullup resistors on A0/A1 |
| Buzzer stuck ON | Check BC337 transistor polarity, base resistor |
| Solenoid won't lock | Verify D5 MOSFET wiring, 12V power supply, 1N4007 diode |
| Door sensor wrong | Verify D2 INPUT_PULLUP, test manually |
| RFID no response | Check SPI wiring (CS on D9, RST on D6, proper supply) |
| No GSM signal | Verify antenna, SIM card installed, check AT commands |

### Database Issues

```bash
# Test connection
mysql -u medi_user -p smart_medi_box -e "SELECT 1;"

# Check tables created
mysql -u medi_user -p smart_medi_box -e "SHOW TABLES;"

# View error logs
tail -f /var/log/mysql/error.log
```

### API Issues

```bash
# Test API endpoint
curl -X GET "http://localhost/robot_api/index.php/api/status"

# Check Apache logs
tail -f /var/log/apache2/error.log

# Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## 🚀 Common Tasks

### Add New User Schedule
```bash
curl -X POST "http://server/robot_api/api/schedule/create" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "USER_20260413_A1B2C3",
    "type": "MEDICINE",
    "hour": 14,
    "minute": 0,
    "description": "Afternoon medication"
  }'
```

### Check Alarm History
```bash
mysql -u medi_user -p smart_medi_box \
  -e "SELECT * FROM alarm_logs WHERE user_id = 1 ORDER BY triggered_at DESC LIMIT 10;"
```

### View Temperature Logs
```bash
curl "http://server/robot_api/api/temperature/history?user_id=USER_20260413_A1B2C3&days=7"
```

### Reset Arduino
Press the **Reset** button on the Leonardo, or send serial command:
```
RESET
```

### Test GSM Module
The ESP32 firmware will automatically:
1. Connect to network
2. Read SIM800L status
3. Send test HTTP request
Check Serial Monitor (115200) for: `GSM Connected: Operator_Name`

---

## 📊 System Performance

### Memory Usage
- **Arduino Leonardo**: ~20KB used / 32KB available
- **ESP32**: ~50KB used / 4MB available
- **Database**: ~15MB (with 1000 users, 10 schedules each)

### Timing
- **RTC Update**: Every minute
- **Sensor Read**: Every 30 seconds
- **Temperature Check**: Every 5 minutes
- **API Sync**: Every 10 minutes
- **Alarm Check**: Every 10 seconds (during active period)

### Network
- **GSM Baud Rate**: 9600 bps
- **Leonardo-ESP32 Serial**: 9600 bps
- **API Response Time**: <2 seconds
- **SMS Delivery**: 5-30 seconds

---

## 📁 File Structure

```
smart-medi-box/
├── arduino/
│   ├── arduino_leonardo_sensors.ino       (950+ lines)
│   └── arduino_esp32_gateway.ino          (850+ lines)
├── robot_api/
│   ├── auth.php
│   ├── schedule.php
│   ├── temperature.php
│   ├── user.php
│   ├── device.php
│   ├── index.php
│   ├── db_config.php
│   ├── database_schema_postgresql.sql
│   ├── composer.json
│   └── Dockerfile
├── dashboard/
│   ├── src/
│   │   ├── App.jsx
│   │   ├── main.jsx
│   │   └── index.css
│   ├── index.html
│   ├── package.json
│   └── vite.config.js
├── SETUP_INSTRUCTIONS.md                 (This file)
├── ARCHITECTURE.md
├── WIRING_MASTER_SHEET.md
├── SYSTEM_DOCUMENTATION.md
├── API_DEPLOYMENT_GUIDE.md
├── TESTING_GUIDE.md
├── QUICK_START_GUIDE.md
├── PROJECT_INDEX.md
├── README.md
└── render.yaml
```

---

## ✅ Deployment Checklist

### Pre-Deployment
- [ ] Database created with all 13 tables
- [ ] API tested at `/api/status` endpoint
- [ ] Arduino compiles without errors
- [ ] All required libraries installed
- [ ] Configuration variables updated (URLs, APNs)
- [ ] Hardware wiring triple-checked
- [ ] Test user created successfully

### Physical Setup
- [ ] Arduino Leonardo connected via USB
- [ ] ESP32 connected via USB-UART
- [ ] SIM card installed in SIM800L module
- [ ] GSM antenna connected
- [ ] All sensors wired correctly
- [ ] Power supply (12V for solenoid/cooling) connected
- [ ] All pull-up resistors installed

### Software Setup
- [ ] Leonardo firmware uploaded
- [ ] ESP32 firmware uploaded
- [ ] API deployed to production server
- [ ] Database backups configured
- [ ] Email/SMS notifications tested
- [ ] Mobile app QR scanner working

### Testing
- [ ] Temperature readings display correctly
- [ ] Schedules trigger at correct times
- [ ] Solenoid locks/unlocks smoothly
- [ ] Buzzer sounds for alarms
- [ ] Door sensor detects opening
- [ ] GSM module connects to network
- [ ] API receives data from Arduino
- [ ] SMS notifications send
- [ ] Mobile app authentication works

---

## 📚 Documentation Reference

| Document | Purpose |
|----------|---------|
| [SETUP_INSTRUCTIONS.md](SETUP_INSTRUCTIONS.md) | Complete setup guide (this file) |
| [WIRING_MASTER_SHEET.md](WIRING_MASTER_SHEET.md) | Hardware pin assignments |
| [ARCHITECTURE.md](ARCHITECTURE.md) | System design overview |
| [SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md) | API reference & technical details |
| [API_DEPLOYMENT_GUIDE.md](API_DEPLOYMENT_GUIDE.md) | Server & database setup |
| [TESTING_GUIDE.md](TESTING_GUIDE.md) | Testing procedures & validation |
| [README.md](README.md) | Project overview |
| [PROJECT_INDEX.md](PROJECT_INDEX.md) | File index & quick links |

---

## 🆘 Getting Help

### Online Resources
- **Arduino Official Docs**: https://docs.arduino.cc/
- **U8g2 Library Wiki**: https://github.com/olikraus/u8g2/wiki
- **RTClib Guide**: https://github.com/adafruit/RTClib
- **DallasTemperature**: https://github.com/milesburton/Arduino-Temperature-Control-Library
- **SIM800L AT Commands**: https://cdn.shopify.com/s/files/1/0165/3325/7363/files/SIM800L_AT_Command_Manual_V1.01.pdf
- **PHP MySQL**: https://www.php.net/docs.php
- **MySQL Reference**: https://dev.mysql.com/doc/

### Debugging Commands
```bash
# Check Arduino port
sudo dmesg | grep ttyUSB  # Linux
Get-PnpDevice -Class Ports | Select-Object Name  # Windows

# Monitor serial output
screen /dev/ttyUSB0 9600  # Linux
comtool /dev/COM3 9600    # Windows

# Test database
mysql -u medi_user -p smart_medi_box -e "SHOW TABLES;"

# Tail API logs
tail -f /var/log/apache2/error.log
```

---

## 📝 Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0.0 | 2026-04-15 | Complete setup instructions, consolidated guides |
| 1.0.0 | 2026-04-13 | Initial documentation release |

---

## 🎉 Success!

Once all steps are complete, your Smart Medi Box system is ready for deployment. Users can:
- Register via QR code scanning
- Receive medicine reminders at scheduled times
- Access medicine box only at appropriate times
- View medication adherence on mobile app
- Receive SMS notifications for reminders and emergencies
- Medical staff can track patient adherence remotely

**Happy coding! 🚀**

---

**Last Updated:** April 15, 2026  
**Maintained By:** Smart Medi Box Development Team  
**Status:** Production Ready ✅
