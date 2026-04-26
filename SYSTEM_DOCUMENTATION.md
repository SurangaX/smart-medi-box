# Smart Medi Box - QR Authentication System

## 📋 System Overview

Smart Medi Box is a comprehensive IoT-based medicine management system with:
- **QR Code Authentication** - Secure user verification via mobile app
- **Arduino Leonardo Integration** - Real-time sensor data collection
- **Automatic Scheduling** - Medicine, food, and blood check reminders
- **Temperature Monitoring** - Peltier-based refrigeration control
- **SMS Notifications** - Instant alerts and reminders
- **RFID Override** - Manual access control
- **Door Sensor Integration** - Alarm management based on door state

---

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Smart Medi Box System                     │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────────┐      ┌──────────────────┐             │
│  │   Mobile App     │◄────►│   QR Scanner     │             │
│  │  (Android/iOS)   │      │  (Camera)        │             │
│  └─────────┬────────┘      └──────────────────┘             │
│            │                                                  │
│            │ Authentication                                   │
│            ▼                                                  │
│  ┌─────────────────────────────────────────────────────┐    │
│  │        Web API Server (PHP/MySQL)                    │    │
│  │  ┌─────────────┐  ┌─────────────┐  ┌────────────┐  │    │
│  │  │ Auth Module │  │ Schedule    │  │ Temperature│  │    │
│  │  │ (QR, User)  │  │ Module      │  │ Module     │  │    │
│  │  └─────────────┘  └─────────────┘  └────────────┘  │    │
│  └─────────────────────────┬──────────────────────────┘    │
│                            │                                │
│                GSM/HTTP Communication                       │
│                            │                                │
│                            ▼                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │   Arduino Leonardo + SIM800L (GSM Module)            │   │
│  │  ┌──────────────────────────────────────────────┐   │   │
│  │  │ Sensors:            Actuators:               │   │   │
│  │  │ • DS3231 (RTC)     • Solenoid Lock          │   │   │
│  │  │ • DHT22 (Temp)     • Door Sensor (D2)       │   │   │
│  │  │ • DS18B20 (Temp)   • Buzzer (D7)            │   │   │
│  │  │ • RC522 (RFID)     • Peltier Cooling (D3)   │   │   │
│  │  │                    • LCD Display (ST7920)     │   │   │
│  │  │                    • Servo (A2)              │   │   │
│  │  └──────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## 🔌 Hardware Configuration

### Arduino Leonardo Pin Assignment

**Digital Pins:**
| Pin | Device | Purpose |
|-----|--------|---------|
| D0 | SIM800L | RX (Crossover from TX) |
| D1 | SIM800L | TX (Crossover from RX) |
| D2 | Door Sensor | INPUT_PULLUP |
| D3 | MOSFET | Cooling/Peltier Control |
| D4 | Stepper | STEP signal |
| D5 | MOSFET | Solenoid Lock Control |
| D6 | RC522 | RST (RFID) |
| D7 | BC337 | Buzzer Control |
| D8 | LCD | RST (ST7920) |
| D9 | RC522 | CS/SS (RFID) |
| D10 | LCD | RS (ST7920) |
| D11 | LCD | RW (ST7920) |
| D12 | Stepper | DIR signal |
| D13 | LCD | E (Enable, ST7920) |

**Analog Pins:**
| Pin | Device | Purpose |
|-----|--------|---------|
| A0 | DHT22 | Temperature & Humidity |
| A1 | DS18B20 | Internal Temperature |
| A2 | Servo | MG995 Signal (Pin 20) |

### Communication Protocols

**SIM800L (GSM):**
- Serial1 (9600 baud)
- Crossover TX/RX wiring
- Supports SMS and HTTP requests

**RC522 (RFID):**
- SPI via ICSP header
- 3.3V logic (level shifter recommended)

**LCD (ST7920 128x64):**
- 4-wire serial interface via U8g2 library
- PSB = GND (Serial mode)

**RTC (DS3231):**
- I2C via SDA/SCL header pins (near AREF)
- NOT pins D2/D3 to avoid conflicts

---

## 🔐 Authentication Flow

### New User Registration

```
User → Mobile App (Scan devices) → Sends MAC address
  ↓
API: POST /api/auth/register
  - name, age, phone (format: +94XXXXXXXXX)
  - mac_address
  ↓
Server generates: USER_ID
  ↓
User added to database
  ↓
Arduino receives auth via GSM
  ↓
System loads user's schedules
```

