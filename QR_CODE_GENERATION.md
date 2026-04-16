# Static QR Code Generation Guide

## Overview
Each Smart Medi Box device needs a printed QR code label with its MAC address. When users click "Scan Device QR" in the dashboard, they scan this label to complete device pairing.

## QR Code Contents
The QR code contains **only the device MAC address**:
```
AA:BB:CC:DD:EE:FF
```

This is obtained from the Arduino ESP32 on startup via:
```cpp
String DEVICE_MAC;  // Set during getDeviceMACAddress()
// Format: AA:BB:CC:DD:EE:FF
```

## How to Generate QR Codes

### Option 1: Online QR Generator (Easiest)
1. Arduino startup logs show the MAC address:
   ```
   Device MAC: AA:BB:CC:DD:EE:FF
   ```

2. Visit: https://www.qr-code-generator.com/
3. Enter the MAC address: `AA:BB:CC:DD:EE:FF`
4. Download as PNG/PDF
5. Print and attach to device

### Option 2: Using QR Code Libraries

#### Python (qrcode library)
```bash
pip install qrcode[pil]
```

```python
import qrcode

def generate_qr_for_mac(mac_address):
    qr = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_L,
        box_size=10,
        border=4,
    )
    qr.add_data(mac_address)
    qr.make(fit=True)
    
    img = qr.make_image(fill_color="black", back_color="white")
    img.save(f"qr_{mac_address.replace(':', '_')}.png")

# Example
generate_qr_for_mac("AA:BB:CC:DD:EE:FF")
```

#### Node.js (qrcode npm package)
```bash
npm install qrcode
```

```javascript
const QRCode = require('qrcode');

async function generateQRForMac(macAddress) {
  const filename = `qr_${macAddress.replace(/:/g, '_')}.png`;
  await QRCode.toFile(filename, macAddress, {
    color: {
      dark: '#000000',
      light: '#FFFFFF'
    },
    width: 300
  });
  console.log(`QR code saved: ${filename}`);
}

// Example
generateQRForMac("AA:BB:CC:DD:EE:FF");
```

#### Linux Command Line
```bash
# Install qr code generator
sudo apt-get install qrencode

# Generate QR code
qrencode -o qr_device_AAF4D1E5B3F6.png "AA:F4:D1:E5:B3:F6"

# With size customization
qrencode -s 10 -d 300 -o qr_device.png "AA:F4:D1:E5:B3:F6"
```

### Option 3: Batch QR Code Generator Script

#### Python Script (Multiple Devices)
```python
import qrcode
import csv

# Read MAC addresses from CSV
with open('devices.csv', 'r') as f:
    reader = csv.reader(f)
    devices = list(reader)

# Generate QR codes
for device_name, mac_address in devices:
    qr = qrcode.QRCode(version=1, box_size=10, border=4)
    qr.add_data(mac_address)
    qr.make(fit=True)
    
    img = qr.make_image(fill_color="black", back_color="white")
    filename = f"qr_{device_name}.png"
    img.save(filename)
    print(f"Generated: {filename}")

# devices.csv format:
# device_01,AA:BB:CC:DD:EE:FF
# device_02,AA:BB:CC:DD:EE:F7
# device_03,AA:BB:CC:DD:EE:F8
```

## QR Code Label Templates

### Printable Label Design
```
┌─────────────────────────┐
│   SMART MEDI BOX        │
│   Device ID: DEVICE-001 │
│                         │
│        [QR CODE]        │
│      (AA:BB:CC:DD)      │
│                         │
│  MAC: AA:BB:CC:DD:EE:FF │
│  Version: v2.1.0        │
└─────────────────────────┘
```

### Print Settings
- **Label Size:** 2" x 2" (51mm x 51mm)
- **QR Code Size:** 1.5" x 1.5" (38mm x 38mm)
- **Font:** Arial or sans-serif, 10pt
- **Print Quality:** 300 DPI minimum
- **Material:** Waterproof adhesive label recommended

## Installation on Device

1. **Print the QR code label**
2. **Clean the device surface** with rubbing alcohol
3. **Apply the label** to the top or side of the device (visible location)
4. **Laminate** (optional, for protection)
5. **Allow to dry** for 24 hours before use

## Pairing Flow

### Step 1: Arduino Startup
```
[ESP32] Device MAC: AA:BB:CC:DD:EE:FF
[ESP32] QR Code Data: AA:BB:CC:DD:EE:FF
[ESP32] To pair: Scan the QR code with mobile app
```

### Step 2: User Scans QR
1. Open dashboard
2. Click "Scan Device QR"
3. Scan the printed QR code on device
4. System automatically extracts MAC: `AA:BB:CC:DD:EE:FF`

### Step 3: Complete Pairing
```javascript
POST /api/auth/complete-pairing
{
  "pairing_token": "d010849...",
  "mac_address": "AA:BB:CC:DD:EE:FF",
  "device_name": "Smart Medi Box - Device 1",
  "token": "user_auth_token"
}
```

### Step 4: Verification
✅ Device appears in "Paired Devices" list
✅ Device status: "Active"
✅ Device ready for commands

## Troubleshooting

### QR Code Won't Scan
- **Issue:** Camera can't focus on small QR code
- **Solution:** Increase QR code size (2.5" x 2.5" minimum)
- **Fix:** Print larger or use higher resolution

### Wrong MAC Address Scanned
- **Issue:** QR code got damaged
- **Solution:** Reprint label
- **Prevention:** Laminate for protection

### Device Already Paired Error
- **Issue:** MAC address already registered
- **Solution:** Unpair first, or use different device
- **Command:** Delete from device list on dashboard

### QR Code Format Issues
- **Issue:** Dashboard doesn't recognize scanned data
- **Solution:** Ensure MAC format is `AA:BB:CC:DD:EE:FF` (colon-separated)
- **Check:** Arduino startup logs should show correct format

## Verification Checklist

- [ ] Arduino boots and displays MAC address in logs
- [ ] QR code generated from correct MAC address
- [ ] QR code label printed and attached to device
- [ ] Dashboard QR scanner recognizes the code
- [ ] Device appears in paired devices list after scanning
- [ ] Device status shows "Active"
- [ ] Device responds to commands from dashboard

## Notes

- Each device has a **unique, permanent MAC address**
- MAC address is burned into ESP32 hardware
- QR codes can be regenerated anytime (MAC doesn't change)
- Store backup copies of QR codes in database for reprinting
- Implement asset tracking system linking QR code → Patient → Device
