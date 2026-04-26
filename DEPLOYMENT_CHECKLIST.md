# Smart Medi Box - Deployment Checklist

## ✅ Backend Setup (Render)

- [ ] Database migration script executed
  ```bash
  # Run migration_qr_notifications.sql on PostgreSQL
  psql -U neondb_owner -h <your-host> -d neondb -f migration_qr_notifications.sql
  ```

- [ ] All PHP files deployed:
  - [ ] `robot_api/qr_auth.php`
  - [ ] `robot_api/notifications.php`
  - [ ] `robot_api/auth.php`
  - [ ] `robot_api/device.php`
  - [ ] `robot_api/schedule.php`
  - [ ] `robot_api/temperature.php`
  - [ ] `robot_api/index.php` (router)

- [ ] Environment variables set:
  ```
  DATABASE_URL = postgresql://user:pass@host/dbname
  ```

- [ ] CORS headers enabled (✅ Already configured)

- [ ] Test endpoints with curl:
  ```bash
  curl https://smart-medi-box.onrender.com/index.php/api/auth/patient/signup \
    -X POST -H "Content-Type: application/json" \
    -d '{"name":"test","email":"test@test.com",...}'
  ```

## ✅ Frontend Setup (Netlify)

- [ ] React dashboard deployed:
  - [ ] `dashboard/src/DashboardComplete.jsx` (or merge into App.jsx)
  - [ ] All CSS styles implemented
  - [ ] API URL correctly set: `https://smart-medi-box.onrender.com`

- [ ] Test login flow:
  1. Create account
  2. Login with credentials
  3. View dashboard
  4. Create schedule

- [ ] Firebase Cloud Messaging (optional):
  - [ ] FCM credentials configured
  - [ ] Service worker registered
  - [ ] Push notifications tested

## ✅ Hardware Setup

### ESP32 Gateway
- [ ] Libraries installed in Arduino IDE:
  - [ ] `WiFi`
  - [ ] `HTTPClient`
  - [ ] `ArduinoJson`
  - [ ] `TFT_eSPI` (for display)

- [ ] Configuration updated:
  ```cpp
  #define WIFI_SSID "YOUR_SSID"
  #define WIFI_PASSWORD "YOUR_PASSWORD"
  #define API_URL "https://smart-medi-box.onrender.com"
  ```

- [ ] Hardware connections:
  - [ ] TFT Display (SPI bus)
  - [ ] Buzzer (GPIO 18)
  - [ ] Power supply (5V)

- [ ] Upload completed: `arduino/arduino_esp32_gateway_complete.ino`

- [ ] Verify:
  - [ ] Device boots and shows startup message
  - [ ] WiFi connects successfully
  - [ ] QR code displays on screen
  - [ ] API communication works (check logs)

### Arduino Leonardo
- [ ] Libraries installed:
  - [ ] `DallasTemperature`
  - [ ] `DHT`
  - [ ] `OneWire`

- [ ] Hardware connections:
  - [ ] Solenoid relay (GPIO 3)
  - [ ] Door sensor (GPIO 2)
  - [ ] Buzzer (GPIO 6)
  - [ ] Temperature sensor DS18B20 (GPIO 4)
  - [ ] Humidity sensor DHT22 (GPIO 5)
  - [ ] Status LED (GPIO 13)
  - [ ] RFID reader (Software Serial RX8)
  - [ ] Power supply (5V)

- [ ] Upload completed: `arduino/arduino_leonardo_sensors_complete.ino`

- [ ] Verify:
  - [ ] LED blinks on startup
  - [ ] Serial output shows "READY"
  - [ ] Door sensor detects opening/closing
  - [ ] Temperature reading displayed
  - [ ] Solenoid responds to commands
  - [ ] Buzzer beeps on test command

### I2C Peltier Controller
- [ ] I2C address set to 0x10
- [ ] Power supply connected (12V)
- [ ] Temperature sensor calibrated

### Network Connectivity
- [ ] ESP32 can reach `smart-medi-box.onrender.com`
- [ ] Render API responds to requests
- [ ] Database connection stable

## ✅ Testing Checklist

### User Workflow
- [ ] **Create Account**
  - [ ] Patient signup works
  - [ ] Doctor signup works
  - [ ] Email validation active
  - [ ] NIC/License validation working

- [ ] **QR Authentication**
  - [ ] QR code displays on Arduino
  - [ ] Scanning QR code authenticates
  - [ ] Device paired to user account
  - [ ] Session token created

