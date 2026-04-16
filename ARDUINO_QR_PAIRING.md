# Arduino QR Code Pairing Implementation

## Overview
The Arduino ESP32 displays a QR code on the TFT display that contains the pairing token. The patient scans this with their mobile phone to complete device pairing.

## Flow

```
1. Arduino boots without credentials
2. Calls: POST /api/auth/generate-pairing-token
   - No token needed (standalone device mode)
3. Receives: { "pairing_token": "d010849..." }
4. Generates QR code from pairing_token string
5. Displays QR on TFT screen
6. Patient scans QR with mobile phone
7. Dashboard completes pairing: POST /api/auth/complete-pairing
   - pairing_token: (from QR scan)
   - mac_address: (Arduino's MAC)
   - device_name: "Smart Medi Box"
8. Arduino stores credentials to EEPROM
9. Becomes operational
```

## Backend Changes (DONE ✅)

### Pairing Token Generation
**POST /api/auth/generate-pairing-token**

```php
// Generate pairing token without requiring auth token
// (Device can call this in standalone mode)
$pairing_token = bin2hex(random_bytes(32));
// Returns: { "pairing_token": "...", "expires_in": 3600 }
```

Current implementation requires auth token, but should be updated to support:
1. **Mode 1**: With auth token (for already paired devices)
2. **Mode 2**: Without auth (for initial pairing - NEW DEVICE)

## Arduino Implementation Required

### Libraries Needed
```cpp
#include <qrcode.h>  // For QR code generation
// or use external QR service:
// https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=TOKEN
```

### QR Code Generation Methods

#### Option A: Use Online QR Generator (Simplest)
```cpp
// Generate pairing token
String pairingToken = generatePairingToken(); // "d010849..."

// Display as QR using online API
String qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" + 
               URLEncode(pairingToken);

// Download image and display on TFT
displayQRFromURL(qrUrl);
```

**Pros:**
- No additional libraries
- Works with any display
- Can download and cache QR image

**Cons:**
- Requires internet during pairing
- Server dependent

#### Option B: Local QR Library (Recommended)
```cpp
#include <qrcode.h>

void displayQRCode(String data) {
  QRCode qrcode;
  uint8_t qrcodedata[150];
  
  qrcode_init(&qrcode, qrcodedata, QR_VERSION_7, ECC_HIGH);
  qrcode_addData(&qrcode, data.c_str());
  qrcode_make(&qrcode);
  
  // Draw on TFT display
  int startX = 20, startY = 20;
  int moduleSize = 5; // 5 pixels per module
  
  for (int y = 0; y < qrcode.modules; y++) {
    for (int x = 0; x < qrcode.modules; x++) {
      if (qrcode_getModule(&qrcode, x, y)) {
        tft.fillRect(startX + x * moduleSize, 
                     startY + y * moduleSize, 
                     moduleSize, moduleSize, TFT_BLACK);
      }
    }
  }
}
```

**Installation:**
```bash
arduino-cli lib install QRCode
```

### Complete Pairing Function

```cpp
void initiatePairing() {
  Serial.println("Starting device pairing...");
  
  // Step 1: Request pairing token from backend
  HTTPClient http;
  http.begin(API_URL "/index.php/api/auth/generate-pairing-token");
  http.addHeader("Content-Type", "application/json");
  
  DynamicJsonDocument doc(256);
  // No auth required - device mode
  String payload;
  serializeJson(doc, payload);
  
  int httpCode = http.POST(payload);
  
  if (httpCode == 201) {
    DynamicJsonDocument response(512);
    deserializeJson(response, http.getString());
    
    String pairingToken = response["pairing_token"].as<String>();
    Serial.println("Pairing Token: " + pairingToken);
    
    // Save to EEPROM temporarily
    EEPROM.writeString(EEPROM_QR_TOKEN_ADDR, pairingToken);
    EEPROM.commit();
    
    // Step 2: Display QR code on TFT
    displayQRCode(pairingToken);
    
    // Step 3: Show instructions
    tft.println("Scan this QR code with your phone");
    tft.println("to complete device pairing");
    
    // Step 4: Wait for user action
    // (In real implementation: listen for button press or timeout)
    waitForPairingCompletion(pairingToken);
    
  } else {
    Serial.println("Failed to get pairing token: " + String(httpCode));
  }
  
  http.end();
}

void waitForPairingCompletion(String pairingToken) {
  unsigned long startTime = millis();
  unsigned long timeout = 3600000; // 1 hour
  
  while (millis() - startTime < timeout) {
    // Check if user pressed "Pairing Complete" button
    if (checkPairingButton()) {
      // Arduino is now ready for commands
      // Backend has already been updated when phone completed pairing
      Serial.println("Device pairing completed!");
      isAuthenticated = true;
      break;
    }
    
    delay(100);
  }
  
  if (!isAuthenticated) {
    Serial.println("Pairing timeout");
    // Restart pairing
    initiatePairing();
  }
}
```

