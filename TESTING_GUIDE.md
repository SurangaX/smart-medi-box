# Smart Medi Box - Testing & Validation Guide

**Version:** 1.0.1  
**Purpose:** Comprehensive testing procedures for all system components  
**Status:** Use this guide to validate before production deployment  

---

## 📋 Overview

This guide provides step-by-step testing procedures for all Smart Medi Box components:

1. **Database Testing** - Schema, tables, triggers, connections
2. **API Testing** - Endpoints, responses, data validation
3. **Arduino Testing** - Sensors, communication, alarms
4. **Hardware Testing** - Actuators, circuits, integration
5. **End-to-End Testing** - Complete workflow validation

---

## 🗄️ Database Testing

### Test 1: Connection Verification

```bash
# Connect to MySQL
mysql -u medi_user -p smart_medi_box

# In MySQL prompt - verify connection
> SELECT 1;
# Expected: 1

# Check database name
> SELECT DATABASE();
# Expected: smart_medi_box
```

**Pass Criteria:** ✅ Successfully connected to database

---

### Test 2: Table Verification

```bash
# In MySQL prompt - count tables (should be 13)
> SHOW TABLES;

# Expected output:
# +------------------------+
# | Tables_in_smart_medi_box |
# +------------------------+
# | alarm_logs               |
# | arduino_commands         |
# | auth_logs                |
# | device_registry          |
# | qr_tokens                |
# | rfid_cards               |
# | schedule_logs            |
# | schedules                |
# | sms_notifications        |
# | system_logs              |
# | temperature_logs         |
# | temperature_settings     |
# | users                    |
# +------------------------+
# 13 rows

# Verify table count
> SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'smart_medi_box';
# Expected: 13
```

**Pass Criteria:** ✅ All 13 tables exist

---

### Test 3: Trigger Verification

```bash
# Check if temperature_settings trigger exists
> SHOW TRIGGERS;

# Expected: At least one trigger for auto-creating temperature_settings

# Create test user to verify trigger
> INSERT INTO users (name, age, phone, mac_address) VALUES ('Test User', 30, '0777154321', 'AA:BB:CC:DD:EE:FF');

# Get the user_id (should be recent)
> SELECT id FROM users ORDER BY id DESC LIMIT 1;
# Note the ID

# Verify temperature_settings was auto-created
> SELECT * FROM temperature_settings WHERE user_id = [YOUR_ID];
# Expected: 1 row with default values
```

**Pass Criteria:** ✅ Trigger auto-creates temperature_settings row

---

### Test 4: Data Integrity

```bash
# Test foreign key constraints
> INSERT INTO schedules (user_id, type, hour, minute) VALUES (99999, 'MEDICINE', 8, 0);
# Expected: Error (foreign key constraint)

# Test ENUM validation
> INSERT INTO schedules (user_id, type, hour, minute) VALUES (1, 'INVALID_TYPE', 8, 0);
# Expected: Error (invalid ENUM value)

# Test time bounds
> INSERT INTO schedules (user_id, type, hour, minute) VALUES (1, 'MEDICINE', 25, 0);
# Expected: No error (MySQL doesn't enforce CHECK by default)
# Note: API will validate before insert

# Clean up test data
> DELETE FROM users WHERE name = 'Test User';
```

**Pass Criteria:** ✅ Foreign keys and data types work correctly

---

### Test 5: Index Performance

```bash
# Check indexes exist
> SHOW INDEXES FROM users;
# Should show: PRIMARY (id), UNIQUE (mac_address)

> SHOW INDEXES FROM schedules;
# Should show: PRIMARY (id), KEY (user_id), KEY (created_at)

> SHOW INDEXES FROM temperature_logs;
# Should show: PRIMARY (id), KEY (user_id), KEY (timestamp)

# Verify index sizes
> SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'smart_medi_box'
  ORDER BY TABLE_NAME, SEQ_IN_INDEX;
```

**Pass Criteria:** ✅ All critical indexes present

---

## 🌐 API Testing

### Test 6: Server Connection

```bash
# Test basic HTTP connectivity
curl -v http://localhost/robot_api/index.php/api/status

# Expected response code: 200
# Expected body:
# {
#   "status": "SUCCESS",
#   "message": "Smart Medi Box API Online",
#   "service": "Smart Medi Box",
#   "version": "1.0.0",
#   "endpoints": { ... }
# }
```

**Pass Criteria:** ✅ API responds with correct structure

---

### Test 7: CORS Headers

