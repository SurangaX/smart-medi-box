# Device Integration Guide - New Pairing System

## Overview

The Arduino/ESP32 devices now use token-based pairing instead of QR codes. This guide explains how to update your device firmware to work with the new authentication system.

## Device Pairing Flow

### Old System (QR Code)
Device sends QR code → App scans QR → Device registered

### New System (Token-Based)
User generates pairing token → User shares token with device → Device uses token to register

## Updated Device Registration Process

### Step 1: Patient generates pairing token
1. Patient logs in to dashboard
2. Goes to "Devices" section
3. Clicks "Add Device"
4. Pairing token is generated (valid for 1 hour)
5. Token is displayed to user

### Step 2: Share pairing token with device
Patient manually enters pairing token on device via:
- Serial terminal connection
- Web interface on device (if equipped)
- Mobile app (if device has connected app)

### Step 3: Device completes pairing
Device sends pairing request with token to server

## Arduino/ESP32 Firmware Update

### Add WiFi/GSM Configuration Screen

Create a simple configuration interface where user can input:
```
=== Smart Medi Box Configuration ===
1. Enter Pairing Token: [______________________________]
2. Enter Device Name: [______________________________]
3. Connect

Status: Waiting for input...
```

### Update Arduino Code

Replace the old QR code detection with token-based registration:

```cpp
// ==================== PAIRING SECTION ====================

const char* API_BASE_URL = "/api";
const char* SERVER_HOST = "smart-medi-box.onrender.com";
const uint16_t SERVER_PORT = 80;

String pairingToken = "";
String deviceName = "Smart Medi Box";
String deviceMAC = "";

// Get device MAC address
void getDeviceMAC() {
  byte mac[6];
  WiFi.macAddress(mac);
  
  char macStr[18];
  sprintf(macStr, "%02X:%02X:%02X:%02X:%02X:%02X",
    mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
  
  deviceMAC = String(macStr);
  debugPrint("Device MAC: " + deviceMAC);
}

// Handle pairing via serial input
void handlePairingInput() {
  if (Serial.available()) {
    String input = Serial.readStringUntil('\n');
    input.trim();
    
    if (input.startsWith("TOKEN:")) {
      pairingToken = input.substring(6);
      debugPrint("Token received: " + pairingToken);
      attemptPairing();
    }
    else if (input.startsWith("NAME:")) {
      deviceName = input.substring(5);
      debugPrint("Device name set: " + deviceName);
    }
    else if (input.equals("STATUS")) {
      printPairingStatus();
    }
  }
}

// Attempt to pair device with server
void attemptPairing() {
  if (pairingToken.length() == 0) {
    debugPrint("No pairing token provided");
    return;
  }
  
  if (!status.gprsConnected) {
    debugPrint("GPRS not connected, cannot pair");
    return;
  }
  
  debugPrint("Attempting to pair with server...");
  
  // Prepare request body
  String payload = "{";
  payload += "\"pairing_token\":\"" + pairingToken + "\",";
  payload += "\"mac_address\":\"" + deviceMAC + "\",";
  payload += "\"device_name\":\"" + deviceName + "\"";
  payload += "}";
  
  // Send pairing request
  String response = postRequest("/auth/complete-pairing", payload);
  
  if (response.indexOf("SUCCESS") != -1) {
    debugPrint("Device paired successfully!");
    parseDeviceID(response);
    status.serverConnected = true;
  } else {
    debugPrint("Pairing failed: " + response);
    status.serverConnected = false;
  }
}

// Parse device ID from server response
void parseDeviceID(String response) {
  int startIndex = response.indexOf("\"device_id\":\"");
  if (startIndex != -1) {
    startIndex += 13; // Length of "\"device_id\":\""
    int endIndex = response.indexOf("\"", startIndex);
    
    String deviceID = response.substring(startIndex, endIndex);
    debugPrint("Assigned Device ID: " + deviceID);
    
    // Save to EEPROM or file system for future use
    saveDeviceID(deviceID);
  }
}

// Save device ID to persistent storage
void saveDeviceID(String deviceID) {
  // For ESP32, use SPIFFS or LittleFS
  #ifdef ESP32
  SPIFFS.begin(true);
  File file = SPIFFS.open("/device_id.txt", "w");
  file.print(deviceID);
  file.close();
  SPIFFS.end();
  debugPrint("Device ID saved");
  #endif
}

// Load device ID from persistent storage
String loadDeviceID() {
  #ifdef ESP32
  SPIFFS.begin(true);
  if (SPIFFS.exists("/device_id.txt")) {
    File file = SPIFFS.open("/device_id.txt", "r");
    String deviceID = file.readString();
    file.close();
    SPIFFS.end();
    return deviceID;
  }
  SPIFFS.end();
  #endif
  return "";
}

// Print pairing status to serial
void printPairingStatus() {
  Serial.println("\n=== Device Pairing Status ===");
  Serial.println("MAC Address: " + deviceMAC);
  Serial.println("Device Name: " + deviceName);
  Serial.println("Pairing Token: " + (pairingToken.length() > 0 ? "SET" : "NOT SET"));
  Serial.println("Server Connected: " + String(status.serverConnected ? "YES" : "NO"));
  
  String savedID = loadDeviceID();
  if (savedID.length() > 0) {
    Serial.println("Device ID: " + savedID);
  } else {
    Serial.println("Device ID: NOT ASSIGNED");
  }
  Serial.println("\n--- Commands ---");
  Serial.println("TOKEN:<pairing_token> - Set pairing token");
  Serial.println("NAME:<device_name> - Set device name");
  Serial.println("STATUS - Show current status");
  Serial.println("============================\n");
}

// ==================== SETUP UPDATE ====================

void setup() {
  Serial.begin(115200);
  delay(100);
  
  debugPrint("Smart Medi Box - Device Starting");
  
  // Get device MAC address
  getDeviceMAC();
  
  // Load saved device ID if exists
  String deviceID = loadDeviceID();
  if (deviceID.length() > 0) {
    debugPrint("Loaded Device ID: " + deviceID);
    status.serverConnected = true;
  } else {
    debugPrint("No saved Device ID. Please pair this device.");
    printPairingStatus();
  }
  
  // Initialize hardware (WiFi/GSM, sensors, etc.)
  initGSM();
  
  // Try to register if device ID is known
  if (deviceID.length() > 0) {
    registerDevice();
  }
}

// ==================== MAIN LOOP UPDATE ====================

void loop() {
  unsigned long now = millis();
  
  // Handle pairing input from serial
  handlePairingInput();
  
  // Listen for data from Leonardo
  if (LeonardoSerial.available()) {
    processLeonardoData();
  }
  
  // If not paired yet, wait for pairing input
  if (!status.serverConnected) {
    delay(100);
    return;
  }
  
  // GSM health check
  if (now - status.lastGSMCheck >= GSM_CHECK_INTERVAL) {
    checkGSMConnection();
    status.lastGSMCheck = now;
  }
  
  // Server sync (send sensor data)
  if (status.gprsConnected && now - status.lastServerSync >= SYNC_INTERVAL) {
    syncWithServer();
    status.lastServerSync = now;
  }
  
  // Heartbeat
  if (status.serverConnected && now - status.lastHeartbeat >= HEARTBEAT_INTERVAL) {
    sendHeartbeat();
    status.lastHeartbeat = now;
  }
  
  delay(100);
}
```

### Serial Interface Commands

Users can interact with the device via serial monitor:

```
TOKEN:abc123def456ghi789jkl012mno345 - Set pairing token
NAME:MyDevice - Set device name
STATUS - View pairing status
```

### Example Pairing Sequence

1. **User generates token in app**: Creates 32-character hex string (valid 1 hour)
2. **User opens serial monitor** to device
3. **User sends command**:
   ```
   NAME:Smart Medi Box #1
   TOKEN:a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
   ```
4. **Device sends pairing request** to server:
   ```
   POST /api/auth/complete-pairing
   {
     "pairing_token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
     "mac_address": "AA:BB:CC:DD:EE:FF",
     "device_name": "Smart Medi Box #1"
   }
   ```
