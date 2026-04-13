# Smart Medi Box - Arduino Implementation Guide

## 📦 Required Libraries

### Install via Arduino Library Manager

1. **U8g2** (LCD Display)
   - Author: Oliver Krause
   - Search for: "U8g2"
   - Install latest version

2. **RTClib** (Real-Time Clock)
   - Author: Adafruit
   - Search for: "RTClib"
   - Install latest version

3. **DHT** (Temperature/Humidity)
   - Author: Adafruit
   - Search for: "DHT"
   - Install latest version

4. **DallasTemperature** (DS18B20)
   - Author: Miles Burton
   - Search for: "DallasTemperature"
   - Install latest version

5. **OneWire** (DS18B20 Protocol)
   - Author: Paul Stoffregen
   - Search for: "OneWire"
   - Install latest version

6. **Servo** (Built-in)
   - No installation needed

### Optional Libraries

7. **MFRC522** (for RFID RC522)
   - Author: Miguel Balboa
   - Search for: "MFRC522"

8. **SPI** (Built-in)
   - No installation needed

---

## ⚙️ Arduino Configuration

### Board Selection
```
Tools → Board → Arduino Leonardo
Tools → Port → COM3 (or your port)
Tools → Upload Speed → 57600
```

### Serial Port Configuration
- **Serial** (USB): 9600 baud - Debug output
- **Serial1** (D0/D1): 9600 baud - GSM module

### Memory Considerations
- Flash: 32 KB (Leonardo)
- SRAM: 2.5 KB
- Current sketch size: ~20 KB

---

## 🔧 Configuration Variables

Update these in the Arduino sketch:

```cpp
// ==================== CONFIGURATION ====================

// Server Details (for GSM HTTP requests)
const char* SERVER_URL = "http://your-server.com/api";
const char* GSM_APN = "gprs.mobitel.lk";      // Sri Lanka carriers
const char* GSM_USER = "";
const char* GSM_PASS = "";

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

---

## 🔌 Pin Wiring Validation Checklist

### LCD (ST7920 128x64)
- [ ] PSB → GND (Serial mode)
- [ ] VCC → 5V
- [ ] GND → GND
- [ ] RS (Register Select) → D10
- [ ] RW (Read/Write) → D11
- [ ] E (Enable) → D13
- [ ] RST (Reset) → D8
- [ ] BLA (Backlight +) → 5V
- [ ] BLK (Backlight -) → GND

### GSM (SIM800L)
- [ ] VCC → 5V (with 1000µF capacitor)
- [ ] GND → GND (common with Arduino)
- [ ] TX → D0 (crossover)
- [ ] RX → D1 (crossover)
- [ ] SIM card installed
- [ ] Antenna connected

### RTC (DS3231)
- [ ] VCC → 5V
- [ ] GND → GND
- [ ] SDA → SDA header (near AREF)
- [ ] SCL → SCL header (near AREF)

### Temperature Sensors
- **DHT22 (A0):**
  - [ ] VCC → 5V
  - [ ] GND → GND
  - [ ] DATA → A0
  - [ ] 4.7K pull-up resistor (A0 to 5V)

- **DS18B20 (A1):**
  - [ ] VCC → 5V
  - [ ] GND → GND
  - [ ] DATA → A1
  - [ ] 4.7K pull-up resistor (A1 to 5V)

### RFID (RC522)
- [ ] VCC → 3.3V (NOT 5V!)
- [ ] GND → GND
- [ ] MOSI → ICSP MOSI
- [ ] MISO → ICSP MISO
- [ ] SCK → ICSP SCK
- [ ] SDA/CS → D9
- [ ] RST → D6

### Digital Control
- **Door Sensor (D2):**
  - [ ] GND → GND
  - [ ] Switch → D2

- **Solenoid (D5 via MOSFET):**
  - [ ] D5 → 1K resistor → MOSFET GATE
  - [ ] MOSFET SOURCE → GND
  - [ ] MOSFET DRAIN → Solenoid (-)
  - [ ] Solenoid (+) → 12V supply
  - [ ] 1N4007 diode across solenoid

- **Buzzer (D7 via BC337):**
  - [ ] D7 → 1K resistor → BC337 BASE
  - [ ] BC337 EMITTER → GND
  - [ ] BC337 COLLECTOR → Buzzer (-)
  - [ ] Buzzer (+) → 5V
  - [ ] 1N4148 diode across buzzer

- **Cooling (D3 via MOSFET):**
  - [ ] D3 → 1K resistor → MOSFET GATE
  - [ ] MOSFET SOURCE → GND
  - [ ] MOSFET DRAIN → TEC (-)
  - [ ] TEC (+) → 12V supply
  - [ ] 100K pulldown on GATE
  - [ ] 1N4007 diode across TEC

- **Servo (A2):**
  - [ ] Signal → A2 (digital pin 20)
  - [ ] VCC → 5V
  - [ ] GND → GND

---

## 🧪 Testing Procedure

### 1. Serial Monitor Test
```
Open: Tools → Serial Monitor
Baud: 9600
Expected: System initialization messages
```

### 2. LCD Display Test
```
Should display: "System Init..." → "Ready"
Check: Font, brightness, contrast
```

### 3. RTC Test
```
Serial output should show current time
If fails: Check I2C wiring, verify address 0x68
```

### 4. Sensor Tests
- **Temperature:** Monitor actual vs displayed readings
- **Humidity:** DHT22 should show 30-80%
- **Internal Temp:** DS18B20 should match environment

### 5. Door Sensor Test
```cpp
Open/Close door and monitor serial output:
"Door OPEN" / "Door CLOSED"
```

### 6. Buzzer Test
```cpp
Should beep in testing mode
Check: Volume, consistency
```

### 7. Solenoid Test
```cpp
Should click/buzz when D5 goes HIGH
Should be locked (LOW) by default
```

### 8. GSM Module Test
```
Serial1 commands:
AT          → Should respond OK
AT+CMGF=1   → Should respond OK
AT+COPS?    → Should show network info
```

---

## 📱 Mobile App Integration

### QR Code Format
```
User will scan QR generated by Android app containing:
AUTH|USER_ID|MAC_ADDRESS|DEVICE_ID