### Existing User Authentication

```
User → QR Code from App → Arduino LCD shows QR
  ↓
Mobile app scans QR from Arduino LCD
  ↓
API: GET /api/auth/verify?user_id=USER_123&mac=AA:BB:CC
  ↓
Server verifies MAC address match
  ↓
Returns: SUCCESS / FAILED
  ↓
Arduino syncs user data (schedules, settings)
```

---

## ⏰ Scheduling System

### Schedule Types

1. **MEDICINE** - Medication time
2. **FOOD** - Meal time with medicine
3. **BLOOD_CHECK** - Blood sugar/pressure check

### Alarm Behavior

#### Triggered at Scheduled Time:
```
1. Buzzer activates (continuous)
2. LCD displays alarm message
3. Door solenoid unlocks
4. SMS sent to user
```

#### While Door Remains Closed:
```
- Alarm continues
- SMS reminders sent every 5 minutes
- System waits for user action
```

#### When Door Opens:
```
- Door sensor detects opening (D2 LOW)
- Alarm stops immediately
- Solenoid locks
- Schedule marked as completed
- SMS confirmation sent
```

#### Forced Door Opening (While Locked):
```
- Alarm triggers immediately
- "UNAUTHORIZED ACCESS" alert SMS sent
- System logs incident
```

### Schedule Management API

```bash
# Get user schedules
GET /api/schedule/get?user_id=USER_123

# Create new schedule
POST /api/schedule/create
  body: {user_id, type, hour, minute, description}

# Mark schedule completed
POST /api/schedule/complete
  body: {schedule_id, user_id}

# Get today's schedules
GET /api/schedule/today?user_id=USER_123

# Update schedule
POST /api/schedule/update
  body: {schedule_id, hour, minute, description}

# Delete schedule
POST /api/schedule/delete
  body: {schedule_id, user_id}
```

---

## 🌡️ Temperature Control

### System Features
- **Target Range:** 4-8°C (pharmaceutical storage)
- **Sensor:** DS18B20 (internal), DHT22 (humidity)
- **Control:** PWM-based Peltier TEC module
- **Hysteresis:** ±0.5°C default

### Temperature API

```bash
# Get current temperature
GET /api/temperature/current?user_id=USER_123

# Set target temperature (2-8°C)
POST /api/temperature/set-target
  body: {user_id, target_temp: 4.0}

# Get temperature history (last 7 days)
GET /api/temperature/history?user_id=USER_123&days=7

# Control cooling system
POST /api/temperature/control
  body: {user_id, action: "ON|OFF|AUTO"}
```

### PID Control Logic
```cpp
if (currentTemp > targetTemp + hysteresis) {
    // Turn ON Peltier
    analogWrite(COOLING_PIN, 255);
} else if (currentTemp < targetTemp - hysteresis) {
    // Turn OFF Peltier
    analogWrite(COOLING_PIN, 0);
}
```

---

## 👤 User Management

### Phone Number Format
- **Accepted Formats:**
  - `+94XXXXXXXXX` (international)
  - `0XXXXXXXXX` (local - auto-converted)
  - `94XXXXXXXXX`

- **Conversion:** `0777154321` → `+94777154321`

### User APIs

```bash
# Get user profile
GET /api/user/profile?user_id=USER_123

# Update user info
POST /api/user/update
  body: {user_id, name, age, phone}

# Get dashboard
GET /api/user/dashboard?user_id=USER_123

# Get user statistics
GET /api/user/stats?user_id=USER_123
```

---

## 🔧 Device Management

### Arduino Device Registration

```bash
# Register device
POST /api/device/register
  body: {
    user_id,
    device_name: "My Medi Box",
    device_type: "ARDUINO_LEONARDO",
    mac_address: "AA:BB:CC:DD:EE:FF",
    firmware_version: "1.0.1"
  }

# List devices
GET /api/device/list?user_id=USER_123

# Sync device
POST /api/device/sync
  body: {user_id, device_id, firmware_version}

# Check for commands
GET /api/device/check-commands?user_id=USER_123
```

---

## 📡 API Base URLs

### Authentication Endpoints
```
POST   /api/auth/verify              # Verify user
POST   /api/auth/register            # Register new user
POST   /api/auth/qr-generate         # Generate QR token
GET    /api/auth/mac-lookup          # Find by MAC address
```

