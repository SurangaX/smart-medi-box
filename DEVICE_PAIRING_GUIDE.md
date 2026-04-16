# Device Pairing System - Complete Implementation

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                                                               │
│  ┌──────────────────┐      ┌──────────────────┐             │
│  │   Mobile/Web     │      │  QR Scanner      │             │
│  │   Dashboard      │      │  (Camera/App)    │             │
│  └─────────┬────────┘      └──────────────────┘             │
│            │                                                  │
│            │ 1. Click "Scan Device QR"                      │
│            │ 2. Generate pairing token                       │
│            ▼ 3. Scan device QR (MAC address)               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │        Web API Server (PHP/PostgreSQL)              │    │
│  │  POST /api/auth/generate-pairing-token              │    │
│  │  POST /api/auth/complete-pairing                    │    │
│  └─────────────────────────┬──────────────────────────┘    │
│                            │                                │
│                            ▼                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │         Database (PostgreSQL)                        │   │
│  │  ┌──────────────────────────────────────────────┐   │   │
│  │  │ pairing_tokens table                         │   │   │
│  │  │ • token (UUID)                               │   │   │
│  │  │ • patient_id (FK)                            │   │   │
│  │  │ • is_used (boolean)                          │   │   │
│  │  │ • expires_at (timestamp)                     │   │   │
│  │  │ • device_mac_address                         │   │   │
│  │  └──────────────────────────────────────────────┘   │   │
│  │  ┌──────────────────────────────────────────────┐   │   │
│  │  │ device_registry table                        │   │   │
│  │  │ • device_id (UUID)                           │   │   │
│  │  │ • user_id (FK)                               │   │   │
│  │  │ • mac_address (UNIQUE)                       │   │   │
│  │  │ • device_name (VARCHAR)                      │   │   │
│  │  │ • device_type (VARCHAR)                      │   │   │
│  │  │ • status (ENUM)                              │   │   │
│  │  │ • created_at (timestamp)                     │   │   │
│  │  └──────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │   Arduino ESP32 (Physical Device)                    │   │
│  │  ┌──────────────────────────────────────────────┐   │   │
│  │  │ MAC: AA:BB:CC:DD:EE:FF (Fixed on device)    │   │   │
│  │  │ [Printed QR Code with MAC]                   │   │   │
│  │  └──────────────────────────────────────────────┘   │   │
│  │  Startup logs:                                       │   │
│  │  Device MAC: AA:BB:CC:DD:EE:FF                      │   │
│  │  QR Code Data: AA:BB:CC:DD:EE:FF                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

## Pairing Flow - Step by Step

### Phase 1: Dashboard Initiates Pairing
```
User Action: Click "Scan Device QR" button
├─ Dashboard State: showQRScanner = true
├─ UI: Displays input field for device MAC address
└─ User Option: Type MAC manually OR scan QR code
```

### Phase 2: User Scans Device QR
```
User Action: Scan printed QR code on device
├─ QR Scanner detects: AA:BB:CC:DD:EE:FF
├─ Dashboard captures MAC in: scannedMac state
├─ UI: Shows "AA:BB:CC:DD:EE:FF" in input field
└─ User confirms and clicks "Pair Device"
```

### Phase 3: Dashboard Generates Pairing Token
```
POST /api/auth/generate-pairing-token
├─ Request: { "token": "user_auth_token" }
├─ Server:
│  ├─ Validates user auth token
│  ├─ Checks for existing patient profile
│  ├─ Auto-creates patient if missing
│  ├─ Generates pairing_token (UUID)
│  ├─ Sets expiration: NOW() + 1 hour
│  └─ Inserts into pairing_tokens table
└─ Response: 
   {
     "status": "SUCCESS",
     "pairing_token": "d010849306d45a9a21338f9cef68721d8...",
     "expires_in": 3600
   }
```

