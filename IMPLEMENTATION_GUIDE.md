# Smart Medi Box - Complete Feature Implementation

## 🎯 System Overview

A comprehensive IoT medication management system with automated scheduling, temperature control, notifications, RFID authentication, and real-time monitoring.

**Commit:** `ae5bce7`

---

## 📋 Features Implemented

### 1. **QR Code Authentication & Device Pairing**

#### Files:
- `robot_api/qr_auth.php` - Backend QR authentication API
- `arduino/arduino_esp32_gateway_complete.ino` - ESP32 QR display and WiFi

#### Endpoints:
```
POST /api/qr/authenticate              - QR token to user authentication
POST /api/qr/pair-new-device          - Detect new device via MAC address
POST /api/qr/register-mac             - Register new user with device
GET  /api/qr/verify-token             - Verify QR token validity
```

#### Workflow:
1. Arduino displays QR code for user to scan
2. QR contains authentication token
3. Backend verifies token and authenticates user
4. Device registered to user for future use

### 2. **Automated Schedule Management**

#### Types of Schedules:
- **MEDICINE** - Medicine intake times
- **FOOD** - Meal times  
- **BLOOD_CHECK** - Blood sugar/health check times

#### Features:
- Create, update, delete schedules
- Automatic time-based alarm triggering
- Track completion status
- Support for recurring times

#### API Endpoints:
```
GET  /api/device/schedules            - Get user's active schedules
POST /api/schedule/create             - Create new schedule
POST /api/schedule/update             - Modify schedule
POST /api/schedule/complete           - Mark as completed
POST /api/schedule/delete             - Remove schedule
```

### 3. **Alarm System with Multiple Triggers**

#### Alarm Flow:
```
Scheduled Time Reached
        ↓
CHECK DATABASE FOR SCHEDULE
        ↓
TRIGGER ALARM EVENTS:
  ├─ Buzzer activation (intermittent pattern)
  ├─ Display message on TFT
  ├─ Solenoid unlock door
  ├─ SMS notification sent
  └─ App notification sent
        ↓
DOOR SENSOR DETECTION
        ↓
User Opens Door?
  ├─ YES → ALARM STOPS ✓
  └─ NO → CONTINUE (5-minute intervals)
```

#### Alarm Behavior:
- **At Scheduled Time:** Alarm starts immediately
- **Buzzer Pattern:** 500ms on, 500ms off (continuous)
- **Solenoid:** Unlocks automatically
- **Notifications:** SMS + App reminders every 5 minutes
- **Door Opening:** Immediately stops buzzer when user takes action

### 4. **Notifications System**

#### Files:
- `robot_api/notifications.php` - Notification management API

#### Notification Types:
- MEDICINE_REMINDER
- FOOD_REMINDER
- BLOOD_CHECK_REMINDER
- ALARM_MEDICINE
- ALARM_FOOD
- ALARM_BLOOD_CHECK

#### Delivery Methods:
1. **SMS** - Via Twilio/AWS SNS (configure credentials)
2. **App Push** - Via Firebase Cloud Messaging
3. **Display** - On TFT screen

#### API Endpoints:
```
POST /api/notifications/send          - Send SMS + app notification
GET  /api/notifications/pending       - Get unsent notifications
POST /api/notifications/mark-sent     - Mark notification delivered
POST /api/alarm/schedule-reminder     - Schedule 5-min interval reminders
```

### 5. **Temperature Control (Peltier Cooling)**

#### Features:
- Real-time temperature monitoring (DS18B20 sensor)
- Humidity tracking (DHT22 sensor)
- Automatic cooling control
- Target temperature adjustment
- Temperature history/graphs
- Hysteresis control (prevent on/off cycling)

#### Workflow:
```
READ TEMPERATURE EVERY 30 SECONDS
        ↓
IF TEMP > TARGET + 0.5°C:
  └─ Activate Peltier cooling
        ↓
IF TEMP < TARGET - 0.5°C:
  └─ Deactivate cooling (prevent overcooling)
        ↓
LOG TEMPERATURE DATA TO DATABASE
        ↓
DISPLAY ON DASHBOARD WITH TREND
```