### Schedule Endpoints
```
GET    /api/schedule/get             # Get schedules
POST   /api/schedule/create          # Create schedule
POST   /api/schedule/update          # Update schedule
POST   /api/schedule/complete        # Mark completed
GET    /api/schedule/today           # Today's schedules
POST   /api/schedule/delete          # Delete schedule
```

### Temperature Endpoints
```
GET    /api/temperature/current      # Current reading
POST   /api/temperature/set-target   # Set target
GET    /api/temperature/history      # Temperature history
POST   /api/temperature/control      # Control cooling
```

### User Endpoints
```
GET    /api/user/profile             # Get profile
POST   /api/user/update              # Update profile
GET    /api/user/dashboard           # Dashboard data
GET    /api/user/stats               # User statistics
```

### Device Endpoints
```
POST   /api/device/register          # Register device
GET    /api/device/list              # List devices
POST   /api/device/sync              # Sync device
POST   /api/device/update-status     # Update status
GET    /api/device/check-commands    # Check commands
```

---

## 💾 Database Setup

### Import Schema

```sql
mysql -u root -p smart_medi_box < database_schema.sql
```

### Required Tables
1. `users` - User profiles
2. `schedules` - Medicine/food schedules
3. `schedule_logs` - Completion tracking
4. `temperature_logs` - Temperature readings
5. `temperature_settings` - User preferences
6. `auth_logs` - Authentication attempts
7. `alarm_logs` - Alarm triggers
8. `arduino_commands` - Command queue
9. `device_registry` - Device tracking
10. `sms_notifications` - SMS history
11. `rfid_cards` - RFID card mappings
12. `qr_tokens` - QR authentication tokens

---

## 🚀 Deployment

### Server Requirements
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx with .htaccess support
- OpenSSL (for HTTPS)

### Installation Steps

1. **Upload files to web server**
   ```bash
   scp -r robot_api/ user@server:/var/www/html/medi-box/
   ```

2. **Create database**
   ```bash
   mysql -u root -p < robot_api/database_schema.sql
   ```

3. **Configure db_config.php**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'medi_box_user');
   define('DB_PASS', 'strong_password');
   define('DB_NAME', 'smart_medi_box');
   ```

4. **Set file permissions**
   ```bash
   chmod 755 robot_api/
   chmod 644 robot_api/*.php
   ```

5. **Upload Arduino code**
   - Open `smart_medi_box_main.ino` in Arduino IDE
   - Select: Tools → Board → Arduino Leonardo
   - Install required libraries:
     - U8g2
     - RTClib
     - DHT
     - DallasTemperature
   - Update server URL in code
   - Upload to Arduino

---

## 🔒 Security Considerations

### Authentication
- MAC address verification
- QR token expiration (5 minutes)
- User status validation

### Data Protection
- All passwords hashed (bcrypt recommended)
- HTTPS recommended for API traffic
- Rate limiting on API endpoints

### Hardware Security
- RFID override requires registration
- Door forced-open triggers alarm
- All actions logged with timestamps

---

## 📊 Monitoring & Logging

### Log Files
- `auth_logs` - Authentication attempts
- `schedule_logs` - Completion tracking
- `alarm_logs` - Alarm events
- `system_logs` - General events

### Dashboard Metrics
- Adherence rate (% of schedules completed)
- Temperature trends
- Device sync status
- SMS delivery status

---

## 🐛 Troubleshooting

### Arduino Connection Issues
```
Problem: No response from Arduino
Solution: Check GSM signal, verify baud rates (9600)

Problem: RTC not found
Solution: Verify SDA/SCL header pins, check I2C address

Problem: Temperature readings incorrect
Solution: Verify pullup resistors on DS18B20, check sensor connections
```

### API Issues
```
Problem: 404 on API calls
Solution: Verify URL format, check .htaccess rewrite rules

Problem: Database connection error
Solution: Check db_config.php credentials, verify MySQL service running

Problem: CORS errors
Solution: Verify CORS headers in index.php
```

---

## 📝 License & Support

This system is designed for medical use. Ensure all local regulations are followed.

For support or issues, contact: support@smartmedibox.com

---

**Version:** 2.0.1  
**Last Updated:** April 13, 2026  
**Created By:** SurangaX