- [ ] **Schedule Management**
  - [ ] Create MEDICINE schedule
  - [ ] Create FOOD schedule
  - [ ] Create BLOOD_CHECK schedule
  - [ ] View schedules on dashboard
  - [ ] Edit schedule details
  - [ ] Delete schedule

- [ ] **Alarm System**
  - [ ] Alarm triggers at scheduled time
  - [ ] Buzzer activates (beep pattern)
  - [ ] Solenoid unlocks automatically
  - [ ] Display shows message
  - [ ] SMS notification sent
  - [ ] App notification sent

- [ ] **Door Control**
  - [ ] Opening door stops alarm
  - [ ] Schedule marked as completed
  - [ ] Door sensor triggers action
  - [ ] Closing door re-locks solenoid

- [ ] **Temperature Control**
  - [ ] Current temperature displayed
  - [ ] Target temperature adjustable
  - [ ] Cooling activates when needed
  - [ ] Temperature history graphed
  - [ ] Humidity logged

- [ ] **Notifications**
  - [ ] SMS sent to registered phone
  - [ ] App push notification works
  - [ ] Notifications marked as sent
  - [ ] Notification history logged

- [ ] **RFID Override**
  - [ ] RFID tag detected
  - [ ] Authorized tag unlocks
  - [ ] Unauthorized tag triggers alarm
  - [ ] Access logged to database

- [ ] **Error Handling**
  - [ ] Invalid QR code rejected
  - [ ] Expired token refreshed
  - [ ] Network error handled gracefully
  - [ ] Database errors logged

## ✅ Monitoring & Logs

- [ ] Render logs monitored:
  ```bash
  # Monitor in real-time:
  # Go to Render dashboard or use:
  # tail -f logs
  ```

- [ ] Arduino Serial output checked:
  - [ ] Temperature readings
  - [ ] Door events
  - [ ] Command execution
  - [ ] Error messages

- [ ] Database queries logged:
  - [ ] Schedule checks
  - [ ] User authentication
  - [ ] Notification delivery
  - [ ] Alarm events

## ✅ Production Deployment

- [ ] **Code Review**
  - [ ] All files committed to git
  - [ ] No hardcoded credentials
  - [ ] Error messages user-friendly
  - [ ] Logging comprehensive

- [ ] **Security**
  - [ ] HTTPS enabled on all endpoints
  - [ ] Passwords hashed (bcrypt)
  - [ ] Tokens use secure random generation
  - [ ] CORS properly configured
  - [ ] SQL injection prevented

- [ ] **Performance**
  - [ ] Database indexes created
  - [ ] API response time < 500ms
  - [ ] Caching implemented where needed
  - [ ] No memory leaks in Arduino

- [ ] **Documentation**
  - [ ] README.md updated
  - [ ] IMPLEMENTATION_GUIDE.md complete
  - [ ] API documentation available
  - [ ] Troubleshooting guide included

- [ ] **Backup & Recovery**
  - [ ] Database backups enabled
  - [ ] Recovery procedure documented
  - [ ] Emergency stop tested
  - [ ] Data recovery plan in place

## 🚀 Go-Live Procedures

### Pre-Launch (1 week before)
- [ ] Full system integration test
- [ ] Load testing on API
- [ ] 24-hour continuous run test
- [ ] User acceptance testing
- [ ] Security audit

### Launch Day
- [ ] Final database migration
- [ ] Deploy all code to production
- [ ] Monitor system for 2 hours
- [ ] Alert key team members
- [ ] Have rollback plan ready

### Post-Launch (First week)
- [ ] Monitor all logs daily
- [ ] User feedback collection
- [ ] Performance metrics review
- [ ] Bug tracking and fixes
- [ ] Document any issues

## 📞 Emergency Contacts

- **Backend Issues:** Check Render logs
- **Database Issues:** Check PostgreSQL Neon status
- **Hardware Issues:** Check Arduino Serial monitor
- **Network Issues:** Verify WiFi connection and API URL

## ✅ Sign-Off

- [ ] **Developer:** _________________ Date: _______
- [ ] **Tester:** _________________ Date: _______
- [ ] **DevOps:** _________________ Date: _______
- [ ] **Product Manager:** _________________ Date: _______

---

**Last Updated:** April 16, 2026
**Version:** 1.0.1

