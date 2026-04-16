# Device Pairing Troubleshooting Guide

## "Failed to generate pairing token" Error

This error occurs when the backend cannot create a temporary pairing token. Here are the solutions:

### 1. Check Database Tables Exist

The app requires these database tables:
- `users` - User accounts
- `auth_tokens` - User authentication tokens
- `patients` - Patient information
- `pairing_tokens` - Temporary pairing tokens
- `device_registry` - Paired devices

**Solution: Run database initialization**

#### Option A: Via Browser (Easiest)
1. Visit: `https://smart-medi-box.onrender.com/api/init-database`
2. Wait for "Database Initialization Complete!" message
3. Try pairing again

#### Option B: Via psql (Command Line)
```bash
# Connect to your PostgreSQL database
psql -h ep-shy-mountain-achv0blk-pooler.sa-east-1.aws.neon.tech -U neondb_owner -d neondb -p 5432

# Then run the SQL setup script
\i robot_api/create_auth_tables.sql
```

#### Option C: Via Neon Dashboard
1. Go to Neon Console
2. Go to SQL Editor
3. Copy content from `robot_api/create_auth_tables.sql`
4. Execute the SQL

### 2. Verify User Account Exists

If tables exist, the issue might be with your user account.

**Check your auth token:**
- Open browser DevTools (F12)
- Go to Application → LocalStorage
- Look for key: `token`
- Copy the token value

**Test if token is valid:**
```bash
# Replace YOUR_TOKEN with your token
curl -X POST https://smart-medi-box.onrender.com/api/auth/generate-pairing-token \
  -H "Content-Type: application/json" \
  -d '{"token":"YOUR_TOKEN"}'
```

**Expected response if valid:**
```json
{
  "status": "SUCCESS",
  "pairing_token": "abc123...",
  "expires_in": 3600
}
```

**If token is invalid:**
- Your auth token expired
- **Solution:** Logout and login again to get a fresh token

### 3. Check Patient Record Exists

The user account must have a linked patient record.

**Check in database:**
```sql
-- List all users and their patient records
SELECT u.id, u.email, u.role, p.id as patient_id, p.name
FROM users u
LEFT JOIN patients p ON u.id = p.user_id;

-- If your user has NULL patient_id, run:
INSERT INTO patients (user_id, nic, name, date_of_birth, gender, blood_type)
VALUES (YOUR_USER_ID, 'UNKNOWN', 'Patient', '1990-01-01', 'OTHER', 'UNKNOWN');
```

### 4. Check Console Logs

**On Dashboard (Browser):**
1. Open DevTools (F12)
2. Go to Console tab
3. Look for detailed error messages
4. Screenshot and share errors

**Example helpful messages:**
```
Starting pairing with MAC: AA:BB:CC:DD:EE:FF Token: abc123...
Pairing token response: {status: 'ERROR', message: '...'}
```

**On Server (Backend Logs):**
- Visit Render dashboard
- Look at logs in deployment
- Search for "PAIRING TOKEN"
- Look for error messages

### 5. Test with Simple QR Code

Make sure your QR code is correct:

1. Generate QR with MAC: `AA:BB:CC:DD:EE:FF`
2. Scan it with camera
3. Should show: `AA:BB:CC:DD:EE:FF` in "MAC Address detected"
4. Then click "Pair Device"

### 6. Complete Troubleshooting Checklist

- [ ] Database tables exist (run init-database)
- [ ] User is logged in (check localStorage token)
- [ ] Auth token is valid (hasn't expired)
- [ ] Patient record exists for user
- [ ] QR code has valid MAC address
- [ ] No console errors in browser (F12)
- [ ] Backend logs show clear error (check Render logs)

### 7. Still Having Issues?

**Get full error details:**
1. Open DevTools (F12)
2. Go to Network tab
3. Click "Pair Device"
4. Click on the failed request
5. Go to Response tab
6. Read the error message
7. Share full error response

**Manual pairing steps to debug:**
```bash
# Step 1: Get your auth token from localStorage

# Step 2: Generate pairing token
curl -X POST https://smart-medi-box.onrender.com/api/auth/generate-pairing-token \
  -H "Content-Type: application/json" \
  -d '{"token":"YOUR_AUTH_TOKEN"}'

# Step 3: Complete pairing with device MAC
curl -X POST https://smart-medi-box.onrender.com/api/auth/complete-pairing \
  -H "Content-Type: application/json" \
  -d '{
    "token":"YOUR_AUTH_TOKEN",
    "pairing_token":"PAIRING_TOKEN_FROM_STEP_2",
    "mac_address":"AA:BB:CC:DD:EE:FF",
    "device_name":"Smart Medi Box Test"
  }'
```

## Quick Reference

| Problem | Solution |
|---------|----------|
| "Failed to generate pairing token" | Run `/api/init-database` |
| Token is invalid or expired | Logout and login again |
| No patient record | Run SQL INSERT in troubleshooting section |
| Unknown database error | Check server logs on Render |
| QR scanner not working | Reload page, check camera permissions |
| Device still not in list | Run `fetchDevices()` after pairing |