#### API Endpoints:
```
GET  /api/temperature/current         - Get current temperature
POST /api/temperature/set-target      - Set target temperature
GET  /api/temperature/history         - Get temperature logs
POST /api/temperature/control         - Manual cooling control
```

### 6. **Door Lock & Solenoid Control**

#### Features:
- **Automatic Unlock:** Solenoid unlocks at scheduled time
- **Manual Lock/Unlock:** Via commands from server
- **Forced Open Detection:** Triggers alarm if opened while locked
- **Status Monitoring:** Door sensor (magnetic switch)

#### States:
- **LOCKED:** Normal state
- **UNLOCKED:** During alarm window or manual override

### 7. **RFID Authentication & Override**

#### Features:
- Authorize access without alarm
- Multiple RFID tag support
- Access logging
- Override locked state

#### Workflow:
```
DOOR IS LOCKED & ALARM IS ACTIVE
        ↓
User scans RFID tag
        ↓
Backend verifies RFID ownership
        ↓
IF AUTHORIZED:
  ├─ Unlock solenoid
  └─ NO ALARM TRIGGERED
        ↓
IF NOT AUTHORIZED:
  └─ Trigger alarm (unauthorized access)
```

#### API Endpoints:
```
POST /api/rfid/unlock                 - RFID unlock request
POST /api/rfid/add-tag                - Add RFID tag to user
POST /api/rfid/remove-tag             - Remove RFID tag
GET  /api/rfid/logs                   - View access logs
```

### 8. **Unauthorized Access Detection**

#### Triggers:
1. **Forced Door Open:** While locked
2. **Wrong RFID Tag:** Non-authorized tag
3. **Invalid QR Code:** Expired/wrong token

#### Response:
- Immediate alarm trigger
- SMS alert to registered contacts
- Door remains locked
- Alert logged to system

---

## 🛠️ Hardware Components

### ESP32 Gateway
```
Component               Pin     Function
─────────────────────────────────────────
TFT Display             SPI     QR code display
Buzzer                  GPIO 18 Alarm sound
WiFi                    Built-in Network communication
```

### Arduino Leonardo
```
Component               Pin     Function
─────────────────────────────────────────
Solenoid Relay          GPIO 3  Door lock control
Door Sensor             GPIO 2  Magnetic switch
Buzzer                  GPIO 6  Alarm buzzer
Temperature (DS18B20)   GPIO 4  Freezer temp
Humidity (DHT22)        GPIO 5  Ambient humidity
Status LED              GPIO 13 System status
RFID Reader             Software Serial RX Door access
```

### I2C Communication
```
Peltier Controller      Address 0x10    Cooling control
```

---

## 📱 API Documentation

### Authentication
All API calls require `Authorization: Bearer <token>` header

### Schedule Management

#### Create Schedule
```bash
POST /api/schedule/create
Content-Type: application/json

{
  "user_id": 1,
  "type": "MEDICINE",
  "hour": 8,
  "minute": 0,
  "description": "Take with breakfast"
}

Response (201):
{
  "status": "SUCCESS",
  "schedule_id": 1,
  "message": "Schedule created"
}
```

#### Get Schedules
```bash
GET /api/device/schedules?user_id=1

Response (200):
{
  "status": "SUCCESS",
  "schedules": [
    {
      "id": 1,
      "type": "MEDICINE",
      "time": "08:00",
      "hour": 8,
      "minute": 0,
      "description": "Take with breakfast",
      "is_completed": false
    }
  ],
  "count": 1
}
```

### Alarm Management

#### Trigger Alarm
```bash
POST /api/alarm/trigger
Content-Type: application/json

{
  "user_id": 1,
  "schedule_id": 1,
  "schedule_type": "MEDICINE"
}

Response (201):
{
  "status": "SUCCESS",
  "alarm_id": 10,
  "message": "Alarm triggered - Buzzer, Display, and Solenoid activated",
  "commands_queued": 3
}
```

#### Dismiss Alarm
```bash
POST /api/alarm/dismiss
Content-Type: application/json

{
  "user_id": 1,
  "alarm_id": 10
}

Response (200):
{
  "status": "SUCCESS",
  "message": "Alarm dismissed - Buzzer stopped"
}
```

### Temperature Control

