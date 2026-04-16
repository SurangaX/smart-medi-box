# Quick Deployment Guide

## 📂 New & Modified Files

### 1. Database
```
robot_api/migrations/
└── 001_add_patient_doctor_auth.sql    [NEW] Run this first
```

### 2. API Backend
```
robot_api/
├── auth_new.php                       [NEW] Authentication endpoints
├── doctor_patient_management.php      [NEW] Doctor/Patient/Article endpoints
└── index.php                          [MODIFIED] Updated routing
```

### 3. Dashboard Frontend
```
dashboard/src/
├── App_new.jsx                        [NEW] Replace App.jsx with this
├── App_new.css                        [NEW] Replace App.css with this
├── main.jsx                           [NO CHANGE]
├── index.css                          [NO CHANGE]
└── package.json                       [NO CHANGE]
```

### 4. Documentation
```
/
├── AUTHENTICATION_MIGRATION.md        [NEW] Setup & API docs
├── DEVICE_INTEGRATION_GUIDE.md        [NEW] Arduino firmware updates
└── Implementation_Summary.md          [NEW] Project overview
```

## ⚡ Quick Start (5 Steps)

### Step 1: Database Migration (5 min)
```bash
psql -U neondb_owner -d neondb < robot_api/migrations/001_add_patient_doctor_auth.sql
```

### Step 2: Deploy API Files (2 min)
1. Upload `robot_api/auth_new.php` to server
2. Upload `robot_api/doctor_patient_management.php` to server
3. Replace `robot_api/index.php` with updated version

### Step 3: Update Dashboard (2 min)
```bash
cd dashboard/src
mv App.jsx App_old.jsx          # Backup old version
cp App_new.jsx App.jsx          # Use new version
mv App.css App_old.css          # Backup old styles
cp App_new.css App.css          # Use new styles
npm run build                   # Build for production
```

### Step 4: Test Authentication (10 min)
```bash
# Test patient signup
curl -X POST https://smart-medi-box.onrender.com/api/auth/patient/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "Test@12345",
    "name": "Test Patient",
    "nic": "12345678",
    "date_of_birth": "1990-01-01",
    "gender": "MALE",
    "blood_type": "O+",
    "phone_number": "+1234567890"
  }'

# Test login
curl -X POST https://smart-medi-box.onrender.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "Test@12345"
  }'
```

### Step 5: Update Arduino Firmware (15 min)
Follow instructions in `DEVICE_INTEGRATION_GUIDE.md`

## 🔑 Key Credentials Format

### Patient Signup
```json
{
  "email": "patient@example.com",
  "password": "MinimumEight8Chars",
  "name": "Full Name",
  "nic": "123456789",                 // Primary ID
  "date_of_birth": "1990-05-15",      // YYYY-MM-DD
  "gender": "MALE|FEMALE|OTHER",
  "blood_type": "O+|A+|B+|AB+|O-|A-|B-|AB-|UNKNOWN",
  "phone_number": "+1234567890",
  "transplanted_organ": "KIDNEY|LIVER|HEART|LUNG|NONE",
  "transplantation_date": "2020-01-10" // Optional
}
```

### Doctor Signup
```json
{
  "email": "doctor@example.com",
  "password": "MinimumEight8Chars",
  "name": "Dr. Full Name",
  "nic": "987654321",                 // Primary ID
  "date_of_birth": "1985-03-20",      // YYYY-MM-DD
  "specialization": "Cardiology",
  "hospital": "City Hospital",
  "license_number": "LIC123456",      // Unique license
  "phone_number": "+1234567890"
}
```

## 📋 Database Tables Created

