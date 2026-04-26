# 🎨 Smart Medi Box - Dashboard Setup Guide

## Quick Start (5 Minutes)

### Step 1: Navigate to Dashboard Folder
```bash
cd dashboard
```

### Step 2: Install Dependencies
```bash
npm install
```

This installs React, Vite, Recharts, and all required packages.

### Step 3: Start Development Server
```bash
npm run dev
```

The browser automatically opens at **http://localhost:3000**

### Step 4: Configure API Connection

Edit `src/App.jsx` line 13 and change the API URL:

**For Local Testing (Apache/PHP):**
```javascript
const API_URL = 'http://localhost/robot_api/index.php/api';
```

**For Remote Server:**
```javascript
const API_URL = 'http://your-server.com/api';
```

**For Docker:**
```javascript
const API_URL = 'http://localhost:8080/api';
```

Save the file. The dashboard hot-reloads automatically.

### Step 5: Test the Connection

1. Make sure your PHP API is running (`robot_api/index.php`)
2. Make sure MySQL database is set up with schema
3. Open browser console (F12) to see any CORS errors
4. Dashboard should show data if API is accessible

---

## ✅ Prerequisites Checklist

Before running the dashboard, ensure:

- [ ] **Node.js 16+** installed (download from nodejs.org)
- [ ] **PHP API** running and accessible
- [ ] **MySQL database** imported with schema
- [ ] **CORS enabled** in your API (check `robot_api/index.php`)

Verify Node.js:
```bash
node --version
npm --version
```

---

## 🎯 What the Dashboard Shows

### Dashboard Tab (Default)
- 🌡️ Real-time box temperature (if Arduino connected)
- 📅 Today's medicine schedules
- 👤 User profile information
- 📊 Quick adherence statistics

### Schedules Tab
- ➕ Create new medicine/food/blood check schedules
- 📋 View all today's schedules
- ✅ Track completion status
- ⏰ Set exact time for each schedule

### Temperature Tab
- 📈 7-day temperature graph
- 🌡️ Current temperature status
- 💨 Humidity percentage
- 🔵 Cooling system status (ON/OFF)

### Statistics Tab
- 📊 Adherence rate pie chart
- 🎯 Total schedules vs completed
- 📌 30-day temperature average
- 📈 Performance summary

---

## 🧪 Test with Sample Data

Default test user already configured:
```
User ID: USER_20260413_A1B2C3
Name: John Doe
Phone: +94777154321
```

**To change the test user:**

Edit `src/App.jsx` around line 13:
```javascript
const [userId, setUserId] = useState('YOUR_USER_ID_HERE');
```

**To create a test user via API:**
```bash
curl -X POST "http://localhost/robot_api/index.php/api/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "age": 45,
    "phone": "0777154321",
    "mac_address": "AA:BB:CC:DD:EE:FF"
  }'
```

Copy the returned `user_id` and paste into App.jsx.

---

## 🛠️ Development Commands

```bash
# Start development server (auto-reload on changes)
npm run dev

# Build for production
npm run build

# Preview production build locally
npm run preview

# There's no lint/test configured, add as needed
```

---

## 📱 Mobile Testing

Test on mobile by opening:
```
http://[YOUR_COMPUTER_IP]:3000
```

Example:
```
http://192.168.1.100:3000
```

Make sure firewall allows port 3000.

---

## 🔧 Customization Examples

### Change Dashboard Colors

Edit `src/App.css` (top of file):
```css
:root {
  --primary: #3b82f6;      /* Change to your preferred blue */
  --secondary: #8b5cf6;    /* Change to your preferred purple */
  --success: #10b981;      /* Change to your preferred green */
}
```

### Change Auto-Refresh Interval

Edit `src/App.jsx` around line 140:
```javascript
// Refresh every 30 seconds
const interval = setInterval(() => {
  fetchSchedules();
  fetchTemperature();
}, 30000);  // Change 30000 to desired milliseconds
```

### Add New Tab