```bash
# Test CORS preflight
curl -i -X OPTIONS http://localhost/robot_api/index.php/api/status \
  -H "Origin: http://example.com"

# Expected headers:
# Access-Control-Allow-Origin: *
# Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
# Access-Control-Allow-Headers: Content-Type, Authorization
```

**Pass Criteria:** ✅ CORS enabled for mobile apps

---

### Test 8: User Registration

```bash
# Register new user
curl -X POST "http://localhost/robot_api/index.php/api/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "age": 45,
    "phone": "0777154321",
    "mac_address": "AA:BB:CC:DD:EE:FF"
  }'

# Expected response:
# {
#   "status": "SUCCESS",
#   "user_id": "USER_20260413_XXXXX",
#   "message": "User registered successfully"
# }

# Save user_id for next tests: USER_20260413_XXXXX
```

**Pass Criteria:** ✅ User ID generated with correct format

---

### Test 9: User Verification

```bash
# Verify user with MAC address
curl -X POST "http://localhost/robot_api/index.php/api/auth/verify" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "USER_20260413_XXXXX",
    "mac_address": "AA:BB:CC:DD:EE:FF"
  }'

# Expected response:
# {
#   "status": "SUCCESS",
#   "phone": "+94777154321",
#   "name": "John Doe"
# }
```

**Pass Criteria:** ✅ User verified successfully

---

### Test 10: Schedule Creation

```bash
# Create medicine schedule
curl -X POST "http://localhost/robot_api/index.php/api/schedule/create" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "USER_20260413_XXXXX",
    "type": "MEDICINE",
    "hour": 8,
    "minute": 30,
    "description": "Morning medication"
  }'

# Expected response:
# {
#   "status": "SUCCESS",
#   "schedule_id": "SCHED_1713000000_XXXXX",
#   "message": "Schedule created successfully"
# }
```

**Pass Criteria:** ✅ Schedule ID generated with timestamp

---

### Test 11: Schedule Retrieval

```bash
# Get today's schedules
curl -X GET "http://localhost/robot_api/index.php/api/schedule/get-today?user_id=USER_20260413_XXXXX"

# Expected response:
# {
#   "status": "SUCCESS",
#   "schedules": [
#     {
#       "schedule_id": "SCHED_1713000000_XXXXX",
#       "type": "MEDICINE",
#       "hour": 8,
#       "minute": 30,
#       "is_completed": 0
#     }
#   ]
# }
```

**Pass Criteria:** ✅ Returns today's schedules sorted by time

---

### Test 12: Temperature Endpoint

```bash
# Get current temperature
curl -X GET "http://localhost/robot_api/index.php/api/temperature/current?user_id=USER_20260413_XXXXX"

# Expected response (may need Arduino data first):
# {
#   "status": "SUCCESS",
#   "internal_temp": 4.2,
#   "humidity": 45,
#   "target_temp": 4.0,
#   "cooling_active": false,
#   "timestamp": "2026-04-13 14:30:00"
# }
```

**Pass Criteria:** ✅ Returns temperature object structure

---

### Test 13: User Profile

```bash
# Get user profile
curl -X GET "http://localhost/robot_api/index.php/api/user/profile?user_id=USER_20260413_XXXXX"

# Expected response:
# {
#   "status": "SUCCESS",
#   "user": {
#     "user_id": "USER_20260413_XXXXX",
#     "name": "John Doe",
#     "age": 45,
#     "phone": "+94777154321",
#     "total_schedules": 1
#   }
# }
```

**Pass Criteria:** ✅ Profile returns complete user info

---

### Test 14: Error Handling

```bash
# Test invalid user_id
curl -X GET "http://localhost/robot_api/index.php/api/user/profile?user_id=INVALID_ID"

# Expected response code: 404
# Expected body:
# {
#   "status": "ERROR",
#   "message": "User not found"
# }

# Test missing required field
curl -X POST "http://localhost/robot_api/index.php/api/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe"}'

# Expected response code: 400
# Expected body:
# {
#   "status": "ERROR",
#   "message": "Missing required field: age"
# }
```

**Pass Criteria:** ✅ Proper error codes and messages

---

## 📱 Arduino Testing

### Test 15: Serial Connection

```
1. Connect Arduino Leonardo to computer via USB
2. Open Arduino IDE
3. Tools → Serial Monitor
4. Set baud rate to 9600
5. Should see initialization messages:
   - "Initializing Smart Medi Box..."
   - "SD Card detected" or "SD Card not found"
   - "RTC initialized"
   - "GSM module initialized"
   - "System ready"
```