#### Get Current Temperature
```bash
GET /api/temperature/current?user_id=1

Response (200):
{
  "status": "SUCCESS",
  "current_temp": 4.5,
  "target_temp": 4.0,
  "humidity": 65.3
}
```

#### Set Target Temperature
```bash
POST /api/temperature/set-target
Content-Type: application/json

{
  "user_id": 1,
  "target_temp": 5.0
}

Response (200):
{
  "status": "SUCCESS",
  "new_target": 5.0
}
```

### Notifications

#### Send Notification
```bash
POST /api/notifications/send
Content-Type: application/json

{
  "user_id": 1,
  "schedule_id": 1,
  "type": "MEDICINE_REMINDER",
  "message": "It's time for your medication!",
  "phone": "+94771234567"
}

Response (201):
{
  "status": "SUCCESS",
  "notification_id": 1,
  "sms_sent": true,
  "app_queued": true
}
```

---

## 🔧 Database Schema

### New Tables Created:
```sql
-- Device Sessions (for Arduino authentication)
device_sessions
  - user_id, device_mac, session_token, expires_at

-- Device Pairings (for new device setup)
device_pairings
  - device_mac, pairing_token, device_type, expires_at

-- Notifications
notifications
  - user_id, schedule_id, type, message, phone
  - sms_sent, app_sent, timestamps

-- Reminder Schedules (5-minute intervals)
reminder_schedules
  - alarm_id, user_id, interval_minutes, next_reminder_at

-- User RFID Tags
user_rfid_tags
  - user_id, rfid_tag, tag_name, is_active

-- RFID Access Logs
rfid_access_logs
  - user_id, rfid_tag, access_type, authorized, timestamp
```

### Modified Tables:
```sql
-- Added to alarm_logs:
- forced_open (BOOLEAN) - Track forced door opens
```

---

## 🚀 Deployment Instructions

### 1. Run Database Migration
```bash
# Connect to PostgreSQL and run:
psql -U neondb_owner -h ep-shy-mountain-achv0blk-pooler.sa-east-1.aws.neon.tech -d neondb

# Copy and paste contents of:
robot_api/migration_qr_notifications.sql
```

### 2. Upload Files to Render
```bash
git push origin main

# Render will auto-deploy:
- robot_api/qr_auth.php
- robot_api/notifications.php
- All other backend files
```

### 3. Configure Arduino

#### ESP32:
1. Update WiFi credentials in `arduino_esp32_gateway_complete.ino`
2. Install required libraries:
   - ArduinoJson
   - WiFi
   - HTTPClient
3. Upload to ESP32
4. Device will display QR code for pairing

#### Leonardo:
1. Install required libraries:
   - DallasTemperature (DS18B20)
   - DHT (humidity)
   - OneWire
2. Upload to Leonardo
3. Connect to ESP32 via Serial

### 4. Configure SMS (Optional)

For SMS notifications, add Twilio credentials to `robot_api/notifications.php`:
```php
define('TWILIO_ACCOUNT_SID', 'your_sid');
define('TWILIO_AUTH_TOKEN', 'your_token');
define('TWILIO_PHONE', '+1234567890');
```

### 5. Configure Firebase Cloud Messaging (Optional)

For app push notifications, add FCM credentials to backend.

---

## 🎯 Usage Workflow

### User Setup
```
1. Patient creates account (email, NIC, DOB, phone)
2. Receives QR code for device pairing
3. Scans QR with Arduino display
4. Device authenticated and registered
```

### Daily Usage
```
1. System checks time every minute
2. When schedule time reaches:
   - Buzzer starts
   - Display shows message
   - Solenoid unlocks
   - SMS + App notification sent
3. User opens door:
   - Door sensor detects opening
   - Alarm stops
   - Schedule marked complete
4. If not opened:
   - Alarm continues
   - Reminders sent every 5 minutes
   - Temperature maintained at target
```

### Medicine Temperature Maintenance
```
1. System continuously monitors temperature
2. If above target:
   - Activates Peltier cooling
3. If below target:
   - Deactivates cooling
4. Graph displayed on dashboard
```

---

## 📊 Dashboard Features

### Overview
- System status (Solenoid, Door, Alarm)
- Next scheduled alarm
- Temperature trend (24-hour graph)
- Today's schedules

### Schedules
- List all schedules
- Create/Edit/Delete
- Mark as completed
- View descriptions