### Phase 4: Complete Device Pairing
```
POST /api/auth/complete-pairing
├─ Request: 
│  {
│    "pairing_token": "d010849...",
│    "mac_address": "AA:BB:CC:DD:EE:FF",
│    "device_name": "Smart Medi Box - Device 1",
│    "token": "user_auth_token"
│  }
├─ Server:
│  ├─ Validates pairing_token (not expired, not used)
│  ├─ Gets patient_id from pairing_token
│  ├─ Gets user_id from patient_id
│  ├─ Checks if MAC already registered (unique constraint)
│  ├─ Marks pairing_token as used
│  ├─ Inserts into device_registry:
│  │  device_id: "DEVICE-XXXXXXXX..."
│  │  user_id: from_patient_id
│  │  mac_address: "AA:BB:CC:DD:EE:FF"
│  │  device_name: "Smart Medi Box - Device 1"
│  │  device_type: "SMART_BOX"
│  │  status: "ACTIVE"
│  └─ Returns device info
└─ Response:
   {
     "status": "SUCCESS",
     "device_id": "DEVICE-abc123def456",
     "mac_address": "AA:BB:CC:DD:EE:FF",
     "device_name": "Smart Medi Box - Device 1"
   }
```

### Phase 5: Device List Updates
```
Dashboard Action: Fetch devices
│
GET /api/patient/devices
├─ Request: { "token": "user_auth_token" }
├─ Server:
│  ├─ Gets user_id from auth token
│  ├─ Queries device_registry WHERE user_id = ?
│  └─ Returns all paired devices
└─ Response:
   {
     "status": "SUCCESS",
     "devices": [
       {
         "device_id": "DEVICE-abc123def456",
         "device_name": "Smart Medi Box - Device 1",
         "mac_address": "AA:BB:CC:DD:EE:FF",
         "device_type": "SMART_BOX",
         "status": "ACTIVE",
         "created_at": "2026-04-16T12:34:56Z"
       }
     ],
     "count": 1
   }
```

### Phase 6: UI Updates & Confirmation
```
Dashboard Action: Refresh device list
├─ Clear scanner state: showQRScanner = false
├─ Clear input fields: scannedMac = '', manualMacInput = ''
├─ Re-fetch devices from /api/patient/devices
├─ Display device in "Paired Devices" section
├─ Show success notification: "✅ Device paired successfully!"
└─ Device status shows: "Active"
```

## API Endpoints

### 1. Generate Pairing Token
```
POST /api/auth/generate-pairing-token

Request Headers:
  Content-Type: application/json

Request Body:
  {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }

Response (201 Created):
  {
    "status": "SUCCESS",
    "pairing_token": "d010849306d45a9a21338f9cef68721d8cc24d43f5d68c5e5e1c8dce2a8a0c14",
    "expires_in": 3600,
    "qr_data": "d010849306d45a9a21338f9cef68721d8cc24d43f5d68c5e5e1c8dce2a8a0c14"
  }

Error Responses:
  401 Unauthorized: Invalid or expired auth token
  405 Method Not Allowed: Request is not POST
  500 Internal Server Error: Database error
```

### 2. Complete Device Pairing
```
POST /api/auth/complete-pairing

Request Headers:
  Content-Type: application/json

Request Body:
  {
    "pairing_token": "d010849306d45a9a21338f9cef68721d8cc24d43f5d68c5e5e1c8dce2a8a0c14",
    "mac_address": "AA:BB:CC:DD:EE:FF",
    "device_name": "Smart Medi Box - Device 1",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }

Response (201 Created):
  {
    "status": "SUCCESS",
    "device_id": "DEVICE-abc123def456",
    "mac_address": "AA:BB:CC:DD:EE:FF",
    "device_name": "Smart Medi Box - Device 1"
  }

Error Responses:
  400 Bad Request: Missing required fields
  401 Unauthorized: Invalid pairing token
  404 Not Found: Patient not found
  409 Conflict: Device already paired (duplicate MAC)
  405 Method Not Allowed: Request is not POST
  500 Internal Server Error: Database error
```

### 3. Get Paired Devices
```
POST /api/patient/devices

Request Headers:
  Content-Type: application/json

Request Body:
  {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }

Response (200 OK):
  {
    "status": "SUCCESS",
    "devices": [
      {
        "device_id": "DEVICE-abc123def456",
        "device_name": "Smart Medi Box - Device 1",
        "mac_address": "AA:BB:CC:DD:EE:FF",
        "device_type": "SMART_BOX",
        "status": "ACTIVE",
        "created_at": "2026-04-16T12:34:56Z"
      }
    ],
    "count": 1
  }

Error Responses:
  401 Unauthorized: Invalid auth token
  500 Internal Server Error: Database error
```

## Implementation Checklist

