# Smart Medi Box - Authentication System Migration

## Overview

The system has been restructured from a QR-based scanning system to a modern account-based authentication system with support for both Patients and Doctors.

## Key Changes

### 1. **Authentication System**
- ✅ Removed QR code scanning requirement
- ✅ Added patient and doctor account signup
- ✅ Added email/password-based login
- ✅ Session token management with 7-day expiry

### 2. **User Roles**
- **PATIENT**: Can manage personal health data, pair devices, and view assigned doctors
- **DOCTOR**: Can assign patients, monitor their health data, and publish medical articles

### 3. **Database Changes**
New tables added via migration `001_add_patient_doctor_auth.sql`:
- `users` - Base user accounts (email, password, role)
- `patients` - Patient profiles with health information
- `doctors` - Doctor profiles with specialization and hospital info
- `patient_doctor_assignments` - Links patients to their doctors
- `articles` - Medical articles published by doctors
- `pairing_tokens` - Device pairing codes
- `session_tokens` - Authentication session management

## Setup Instructions

### 1. **Run Database Migration**

Connect to your PostgreSQL database and execute:

```bash
psql -U neondb_owner -d neondb < robot_api/migrations/001_add_patient_doctor_auth.sql
```

Or run the SQL directly in your database client:
```sql
-- Copy and paste the contents of robot_api/migrations/001_add_patient_doctor_auth.sql
```

### 2. **Deploy New API Files**

Replace or add these files in your `robot_api` directory:
- `auth_new.php` - New authentication endpoints
- `doctor_patient_management.php` - Patient/Doctor management and articles
- Update `index.php` - Routes to new modules

### 3. **Update Dashboard**

Replace:
- `dashboard/src/App.jsx` with `App_new.jsx`
- `dashboard/src/App.css` with `App_new.css`

Then rename:
```bash
mv App_new.jsx App.jsx
mv App_new.css App.css
```

## API Endpoints

### Authentication Endpoints

#### Patient Signup
```http
POST /api/auth/patient/signup
Content-Type: application/json

{
  "email": "patient@example.com",
  "password": "securepassword",
  "name": "John Doe",
  "nic": "123456789",
  "date_of_birth": "1990-05-15",
  "gender": "MALE",
  "blood_type": "O+",
  "phone_number": "+1234567890",
  "transplanted_organ": "KIDNEY",
  "transplantation_date": "2020-01-10",
  "emergency_contact": "+1234567891"
}

Response:
{
  "status": "SUCCESS",
  "message": "Patient account created successfully",
  "token": "...",
  "user_id": 1,
  "role": "PATIENT"
}
```

#### Doctor Signup
```http
POST /api/auth/doctor/signup
Content-Type: application/json

{
  "email": "doctor@example.com",
  "password": "securepassword",
  "name": "Dr. Jane Smith",
  "nic": "987654321",
  "date_of_birth": "1985-03-20",
  "specialization": "Cardiology",
  "hospital": "City Hospital",
  "license_number": "LIC123456",
  "phone_number": "+1234567890"
}

Response:
{
  "status": "SUCCESS",
  "message": "Doctor account created successfully",
  "token": "...",
  "user_id": 2,
  "role": "DOCTOR"
}
```

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

Response:
{
  "status": "SUCCESS",
  "message": "Login successful",
  "token": "...",
  "user_id": 1,
  "role": "PATIENT",
  "profile": { ... }
}
```

#### Validate Token
```http
POST /api/auth/validate-token
Content-Type: application/json

{
  "token": "..."
}
```

#### Logout
```http
POST /api/auth/logout
Content-Type: application/json

{
  "token": "..."
}
```

### Device Management Endpoints

#### Generate Pairing Token
```http
POST /api/auth/generate-pairing-token
Content-Type: application/json

{
  "token": "session_token"
}

Response:
{
  "status": "SUCCESS",
  "pairing_token": "...",
  "expires_in": 3600
}
```

#### Complete Device Pairing
```http
POST /api/auth/complete-pairing
Content-Type: application/json

{
  "pairing_token": "...",
  "mac_address": "AA:BB:CC:DD:EE:FF",
  "device_name": "Smart Medi Box #1"
}