```
users (base auth table)
├── id, email, password_hash, role, status, last_login, timestamps

patients (patient profiles)
├── id, user_id, nic, name, date_of_birth, age*, gender, blood_type
├── transplanted_organ, transplantation_date, phone_number, emergency_contact

doctors (doctor profiles)
├── id, user_id, nic, name, date_of_birth, age*, specialization
├── hospital, license_number, phone_number, is_verified

patient_doctor_assignments (linking table)
├── id, patient_id, doctor_id, assigned_at, notes, is_active

articles (doctor publications)
├── id, doctor_id, title, content, summary, category
├── is_published, view_count, created_at, updated_at

pairing_tokens (device pairing codes)
├── id, patient_id, token, device_mac_address, device_name
├── is_used, expires_at, created_at

session_tokens (user sessions)
├── id, user_id, token, expires_at, created_at

*age is auto-calculated from date_of_birth
```

## 🔗 Main API Endpoints

```
POST /api/auth/patient/signup          Create patient account
POST /api/auth/doctor/signup           Create doctor account
POST /api/auth/login                   Login user
POST /api/auth/logout                  Logout user
POST /api/auth/validate-token          Verify token
POST /api/auth/generate-pairing-token  Get device pairing code
POST /api/auth/complete-pairing        Pair device with token

POST /api/doctor/assign-patient        Assign patient to doctor
POST /api/doctor/patients              Get doctor's patients
POST /api/doctor/patient-details       View patient details
POST /api/patient/doctors              Get patient's doctors

POST /api/doctor/article/create        Create article
POST /api/doctor/article/update        Update article
POST /api/doctor/article/delete        Delete article
GET  /api/articles                     List all articles
GET  /api/articles?id=1                Get single article
```

## ✅ Checklist Before Going Live

- [ ] Database migration executed successfully
- [ ] All new API files deployed to server
- [ ] Dashboard files updated and built
- [ ] Test patient signup works
- [ ] Test doctor signup works
- [ ] Test login/logout works
- [ ] Test device pairing works
- [ ] Test doctor-patient assignment works
- [ ] Test article creation works
- [ ] Existing features still work (schedules, temperature, etc.)
- [ ] Check API error responses are useful
- [ ] Verify database has proper backups
- [ ] Monitor logs for first 24 hours
- [ ] Notify users of new system

## 🆘 Troubleshooting

### Database migration fails
- Check PostgreSQL connection string
- Verify user has proper permissions
- Check for syntax errors in SQL file

### API endpoints return 404
- Verify files are in correct location
- Check index.php routing is correct
- Restart web server/PHP

### Dashboard won't load
- Check browser console for errors
- Verify API_URL points to correct server
- Run `npm run build` in dashboard folder

### Device pairing fails
- Check pairing token hasn't expired (1 hour)
- Verify device is connected to network
- Check server logs for detailed error

### User can't login
- Verify email and password are correct
- Check user account exists in database
- Verify account is ACTIVE (not SUSPENDED)

## 📞 Support Contacts

For issues:
1. Check documentation files
2. Review API endpoint docs
3. Check database logs
4. Review browser console
5. Test with curl commands

## 📊 Performance Notes

- Pairing tokens expire: 1 hour
- Session tokens expire: 7 days
- Article pagination: 20 per page default
- Recommended DB backup: Daily
- Recommended log rotation: Weekly

## 🔒 Security Checklist

- [ ] HTTPS enabled on production
- [ ] CORS headers properly configured
- [ ] SQL queries use parameterized statements
- [ ] Passwords hashed with bcrypt
- [ ] Tokens stored securely in database
- [ ] No sensitive data in error messages
- [ ] Rate limiting enabled on API
- [ ] Regular security audits scheduled

## 📈 Expected Load

- Initial: 100-500 users
- Peak: 1000-5000 users
- Device data: ~1KB per device per sync
- Article traffic: Varies by popularity

---

**Version**: 2.0.0  
**Date**: 2026-04-16  
**Status**: Ready for Deployment

For detailed information, see:
- AUTHENTICATION_MIGRATION.md
- DEVICE_INTEGRATION_GUIDE.md
- Implementation_Summary.md
