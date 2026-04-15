# Smart Medi Box - Dual Microcontroller Architecture

## Overview

The Smart Medi Box now uses a **split architecture** to overcome Arduino Leonardo's memory limitations:

- **Arduino Leonardo**: Handles all sensors, motors, and local hardware control
- **ESP32**: Handles GSM/GPRS communication and server integration

They communicate via **Serial UART** with a simple JSON protocol.

---

## Hardware Setup

### Arduino Leonardo (Sensor Controller)
```
Connections:
- Serial0 TX (D1) → ESP32 RX0 (GPIO3)
- Serial0 RX (D0) → ESP32 TX0 (GPIO1)
- All sensors connected as before (DHT, DS18B20, RFID, etc.)
```

### ESP32 (GSM Gateway)
```
Connections:
- RX0 (GPIO3) ← Leonardo TX (D1)
- TX0 (GPIO1) → Leonardo RX (D0)

GSM Module (SIM800L):
- RX (GPIO16) ← SIM800L TX
- TX (GPIO17) → SIM800L RX
```

### Complete Pin Mapping
```
LEONARDO:
- A0: DHT22 (temperature/humidity)
- A1: DS18B20 (backup temperature)
- A2: Servo (solenoid release)
- D2: Door sensor
- D3: Cooling system (PWM)
- D4: RFID (MISO on SPI)
- D5: Solenoid lock (PWM)
- D6: STEPPER_IN1
- D7: Buzzer
- D10: LCD CS (SPI)
- D11: STEPPER_IN3
- D12: STEPPER_IN2
- D13: STEPPER_IN4 (also LED, SPI CLK)
- SPI: MFRC522 RFID (CS=D4)
- I2C: RTC DS3231 (SDA=D2, SCL=D3)

ESP32:
- GPIO0: Leonardo RX (Serial0)
- GPIO1: Leonardo TX (Serial0)
- GPIO16: SIM800L TX (Serial2)
- GPIO17: SIM800L RX (Serial2)
```

---

## Installation Steps

### 1. Upload Leonardo Sketch
```
1. Connect Arduino Leonardo to computer via USB
2. Open Arduino IDE
3. Select Board: Arduino Leonardo
4. Open: leonardo_sensors.ino
5. Sketch → Upload
6. Expected size: ~12KB (well under 28KB limit)
```

### 2. Upload ESP32 Sketch
```
1. Install ESP32 board support if not already installed:
   - Tools → Board Manager
   - Search "ESP32" by Espressif
   - Install

2. Connect ESP32 to computer via USB
3. Select Board: ESP32 Dev Module
4. Open: esp32_gateway.ino
5. Configure:
   - GSM_APN = your provider's APN (e.g., "hutch3g" for Sri Lanka)
   - SERVER_HOST = your server (already set to render.com)
6. Sketch → Upload
7. Expected size: ~50KB (fits in 4MB easily)
```

### 3. Connect Leonardo and ESP32
Wire the Serial connection between boards:
- Leonardo D1 (TX) → ESP32 GPIO3 (RX0)
- Leonardo D0 (RX) → ESP32 GPIO1 (TX0)
- Common GND between both boards

### 4. Connect GSM Module to ESP32
- SIM800L GND → ESP32 GND
- SIM800L VCC → ESP32 5V (via buck converter recommended)
- SIM800L TX → ESP32 GPIO16
- SIM800L RX → ESP32 GPIO17

---

## Communication Protocol

### Leonardo → ESP32 (Sensor Data)
Leonardo sends sensor readings every 5 seconds:
```json
{
  "action": "data",
  "temp": 25.5,
  "humidity": 60.2,
  "door_open": false,
  "alarm_active": false
}
```

### ESP32 → Leonardo (Commands)
ESP32 can send commands to Leonardo:
```
Commands:
- trigger_alarm     - Start alarm and unlock solenoid
- stop_alarm        - Stop alarm, lock solenoid
- dispense          - Rotate stepper motor for medicine dispensing
- buzzer_on         - Enable buzzer
- buzzer_off        - Disable buzzer
- lock              - Lock solenoid
- unlock            - Unlock solenoid
```