### Temperature
- Current temperature display
- Set target temperature (0-10°C)
- History graph with trend
- Cooling status

### Alarms & Alerts
- Current alarm status
- Manual dismiss button
- System controls
- Emergency stop option

### Notifications
- Pending notifications
- SMS delivery status
- App notification status
- Notification history

### Settings
- Add/Remove RFID tags
- Configure preferences
- Device information
- Access logs

---

## 🔐 Security Features

1. **Token-Based Authentication:**
   - 7-day token expiry
   - Session tokens for devices
   - Token refresh capability

2. **RFID Authorization:**
   - Only authorized tags unlock
   - Access logging
   - Unauthorized access detection

3. **Data Encryption:**
   - HTTPS/SSL for all API calls
   - Bcrypt password hashing
   - Secure token generation

4. **Access Control:**
   - Role-based (PATIENT, DOCTOR)
   - User-specific data isolation
   - Activity logging

---

## 🐛 Troubleshooting

### Alarm Not Triggering
1. Check database for active schedules
2. Verify Arduino time sync (NTP)
3. Check ESP32 WiFi connection
4. Review Render logs: `renderlogs -f`

### Temperature Not Cooling
1. Verify Peltier power connection
2. Check I2C communication (address 0x10)
3. Confirm DS18B20 sensor connection
4. Review temperature logs in database

### QR Authentication Failing
1. Verify token not expired
2. Check device MAC address format
3. Confirm WiFi connectivity
4. Review qr_auth.php error logs

### SMS Not Sending
1. Configure Twilio credentials
2. Verify phone number format (+94...)
3. Check SMS balance
4. Review notification logs

---

## 📚 File Structure

```
smart-medi-box/
├── robot_api/
│   ├── qr_auth.php                    ← QR authentication
│   ├── notifications.php              ← Notifications & alarms
│   ├── migration_qr_notifications.sql ← Database migration
│   ├── auth.php                       ← User authentication
│   ├── device.php                     ← Device management
│   ├── schedule.php                   ← Schedule management
│   ├── temperature.php                ← Temperature control
│   └── index.php                      ← API router
├── arduino/
│   ├── arduino_esp32_gateway_complete.ino      ← ESP32 main
│   └── arduino_leonardo_sensors_complete.ino   ← Leonardo sensors
├── dashboard/
│   ├── src/
│   │   ├── DashboardComplete.jsx      ← Full dashboard UI
│   │   ├── App.jsx                    ← Main app with auth
│   │   └── App.css                    ← Styling
│   └── package.json
└── README.md (this file)
```

---

## 🔄 Communication Flow

```
            ┌──────────────────────────────────────┐
            │     Smart Medi Box System            │
            └──────────────────────────────────────┘
                    ↓         ↓          ↓
          ┌─────────┴─────────┴──────────┴─────────┐
          │                                        │
     ESP32 WiFi Gateway              Arduino Leonardo Sensors
    ┌──────────────────┐             ┌────────────────────┐
    │ - QR Display     │             │ - Door Sensor      │
    │ - WiFi Comm      │────Serial────│ - Temperature      │
    │ - Schedule Check │             │ - Humidity         │
    │ - Command Queue  │             │ - RFID Reader      │
    │ - Notifications  │             │ - Solenoid Control │
    └──────────────────┘             │ - Buzzer           │
          │                          └────────────────────┘
          │                                  │
          └──────────────┬───────────────────┘
                         ↓
            ┌────────────────────────┐
            │  Render Cloud Platform │
            ├────────────────────────┤
            │ PostgreSQL (Neon)      │
            │ - Users                │
            │ - Schedules            │
            │ - Alarms               │
            │ - Temperatures         │
            │ - Notifications        │
            │ - RFID Tags            │
            └────────────────────────┘
                       ↑
          ┌────────────┴────────────┐
          ↓                         ↓
    React Dashboard          Mobile App
  (Netlify Frontend)      (Push Notifications)
```

---

## 📞 Support & Contact

For issues or questions:
1. Check troubleshooting section
2. Review Render logs
3. Inspect Arduino Serial output
4. Check database records

---

**Last Updated:** April 16, 2026
**System Version:** 1.0.1 Complete
**Commit:** ae5bce7

