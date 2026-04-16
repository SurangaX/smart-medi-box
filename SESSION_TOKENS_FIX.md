# Fix: Session Tokens Table Issue - Deployment Instructions

## Root Cause
The login endpoint was trying to insert tokens into a **non-existent `auth_tokens` table**, while the schedule endpoints were querying a **non-existent `session_tokens` table**. This caused all token lookups to fail even though users could successfully log in.

## What Was Fixed
✅ Added `session_tokens` table definition to the database schema  
✅ Updated `auth.php` to insert tokens into `session_tokens` table  
✅ Added database indexes for fast token lookups  
✅ Created migration script for existing databases  

## Deployment Steps

### Step 1: Verify Render Database Access
1. Go to https://render.com/dashboard
2. Navigate to your PostgreSQL service: **smart-medi-box-postgresql**
3. Click "Connect" and copy your connection details

### Step 2: Run the Migration Script
Execute this SQL on your Render PostgreSQL database:

```sql
-- Create Session Tokens Table (for web authentication)
CREATE TABLE IF NOT EXISTS session_tokens (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_session_tokens_user_id ON session_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_session_tokens_token ON session_tokens(token);
```

**To run via Render:**
1. Click "Connect" on the PostgreSQL instance
2. Use psql, pgAdmin, or the Render dashboard's query tool
3. Paste and execute the SQL above

**To run via psql command line:**
```bash
psql postgresql://user:password@host:port/smart_medi_box < robot_api/migrations/001_add_session_tokens_table.sql
```

### Step 3: Verify Deployment
1. Pull the latest code from GitHub (Render should auto-deploy)
2. Log in to the dashboard at https://smart-medi-box.onrender.com
3. Try creating a schedule - it should now work!

## What Happens Now
1. User logs in → Token is **saved to `session_tokens` table** ✅
2. User creates schedule → Backend queries `session_tokens` → Finds token ✅
3. User fetches schedules → Backend queries `session_tokens` → Returns schedules ✅

## Testing
After deployment:
```javascript
// Frontend console will now show:
✅ Schedule API Response Status: 200 (instead of 400)
✅ Schedules created and fetched successfully
```

---

**Git Commit:** `406b620` - "fix: add missing session_tokens table and update auth to use correct table"