In `src/App.jsx`:

1. Add button to nav:
```jsx
<button 
  className={`nav-btn ${activeTab === 'newtab' ? 'active' : ''}`}
  onClick={() => setActiveTab('newtab')}
>
  <Icon size={18} /> New Tab
</button>
```

2. Add content section:
```jsx
{activeTab === 'newtab' && (
  <div className="content">
    {/* Your content here */}
  </div>
)}
```

---

## 🚀 Production Deployment

### Build for Production
```bash
npm run build
```

This creates an optimized `dist/` folder.

### Option 1: Deploy to Netlify (Easiest)
1. Create account at netlify.com
2. Drag `dist/` folder to Netlify
3. Get live URL instantly

### Option 2: Deploy to Your Web Server
```bash
# Copy dist folder to your server
scp -r dist/* user@server.com:/var/www/html/dashboard/

# Configure web server to serve index.html for all routes
# (Nginx/Apache config needed for SPA routing)
```

### Option 3: Docker Deployment
Create `Dockerfile`:
```dockerfile
FROM node:18-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build
EXPOSE 3000
CMD ["npm", "run", "preview"]
```

Build and run:
```bash
docker build -t medi-box-dashboard .
docker run -p 3000:3000 medi-box-dashboard
```

---

## 🐛 Troubleshooting

### "npm not found"
Install Node.js from nodejs.org (includes npm)

### "Cannot find module"
```bash
rm -rf node_modules package-lock.json
npm install
```

### "CORS error" in console
- Make sure API has CORS headers
- Check `robot_api/index.php` line with `header('Access-Control-Allow-Origin')`
- Verify API URL is correct in App.jsx

### Dashboard shows "No data"
1. Verify API is running: `curl http://localhost/robot_api/index.php/api/status`
2. Check user exists: Create new user with registration API
3. Verify no JavaScript errors (open F12 console)

### Port 3000 already in use
```bash
# Change port in vite.config.js
server: {
  port: 3001,  // Use 3001 instead
  open: true,
  cors: true
}
```

### Temperature shows "No data"
Arduino needs to be connected and sending data. Check:
- Arduino firmware uploaded
- GSM module connected
- API receiving temperature logs

---

## 📊 API Requirements

Your backend API needs these endpoints:

```
✅ GET  /api/user/profile?user_id=ID
✅ GET  /api/schedule/get-today?user_id=ID
✅ GET  /api/temperature/current?user_id=ID
✅ GET  /api/temperature/history?user_id=ID&days=7
✅ GET  /api/user/stats?user_id=ID
✅ POST /api/schedule/create
✅ CORS headers enabled
```

All endpoints should return JSON with `{"status": "SUCCESS", "data": {...}}` format.

---

## 📝 Next Steps

1. ✅ Start dashboard: `npm run dev`
2. ✅ Test with sample user
3. ✅ Create real users and schedules
4. ✅ Connect Arduino and start monitoring
5. ✅ Deploy to production when ready

---

## 💡 Tips & Tricks

- **Single Page App:** Dashboard is SPA, no page reloads needed
- **Real-time Updates:** Automatically refreshes every 30 seconds
- **Offline Support:** Consider adding caching for offline mode (future enhancement)
- **Dark Mode:** Can be added by toggling CSS variables
- **PWA Ready:** Can be converted to app installable on phones

---

## 🌐 CORS Configuration (If Needed)

If you get CORS errors, update your `robot_api/index.php`:

```php
<?php
// Add these lines at the top
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
```

---

## 📞 Support

For issues:
1. Check [dashboard/README.md](./README.md) for full documentation
2. Check main [README.md](../README.md) for system overview
3. Check [SYSTEM_DOCUMENTATION.md](../SYSTEM_DOCUMENTATION.md) for API details

---

**Version:** 1.0.1  
**Last Updated:** April 13, 2026  
**Status:** Ready to Deploy ✅

**Ready to start?** Run `npm run dev` in the `dashboard/` folder!