Response:
{
  "status": "SUCCESS",
  "message": "Device paired successfully",
  "device_id": "DEVICE-...",
  "mac_address": "AA:BB:CC:DD:EE:FF"
}
```

### Doctor-Patient Management

#### Assign Patient to Doctor
```http
POST /api/doctor/assign-patient
Content-Type: application/json

{
  "token": "doctor_token",
  "patient_nic": "123456789",
  "notes": "Follow-up for transplant monitoring"
}
```

#### Get Doctor's Patients
```http
POST /api/doctor/patients
Content-Type: application/json

{
  "token": "doctor_token"
}

Response:
{
  "status": "SUCCESS",
  "patients": [
    {
      "id": 1,
      "nic": "123456789",
      "name": "John Doe",
      "age": 34,
      "blood_type": "O+",
      "transplanted_organ": "KIDNEY",
      "phone_number": "+1234567890",
      "assigned_at": "2026-04-15T10:30:00"
    }
  ],
  "count": 1
}
```

#### Get Patient's Doctors
```http
POST /api/patient/doctors
Content-Type: application/json

{
  "token": "patient_token"
}
```

#### Get Patient Details (Doctor View)
```http
POST /api/doctor/patient-details
Content-Type: application/json

{
  "token": "doctor_token",
  "patient_id": 1
}
```

### Article Management

#### Create Article (Doctor Only)
```http
POST /api/doctor/article/create
Content-Type: application/json

{
  "token": "doctor_token",
  "title": "Understanding Kidney Transplant Care",
  "content": "Full article content...",
  "summary": "A brief summary",
  "category": "Nephrology"
}
```

#### Get All Articles
```http
GET /api/articles?limit=20&offset=0

Response:
{
  "status": "SUCCESS",
  "articles": [
    {
      "id": 1,
      "title": "...",
      "summary": "...",
      "category": "...",
      "doctor_name": "Dr. Jane Smith",
      "specialization": "Cardiology",
      "view_count": 125,
      "created_at": "2026-04-15T10:30:00"
    }
  ],
  "total": 50,
  "limit": 20,
  "offset": 0
}
```

#### Get Single Article
```http
GET /api/articles?action=article&id=1
```

#### Update Article (Doctor Only)
```http
POST /api/doctor/article/update
Content-Type: application/json

{
  "token": "doctor_token",
  "article_id": 1,
  "title": "Updated Title",
  "content": "Updated content",
  "summary": "Updated summary",
  "category": "Nephrology"
}
```

#### Delete Article (Doctor Only)
```http
POST /api/doctor/article/delete
Content-Type: application/json