Or for new user:
NEW_USER|NAME|AGE|PHONE|MAC_ADDRESS
```

### Expected Flow
1. User launches mobile app
2. Selects "Authenticate" or "Register"
3. Arduino displays QR code on LCD
4. User scans QR with mobile camera
5. App processes authentication
6. Arduino receives response via GSM
7. System loads user data

---

## 🚀 Upload & Deployment

### Pre-Upload Checklist
- [ ] All libraries installed
- [ ] Arduino Leonardo selected as board
- [ ] COM port correct
- [ ] Baud rate: 57600
- [ ] All pin assignments verified
- [ ] Server URL configured
- [ ] GSM APN configured

### Upload Process
```
Arduino IDE → Sketch → Upload
Or: Ctrl+U

Expected: Compiling → Uploading → Done
```

### Post-Upload Verification
1. Open Serial Monitor
2. Look for initialization messages
3. Verify RTC time displays
4. Test door sensor
5. Check GSM connection message

---

## 🔄 Common Issues & Solutions

### Compilation Errors

**Error:** `U8g2 not found`
- **Solution:** Install U8g2 library via Library Manager

**Error:** `RTClib not found`
- **Solution:** Install Adafruit RTClib library

**Error:** `no matching function for call`
- **Solution:** Verify library versions match Arduino IDE version

### Runtime Issues

**Issue:** LCD shows garbage characters
- **Solution:** Verify PSB to GND, check SPI pins
- Try: Adjust contrast with u8g2.setContrast()

**Issue:** RTC not responding
- **Solution:** Check SDA/SCL connections to AREF header
- Verify: I2C address is 0x68

**Issue:** Temperature readings wrong
- **Solution:** Check 4.7K pullup resistors
- Verify: OneWire and DallasTemperature libraries match

**Issue:** Buzzer always on/off
- **Solution:** Check BC337 transistor polarity
- Verify: 1K base resistor present

**Issue:** No GSM response**
- **Solution:** Check crossover TX/RX wiring
- Verify: 1000µF capacitor near VCC
- Check: SIM card and antenna

### Memory Issues

**Error:** Sketch too large
- **Solution:** Disable DEBUG_MODE
- Remove unused serial output
- Use PROGMEM for string literals

---

## 📋 Testing Code Snippets

### Test Buzzer
```cpp
void testBuzzer() {
  digitalWrite(BUZZER_PIN, HIGH);
  delay(500);
  digitalWrite(BUZZER_PIN, LOW);
  delay(500);
}
```

### Test Solenoid
```cpp
void testSolenoid() {
  digitalWrite(SOLENOID_PIN, HIGH);  // Unlock
  delay(2000);
  digitalWrite(SOLENOID_PIN, LOW);   // Lock
}
```

### Test LCD Display
```cpp
void testLCD() {
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_ncenB14_tr);
  u8g2.drawStr(0, 20, "LCD TEST");
  u8g2.setFont(u8g2_font_ncenB08_tr);
  u8g2.drawStr(0, 40, "All systems OK");
  u8g2.sendBuffer();
}
```

### Test RTC
```cpp
void testRTC() {
  DateTime now = rtc.now();
  Serial.print("RTC Time: ");
  Serial.print(now.hour());
  Serial.print(":");
  Serial.println(now.minute());
}
```

---

## 🔐 Security Notes

1. **Change server URL** before deployment
2. **Disable DEBUG_MODE** in production
3. **Use HTTPS** for API calls (if supported by GSM)
4. **Validate all incoming data** from API
5. **Never hardcode** user credentials
6. **Log all actions** for audit trail

---

## 📊 Performance Tips

1. **Minimize serial output** (affects RAM)
2. **Use arrays** instead of Strings when possible
3. **Reduce LCD update frequency**
4. **Implement sleep modes** for power efficiency
5. **Cache schedule data** locally

---

## 🆘 Support Resources

- **U8g2 Documentation:** https://github.com/olikraus/u8g2/wiki
- **Arduino Leonardo:** https://docs.arduino.cc/hardware/leonardo
- **RTClib:** https://github.com/adafruit/RTClib
- **SIM800L AT Commands:** https://cdn-shop.adafruit.com/datasheets/SIM800L_AT_Command_Manual_V1.01.pdf

---

**Version:** 2.0.0  
**Updated:** April 13, 2026