## Dashboard Flow (Already Implemented ✅)

When user clicks "Add Device":
1. Dashboard calls: `/api/auth/generate-pairing-token` (WITH auth token)
2. Gets pairing token
3. Shows to user: "Share this token with your device"
4. Arduino displays QR of same token
5. User scans QR → Dashboard captures token
6. Dashboard completes: `/api/auth/complete-pairing`
   ```javascript
   {
     pairing_token: "d010849...",
     mac_address: "AA:BB:CC:DD:EE:FF",
     device_name: "Smart Medi Box"
   }
   ```

## Required Changes to Backend

### Update Pairing Token Generation
The endpoint should support TWO modes:

```php
POST /api/auth/generate-pairing-token

// Mode 1: With authentication (existing)
{
  "token": "user_auth_token"
}

// Mode 2: New Device (no auth)
// Arduino can call without token
// Server auto-creates temporary patient record
```

### Updated Handler

```php
function handleGeneratePairingToken($method) {
    global $conn;
    
    if ($method !== 'POST') {
        return errorResponse(405, 'Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['token'] ?? null;
    
    $user_id = null;
    $patient_id = null;
    
    // Mode 1: Authenticated mode
    if ($token) {
        // Existing code...
        $query = "SELECT user_id FROM auth_tokens WHERE token = $1 AND expires_at > CURRENT_TIMESTAMP";
        $result = pg_query_params($conn, $query, [$token]);
        
        if (pg_num_rows($result) > 0) {
            $token_row = pg_fetch_assoc($result);
            $user_id = $token_row['user_id'];
            
            // Get patient_id...
        }
    } 
    // Mode 2: Unauthenticated (New Device)
    else {
        // Create temporary patient record for unknown user
        $unique_id = "TEMP_" . uniqid();
        
        $query = "INSERT INTO users (email, password_hash, role) 
                  VALUES ($1, $2, 'PATIENT') RETURNING id";
        $result = pg_query_params($conn, $query, [$unique_id . '@temp.local', 'UNKNOWN']);
        $user = pg_fetch_assoc($result);
        $user_id = $user['id'];
        
        // Create patient profile...
        $query = "INSERT INTO patients (user_id, nic, name, date_of_birth, gender, blood_type) 
                  VALUES ($1, $2, $3, $4, $5, $6) RETURNING id";
        $result = pg_query_params($conn, $query, [$user_id, 'UNKNOWN', 'Arduino Device', '1990-01-01', 'OTHER', 'UNKNOWN']);
        $patient = pg_fetch_assoc($result);
        $patient_id = $patient['id'];
    }
    
    // Generate pairing token (same for both modes)
    $pairing_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $query = "INSERT INTO pairing_tokens (patient_id, token, expires_at, is_used) 
              VALUES ($1, $2, $3, false) RETURNING token";
    $result = pg_query_params($conn, $query, [$patient_id, $pairing_token, $expires_at]);
    
    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }
    
    http_response_code(201);
    echo json_encode([
        'status' => 'SUCCESS',
        'pairing_token' => $pairing_token,
        'expires_in' => 3600
    ]);
}
```

## Testing Checklist

- [ ] Arduino can request pairing token via HTTP POST
- [ ] Pairing token returned correctly
- [ ] QR code generates from token string
- [ ] QR displays on TFT screen
- [ ] User can scan QR with phone camera
- [ ] Dashboard recognizes token from QR scan
- [ ] Complete pairing succeeds
- [ ] Arduino stores MAC address + pairing info
- [ ] Device becomes operational after pairing
- [ ] All schedules/commands work post-pairing

## Example QR Data
The QR code contains just the pairing token string:
```
d010849306d45a9a21338f9cef68721d8cc24d43f5d68c5e5e1c8dce2a8a0c14
```

When scanned, this token is sent to backend to complete pairing.