**Pass Criteria:** ✅ All initialization messages appear without errors

---

### Test 16: LCD Display

```
Expected output on LCD:
Line 1: "Smart Medi Box"
Line 2: "System: Ready"
Line 3: "Temp: 4.2°C"
Line 4: "Time: 14:30"

Check:
- Characters display clearly
- No garbage text
- Brightness acceptable
- Contrast adjustable
```

**Pass Criteria:** ✅ LCD displays correct information

---

### Test 17: RTC (Real-Time Clock)

Open Serial Monitor and observe:
```
Serial output should show:
"RTC Time: 14:30:45"
"Date: 2026-04-13"

Verify:
- Time matches current time
- Date is correct
- Updates every second
```

**Pass Criteria:** ✅ RTC reads and displays correct time

---

### Test 18: Temperature Sensors

Open Serial Monitor and observe:
```
Serial output should show:
"DHT22: 22.5°C, 45% humidity"
"DS18B20: 4.2°C"

Verify:
- DHT22 reads ambient temperature
- DS18B20 reads cold box temperature
- Values update every 30 seconds
- Numbers are realistic (not 0 or 999)
```

**Pass Criteria:** ✅ Both temperature sensors read correctly

---

### Test 19: Door Sensor

```
Serial output baseline:
"Door: CLOSED"

Test by opening physical door:
"Door: OPEN"

Wait a few seconds and close:
"Door: CLOSED"

Verify:
- Immediate response to door changes
- No bouncing/false triggers
```

**Pass Criteria:** ✅ Door sensor detects open/close reliably

---

### Test 20: GSM Module Connection

Open Serial Monitor and observe for:
```
"GSM Status: Initializing..."
"GSM Status: +CREG: 0,1" (Connected to network)
"GSM Status: Signal strength: 20"

Or if roaming:
"GSM Status: +CREG: 0,5" (Roaming)

Expected within 30 seconds of boot.
```

**Pass Criteria:** ✅ GSM connects to cellular network

---

### Test 21: Buzzer Test

In Serial Monitor, send:
```
BUZZ_TEST
```

Expected: Buzzer produces continuous sound for 2 seconds

**Pass Criteria:** ✅ Buzzer activates on command

---

### Test 22: Solenoid Test

In Serial Monitor, send:
```
SOLENOID_TEST
```

Expected:
1. Buzzing/clicking sound (solenoid activating)
2. Door becomes unlocked
3. After 3 seconds, returns to locked state
4. Serial monitor shows: "Solenoid test complete"

**Pass Criteria:** ✅ Solenoid locks and unlocks

---

### Test 23: Alarm Trigger Test

In Serial Monitor, set a schedule for 1 minute from now, then wait:
```
At scheduled time:
"Alarm triggered!"
"Unlocking solenoid..."
"Buzzer activated"
"LED on"

In LCD:
"TAKE MEDICINE"
"[Alert displayed]"
```

Open door:
```
"Door sensor detected!"
"Stopping alarm..."
Serial shows completion
```

**Pass Criteria:** ✅ Complete alarm sequence works

---

### Test 24: GSM SMS Sending

When alarm triggers, verify SMS received:
```
Expected SMS:
"+94777154321: Medicine time! Please take your medication at 08:30"

And after door opens:
"+94777154321: Medication acknowledged ✓ Timestamp: 08:35"
```

**Pass Criteria:** ✅ SMS notifications send successfully

---

## ⚡ Hardware Component Testing

### Test 25: LCD Wiring

Verify connections:
- [ ] PSB pin → GND (serial mode)
- [ ] VCC → 5V
- [ ] GND → GND
- [ ] RS → D10
- [ ] RW → D11
- [ ] E → D13
- [ ] RST → D8
- [ ] BLA → 5V (backlight +)
- [ ] BLK → GND (backlight -)

**Pass Criteria:** ✅ All pins connected correctly

---

### Test 26: RTC Wiring

Verify connections:
- [ ] VCC → 5V
- [ ] GND → GND
- [ ] SDA → SDA header (near AREF)
- [ ] SCL → SCL header (near AREF)
- [ ] 32K crystal installed

**Pass Criteria:** ✅ RTC responds to I2C

---

### Test 27: DHT22 Sensor

Verify connections:
- [ ] VCC → 5V
- [ ] GND → GND
- [ ] DATA → A0
- [ ] 4.7K pull-up resistor from A0 to 5V

**Pass Criteria:** ✅ Temperature and humidity readings accurate

---