{
  "token": "doctor_token",
  "article_id": 1
}
```

## Patient Signup Fields

Required:
- **Email**: Valid email address (unique)
- **Password**: Minimum 8 characters
- **Name**: Full name
- **NIC**: National ID (unique, primary identifier)
- **Date of Birth**: YYYY-MM-DD format
- **Gender**: MALE, FEMALE, or OTHER
- **Blood Type**: A+, A-, B+, B-, AB+, AB-, O+, O-, UNKNOWN
- **Phone Number**: Contact number

Optional:
- **Transplanted Organ**: KIDNEY, LIVER, HEART, LUNG, PANCREAS, INTESTINE, CORNEA, BONE_MARROW, TISSUE, NONE (default: NONE)
- **Transplantation Date**: YYYY-MM-DD format
- **Emergency Contact**: Secondary contact phone
- **Medical History**: Free text field

## Doctor Signup Fields

Required:
- **Email**: Valid email address (unique)
- **Password**: Minimum 8 characters
- **Name**: Full name
- **NIC**: National ID (unique)
- **Date of Birth**: YYYY-MM-DD format
- **Specialization**: Medical specialty (e.g., Cardiology, Nephrology)
- **Hospital**: Hospital/Institution name
- **License Number**: Medical license number (unique)
- **Phone Number**: Contact number

## Feature Workflow

### Patient Workflow

1. **Sign Up**
   - Patient creates account with personal and health information
   - Age is automatically calculated from date of birth

2. **Device Pairing**
   - Patient generates a pairing token
   - Shares token with their device (Arduino)
   - Device uses token to pair and register

3. **View Assigned Doctors**
   - Doctor assigns themselves to patient by searching patient's NIC
   - Patient can view list of assigned doctors and their specializations

4. **Health Monitoring**
   - View current temperature data from paired devices
   - View health history
   - Receive updates from assigned doctors

### Doctor Workflow

1. **Sign Up**
   - Doctor creates account with credentials and hospital info
   - Account pending verification (can be enabled by admin)

2. **Assign Patients**
   - Doctor searches for patient by NIC
   - Creates assignment with optional notes
   - Can now view patient details and health data

3. **Manage Patients**
   - View list of assigned patients
   - View patient health data and device status
   - Add notes about patient care

4. **Publish Articles**
   - Write and publish medical articles
   - Articles visible to all users
   - Only doctors can create articles
   - Track view counts

## User Data Storage

### Patient Data
```javascript
{
  id: number,
  user_id: number,
  nic: string,
  name: string,
  date_of_birth: date,
  age: number,  // auto-calculated
  gender: enum,
  blood_type: enum,
  transplanted_organ: enum,
  transplantation_date: date | null,
  phone_number: string,
  emergency_contact: string,
  medical_history: text
}
```

### Doctor Data
```javascript
{
  id: number,
  user_id: number,
  nic: string,
  name: string,
  date_of_birth: date,
  age: number,  // auto-calculated
  specialization: string,
  hospital: string,
  license_number: string,
  phone_number: string,
  is_verified: boolean
}
```

## Token Management

- **Session Tokens**: Valid for 7 days
- **Pairing Tokens**: Valid for 1 hour (one-time use)
- Tokens stored in database with expiration
- Expired tokens are automatically rejected

## Security Features

- ✅ Passwords hashed with bcrypt
- ✅ Email uniqueness enforced
- ✅ NIC/License number uniqueness enforced
- ✅ Token-based authentication
- ✅ Role-based access control (RBAC)
- ✅ CORS headers configured
- ✅ SQL injection prevention via parameterized queries

## Error Handling

All endpoints return standard response format:

```javascript
{
  "status": "SUCCESS" | "ERROR",
  "message": "Human readable message",
  "data": {}  // optional
}
```

## Migration Rollback

If you need to revert to the old system:

```sql
-- Backup new data
-- Drop new tables: articles, pairing_tokens, session_tokens, patient_doctor_assignments, doctors, patients, users

-- Run old schema to restore original database
```

## Testing

### Test Patient Signup
```bash
curl -X POST https://smart-medi-box.onrender.com/api/auth/patient/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "testpass123",
    "name": "Test Patient",
    "nic": "12345678",
    "date_of_birth": "1990-01-01",
    "gender": "MALE",
    "blood_type": "O+",
    "phone_number": "+1234567890"
  }'
```

### Test Doctor Signup
```bash
curl -X POST https://smart-medi-box.onrender.com/api/auth/doctor/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "doctor@example.com",
    "password": "testpass123",
    "name": "Dr. Test",
    "nic": "87654321",
    "date_of_birth": "1985-01-01",
    "specialization": "Cardiology",
    "hospital": "Test Hospital",
    "license_number": "LIC123456",
    "phone_number": "+1234567890"
  }'
```

### Test Login
```bash
curl -X POST https://smart-medi-box.onrender.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "testpass123"
  }'
```

## Next Steps

1. ✅ Run database migration
2. ✅ Deploy new API files to server
3. ✅ Update dashboard to use new authentication
4. ✅ Test signup and login flows
5. ✅ Test device pairing
6. ✅ Test doctor-patient assignment
7. ✅ Test article management
8. Communicate changes to users
9. Migrate existing users (manual or automated)
10. Monitor for issues and bugs

## Support

For issues or questions:
1. Check the error message returned by API
2. Review logs in database system_logs table
3. Verify database connectivity
4. Ensure migration was run successfully
5. Check that new API files are deployed

## Version Information

- **System Version**: 2.0.1
- **API Version**: 2.0
- **Migration Version**: 001
- **Date**: 2026-04-16
- **Status**: Ready for Deployment