### Backend ✅
- [x] `handleGeneratePairingToken()` - Generates pairing token for authenticated users
- [x] `handleCompletePairing()` - Validates token and MAC, registers device
- [x] `getPatientDevices()` - Returns list of paired devices
- [x] Database schema with `pairing_tokens` and `device_registry` tables
- [x] Auto-patient-profile creation for users without patient records
- [x] Default values for required fields (DOB, gender, blood type)
- [x] Unique constraint on device MAC (no duplicate pairing)

### Frontend ✅
- [x] QR Scanner UI in Patient Dashboard
- [x] Manual MAC address input field
- [x] Pairing button with loading state
- [x] Error message display
- [x] Device list display
- [x] Auto-refresh device list after pairing
- [x] Success notification

### Arduino ✅
- [x] MAC address retrieval on startup
- [x] Device ID generation from MAC
- [x] Display MAC in startup logs
- [x] Prepare for QR code label generation

### Documentation ✅
- [x] System architecture diagram
- [x] Step-by-step pairing flow
- [x] API endpoint documentation
- [x] QR code generation guide
- [x] Troubleshooting guide

## Database Schema

### pairing_tokens Table
```sql
CREATE TABLE pairing_tokens (
  id SERIAL PRIMARY KEY,
  patient_id INTEGER NOT NULL REFERENCES patients(id),
  token VARCHAR(64) NOT NULL UNIQUE,
  is_used BOOLEAN DEFAULT false,
  expires_at TIMESTAMP NOT NULL,
  device_mac_address VARCHAR(17),
  device_name VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_pairing_token ON pairing_tokens(token);
CREATE INDEX idx_pairing_patient ON pairing_tokens(patient_id);
```

### device_registry Table
```sql
CREATE TABLE device_registry (
  id SERIAL PRIMARY KEY,
  device_id VARCHAR(50) NOT NULL UNIQUE,
  user_id INTEGER NOT NULL REFERENCES users(id),
  device_name VARCHAR(255) NOT NULL,
  mac_address VARCHAR(17) NOT NULL UNIQUE,
  device_type VARCHAR(50) NOT NULL,
  status VARCHAR(20) DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_device_user ON device_registry(user_id);
CREATE INDEX idx_device_mac ON device_registry(mac_address);
```

## Testing Procedure

### Manual Testing
1. **User Login**
   ```
   POST /api/auth/login
   {
     "email": "test@example.com",
     "password": "password123"
   }
   ```
   Response: Get `token` value

2. **Generate Pairing Token**
   ```
   POST /api/auth/generate-pairing-token
   {
     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
   }
   ```
   Response: Get `pairing_token` value

3. **Complete Pairing**
   ```
   POST /api/auth/complete-pairing
   {
     "pairing_token": "d010849306d45a9a...",
     "mac_address": "AA:BB:CC:DD:EE:FF",
     "device_name": "Test Device",
     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
   }
   ```
   Response: Get `device_id`

4. **Verify Device Registration**
   ```
   POST /api/patient/devices
   {
     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
   }
   ```
   Response: Should show the paired device

### Edge Cases to Test
- [ ] Pairing same device twice (should error)
- [ ] Pairing with expired token (should error)
- [ ] Pairing with invalid MAC format (should error)
- [ ] Multiple devices for one user (should work)
- [ ] One device paired to multiple users (should error - unique MAC)
- [ ] User without patient profile (should auto-create)
- [ ] Scanning QR code with special characters

## Security Considerations

1. **Pairing Token Expiration**: 1 hour limit prevents brute force attacks
2. **Unique MAC Constraint**: Prevents device hijacking or duplicate registration
3. **User Authorization**: Requires valid auth token for all operations
4. **Database Transactions**: Atomic operations ensure consistency
5. **Error Messages**: Don't reveal sensitive information
6. **Rate Limiting**: Recommended to add rate limiting on pairing endpoints
7. **SSL/TLS**: All API calls should use HTTPS in production

## Deployment Notes

- [x] Code committed and pushed to GitHub
- [x] Changes deployed to Render (auto-deploy on push)
- [ ] Test pairing flow in production environment
- [ ] Monitor error logs for any issues
- [ ] Verify QR code generation works with final MAC addresses
- [ ] Test with actual Arduino devices

## Related Documentation
- See: [QR_CODE_GENERATION.md](QR_CODE_GENERATION.md) - QR code generation instructions
- See: [ARDUINO_QR_PAIRING.md](ARDUINO_QR_PAIRING.md) - Arduino implementation details