### Test 28: DS18B20 Sensor

Verify connections:
- [ ] VCC → 5V (or parasitic)
- [ ] GND → GND
- [ ] DATA → A1
- [ ] 4.7K pull-up resistor from A1 to 5V

**Pass Criteria:** ✅ Temperature readings agree with environment

---

### Test 29: Door Sensor

Verify connections:
- [ ] Contact 1 → D2
- [ ] Contact 2 → GND

Test with multimeter:
- Closed: Continuity (0 Ω)
- Open: No continuity (∞ Ω)

**Pass Criteria:** ✅ Door sensor electrical path verified

---

### Test 30: Buzzer Circuit

Test with multimeter on buzzer:
- [ ] +5V when D7 = HIGH
- [ ] 0V when D7 = LOW
- [ ] Current < 100mA (check transistor doesn't burn)

Listen for sound:
- [ ] Clear beeping when active
- [ ] Stops immediately when inactive
- [ ] Same volume as expected

**Pass Criteria:** ✅ Buzzer produces audible tones

---

### Test 31: Solenoid Lock

Test with multimeter on solenoid:
- [ ] ~12V across solenoid when D5 = HIGH
- [ ] ~0V when D5 = LOW
- [ ] Diode (1N4007) prevents back-voltage spikes

Physical test:
- [ ] Door locks securely when powered off (spring-loaded)
- [ ] Door unlocks when solenoid energized
- [ ] No sparking or burning smells

**Pass Criteria:** ✅ Solenoid electrically and mechanically sound

---

### Test 32: Peltier Cooler (TEC)

Test with multimeter:
- [ ] ~12V across TEC when D3 = HIGH
- [ ] ~0V when D3 = LOW
- [ ] Diode (1N4007) prevents back-voltage

Physical test:
- [ ] Cold surface (~0-5°C) when powered for 1 minute
- [ ] Hot surface on other side (radiator side)
- [ ] Stops cooling when powered off

**Pass Criteria:** ✅ Peltier cools box to target temperature

---

### Test 33: GSM Module SIM800L

Physical connection:
- [ ] USB power adapter or reliable 5V supply
- [ ] 1000µF capacitor near VCC
- [ ] SIM card inserted and activated
- [ ] Antenna connected
- [ ] TX/RX crossover verified
  - Arduino D0 receives GSM TX
  - Arduino D1 sends to GSM RX

Test with multimeter:
- [ ] VCC shows ~4.5-5.0V
- [ ] RX/TX pins show PWM signals during communication

**Pass Criteria:** ✅ GSM module powered and ready for communication

---

## 🔄 End-to-End Integration Testing

### Test 34: Complete User Workflow

**Scenario:** New user registers and receives first medication reminder

Step 1: User Registration
```bash
# API registers user
curl -X POST "http://localhost/api/auth/register" \
  -d '{"name":"Alice","age":50,"phone":"0775555555","mac_address":"11:22:33:44:55:66"}'
# Expect: USER_ID returned
```

Step 2: Create Schedule
```bash
# API creates medicine schedule for 1 minute from now
curl -X POST "http://localhost/api/schedule/create" \
  -d '{...schedule data for next minute...}'
# Expect: SCHEDULE_ID returned
```

Step 3: Arduino Checks Schedule
```
Arduino boots and automatically checks API
"Schedule found: Take medicine in 1 minute"
LCD displays countdown
```

Step 4: Alarm Triggers
```
Buzzer: Beeps continuously
LCD: "TAKE MEDICINE"
SMS: Reminder sent to +94775555555
```

Step 5: User Opens Box
```
Door sensor detects open
Alarm stops
Solenoid locks
SMS confirmation sent
Schedule marked complete in database
```

Expected result: **All steps complete without errors**

**Pass Criteria:** ✅ Complete workflow functions correctly

---

### Test 35: Multiple Schedule Handling

Create 3 schedules for today:
```
08:00 - Medicine A
12:00 - Food
18:00 - Medicine B
```

Verify:
- [ ] API returns all 3 when queried
- [ ] Arduino displays correct one at each time
- [ ] Each triggers its own alarm
- [ ] Completion status tracked separately
- [ ] Dashboard shows 3 items, completion count updates

**Pass Criteria:** ✅ Multiple schedules managed independently

---

### Test 36: Temperature Monitoring Cycle

Set system to 4°C target for 1 hour:

Verify in database temperature_logs table:
- [ ] Readings appear every 30 seconds
- [ ] Cooling status toggles (ON/OFF) based on hysteresis
- [ ] Temperature gradually approaches target
- [ ] No temperature spike or oscillation

**Pass Criteria:** ✅ Temperature control stable and efficient

---

### Test 37: Error Recovery

Scenarios to test recovery:

**WiFi/Internet Disconnection:**
```
- Arduino loses API connection
- SMS buffering continues
- When reconnected, catches up on pending commands
- No data loss
```

**Database Down:**
```
- API returns proper error response (500)
- Mobile app handles gracefully
- Arduino retries connection
- System recovers when database back online
```

**Sensor Failure:**
```
- Disconnect one sensor
- System logs error
- Alert sent if critical sensor
- System continues operating with available data
```

**Pass Criteria:** ✅ System recovers from failures gracefully

---

## 📊 Test Results Summary

Use this table to track your testing:

| Test # | Category | Test Name | Status | Notes |
|--------|----------|-----------|--------|-------|
| 1 | DB | Connection | ☐ Pass | |
| 2 | DB | Tables | ☐ Pass | |
| 3 | DB | Triggers | ☐ Pass | |
| 4 | DB | Data Integrity | ☐ Pass | |
| 5 | DB | Indexes | ☐ Pass | |
| 6 | API | Server Connection | ☐ Pass | |
| 7 | API | CORS | ☐ Pass | |
| 8 | API | User Registration | ☐ Pass | |
| 9 | API | User Verification | ☐ Pass | |
| 10 | API | Schedule Creation | ☐ Pass | |
| 11 | API | Schedule Retrieval | ☐ Pass | |
| 12 | API | Temperature | ☐ Pass | |
| 13 | API | User Profile | ☐ Pass | |
| 14 | API | Error Handling | ☐ Pass | |
| 15 | Arduino | Serial Connection | ☐ Pass | |
| 16 | Arduino | LCD Display | ☐ Pass | |
| 17 | Arduino | RTC | ☐ Pass | |
| 18 | Arduino | Temperature Sensors | ☐ Pass | |
| 19 | Arduino | Door Sensor | ☐ Pass | |
| 20 | Arduino | GSM Connection | ☐ Pass | |
| 21 | Arduino | Buzzer | ☐ Pass | |
| 22 | Arduino | Solenoid | ☐ Pass | |
| 23 | Arduino | Alarm Trigger | ☐ Pass | |
| 24 | Arduino | SMS Sending | ☐ Pass | |
| 25 | Hardware | LCD Wiring | ☐ Pass | |
| 26 | Hardware | RTC Wiring | ☐ Pass | |
| 27 | Hardware | DHT22 | ☐ Pass | |
| 28 | Hardware | DS18B20 | ☐ Pass | |
| 29 | Hardware | Door Sensor | ☐ Pass | |
| 30 | Hardware | Buzzer | ☐ Pass | |
| 31 | Hardware | Solenoid | ☐ Pass | |
| 32 | Hardware | Peltier | ☐ Pass | |
| 33 | Hardware | GSM Module | ☐ Pass | |
| 34 | E2E | User Workflow | ☐ Pass | |
| 35 | E2E | Multiple Schedules | ☐ Pass | |
| 36 | E2E | Temperature Control | ☐ Pass | |
| 37 | E2E | Error Recovery | ☐ Pass | |

---

## ✅ Final Validation Checklist

Before going to production:

- [ ] All 37 tests passed
- [ ] No error messages in logs
- [ ] Database backups working
- [ ] HTTPS/SSL configured
- [ ] Firewall rules applied
- [ ] Monitoring alerts set
- [ ] Documentation current
- [ ] Team trained on system
- [ ] Disaster recovery tested
- [ ] Load testing passed
- [ ] Security audit completed
- [ ] User acceptance testing done
- [ ] Sign-off from manager

**Final Status:** ☐ **APPROVED FOR PRODUCTION**

---

## 📞 Troubleshooting Failed Tests

If any test fails, refer to:
- [QUICK_START_GUIDE.md](QUICK_START_GUIDE.md) - Common issues
- [ARDUINO_SETUP_GUIDE.md](ARDUINO_SETUP_GUIDE.md) - Hardware troubleshooting
- [API_DEPLOYMENT_GUIDE.md](API_DEPLOYMENT_GUIDE.md) - Server issues
- [SYSTEM_DOCUMENTATION.md](SYSTEM_DOCUMENTATION.md) - Technical reference

---

**Document Version:** 1.0.0  
**Created:** April 13, 2026  
**Purpose:** Comprehensive testing guide for Smart Medi Box system