### ESP32 → Server (HTTP/JSON)
ESP32 handles all server communication:
```
Endpoints:
POST /api/device/register     - Device registration with IMEI
POST /api/device/status       - Send sensor data + status
POST /api/device/heartbeat    - Keep-alive signal
POST /api/temp/log            - Temperature logging
POST /api/schedule/data       - Send schedule events
```

---

## Features & Benefits

### Leonardo (Sensor Controller)
✅ **Lightweight**: Only 12KB (was 202% before)
✅ **Fast Response**: Immediate sensor reading & control
✅ **Reliable**: Proven Arduino platform
✅ **Low Power**: Can be battery-backed
❌ No server communication (handled by ESP32)
❌ No JSON parsing (simple serial protocol)

### ESP32 (Gateway)
✅ **Powerful**: 4MB flash, 520KB RAM
✅ **GSM/GPRS**: Full cellular connectivity
✅ **JSON Support**: Full ArduinoJson library
✅ **Dual Core**: Background processing capability
✅ **WiFi Ready**: Can be extended for WiFi later
✅ **Server Integration**: All cloud communication

---

## Troubleshooting

### Leonardo not responding
- Check Serial connection (D0/D1 to ESP32 GPIO0/GPIO1)
- Verify baud rate is 9600 on both
- Check Leonardo uploads successfully (12KB size)
- Monitor Serial0 with Arduino IDE

### ESP32 not communicating with Leonardo
```
Check Pin Connections:
Leonardo D1 → ESP32 GPIO1 (TX0 input)
Leonardo D0 → ESP32 GPIO3 (RX0 output)
```

### GSM module not connecting
- Verify SIM card is inserted correctly
- Ensure SIM has active data plan
- Check APN setting for your provider
- Monitor Serial2 output from ESP32

### Server not receiving data
- Check ESP32 connected to GPRS
- Monitor HTTP POST responses
- Verify SERVER_HOST is correct
- Check firewall/NAT not blocking port 80

---

## Future Enhancements

1. **Add WiFi to ESP32**: Can switch between GSM and WiFi
2. **Battery Backup**: Add battery monitoring to Leonardo
3. **OTA Updates**: Update firmware wirelessly via ESP32
4. **Local Storage**: SD card on ESP32 for offline logging
5. **SMS Notifications**: Send SMS from ESP32 directly
6. **Mobile App**: Connect directly to ESP32 WiFi hotspot

---

## File Structure
```
smart-medi-box/
├── leonardo_sensors.ino       ← Upload to Arduino Leonardo
├── esp32_gateway.ino          ← Upload to ESP32
├── ARCHITECTURE.md            ← This file
└── [other project files]
```

---

## Testing

### Test Leonardo Alone
1. Upload leonardo_sensors.ino
2. Open Serial Monitor (9600 baud)
3. Trigger RFID card near reader
4. Should see: `[DEBUG] RFID detected`
5. Buzzer should sound, stepper should rotate
6. Door sensor should show: `[DEBUG] Door: OPEN/CLOSED`

### Test ESP32 Alone
1. Upload esp32_gateway.ino
2. Open Serial Monitor (115200 baud)
3. Should see: `[ESP32] Initializing GSM Module...`
4. After ~30 seconds: `[ESP32] GPRS Connected`
5. Should see: `[ESP32] Device registered successfully`

### Full Integration Test
1. Both boards uploaded
2. Serial connection verified
3. Send command from ESP32: `trigger_alarm`
4. Leonardo should sound buzzer + unlock solenoid
5. RFID trigger should dispense medicine
6. Sensor data appears in server logs

---

## Support & Documentation

See these files for more info:
- API_DEPLOYMENT_GUIDE.md - Server API details
- WIRING_MASTER_SHEET.md - Complete wiring diagram
- TESTING_GUIDE.md - Test procedures