5. **Server responds** with device_id
6. **Device saves device_id** to EEPROM/SPIFFS
7. **Device is now paired** and can sync data

## Device Response to Server

Once paired, the device continues to send sensor data with its device_id:

```cpp
void syncWithServer() {
  if (!status.serverConnected) return;
  
  String deviceID = loadDeviceID();
  String payload = "{";
  payload += "\"device_id\":\"" + deviceID + "\",";
  payload += "\"temperature\":" + String(sensorData.temperature, 2) + ",";
  payload += "\"humidity\":" + String(sensorData.humidity, 2) + ",";
  payload += "\"door_open\":" + String(sensorData.doorOpen ? "true" : "false") + ",";
  payload += "\"alarm_active\":" + String(sensorData.alarmActive ? "true" : "false");
  payload += "}";
  
  postRequest("/device/status", payload);
}
```

## Troubleshooting

### Device shows "NOT PAIRED"
1. Verify patient generated pairing token in app
2. Check token is still valid (expires after 1 hour)
3. Check device is connected to GSM/WiFi
4. Try sending token again using serial command

### Device paired but data not syncing
1. Verify GSM/WiFi connection is active
2. Check server logs for device activity
3. Verify sensor data is being collected from Leonardo
4. Check API endpoint is responding

### Token expired
1. Generate new pairing token in app (old ones expire after 1 hour)
2. Send new token to device via serial
3. Device will attempt pairing again

## Hardware Requirements

- **ESP32** or Arduino with WiFi/GSM module
- **SPIFFS** or **LittleFS** for storing device_id
- **Serial connection** for initial pairing
- **WiFi** or **GSM/GPRS** for server communication

## Device States

```
┌─────────────────────────────────────────┐
│   Device Powered On - No Device ID      │
│          (UNPAIRED STATE)               │
└──────────────────┬──────────────────────┘
                   │
      User sends pairing token
               via serial
                   │
                   ▼
┌─────────────────────────────────────────┐
│  Device attempts pairing with token     │
│          (PAIRING STATE)                │
└──────────────────┬──────────────────────┘
                   │
       Server validates token
        and returns device_id
                   │
                   ▼
┌─────────────────────────────────────────┐
│  Device stores device_id to EEPROM      │
│   and syncs sensor data to server       │
│          (PAIRED STATE)                 │
└─────────────────────────────────────────┘
```

## Configuration Storage

Device stores:
- `device_id` - Assigned by server (persistent)
- `device_name` - Set by user (persistent)
- `mac_address` - Hardware address (derived each boot)
- `pairing_token` - Temporary, used only during pairing

## Testing

### Simulate Pairing on Serial Monitor

```
> NAME:Test Device
Device name set: Test Device

> STATUS
=== Device Pairing Status ===
MAC Address: A1:B2:C3:D4:E5:F6
Device Name: Test Device
Pairing Token: NOT SET
Server Connected: NO
Device ID: NOT ASSIGNED

> TOKEN:a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
Token received: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
Attempting to pair with server...

[Server processes request]

Device paired successfully!
Assigned Device ID: DEVICE-12a34b56c78d90ef

> STATUS
=== Device Pairing Status ===
MAC Address: A1:B2:C3:D4:E5:F6
Device Name: Test Device
Pairing Token: SET
Server Connected: YES
Device ID: DEVICE-12a34b56c78d90ef
```

## Files to Update

1. **arduino_esp32_gateway.ino**
   - Add pairing token handling
   - Add persistent storage for device_id
   - Update registration flow

2. **arduino_leonardo_sensors.ino**
   - No changes needed (still sends sensor data to ESP32)

## Migration from Old System

If you have devices already using QR codes:

1. Factory reset device (delete device_id from storage)
2. Device will enter unpaired state
3. Generate new pairing token in app
4. Follow normal pairing flow above

## Support

For device integration issues:
1. Check serial monitor output
2. Verify server connectivity
3. Review AUTHENTICATION_MIGRATION.md for API details
4. Check system logs on server

---

**Version**: 2.0.0  
**Status**: Ready for Implementation  
**Date**: 2026-04-16
