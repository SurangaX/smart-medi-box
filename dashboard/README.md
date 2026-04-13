# Smart Medi Box - Modern React Dashboard

A beautiful, responsive web dashboard for the Smart Medi Box IoT medicine management system.

## 🎨 Features

✅ **Beautiful UI** - Modern gradient design with smooth animations  
✅ **Real-time Dashboard** - Live temperature, schedules, and status updates  
✅ **Schedule Management** - Create, view, and manage medication schedules  
✅ **Temperature Monitoring** - Real-time temperature with 7-day history graph  
✅ **User Statistics** - Adherence rate, completion tracking, trends  
✅ **Responsive Design** - Works on desktop, tablet, and mobile  
✅ **Fast & Lightweight** - Built with React and Vite  

## 📋 Quick Start

### Prerequisites
- Node.js 16+ and npm

### Installation

```bash
# Navigate to dashboard folder
cd dashboard

# Install dependencies
npm install

# Start development server
npm run dev

# Open browser to http://localhost:3000
```

### Building for Production

```bash
# Build optimized version
npm run build

# Preview production build
npm run preview
```

## 🔌 API Configuration

The dashboard connects to your Smart Medi Box API. Update the API URL in `src/App.jsx`:

```javascript
const API_URL = 'http://your-server.com/api';
```

For local testing:
```javascript
const API_URL = 'http://localhost/robot_api/index.php/api';
```

## 📱 Dashboard Tabs

### 1. **Dashboard**
- Temperature status
- Today's schedules
- User profile
- Quick statistics

### 2. **Schedules**
- Create new schedules (Medicine, Food, Blood Check)
- View all today's schedules
- Track completion status

### 3. **Temperature**
- Real-time temperature display
- 7-day temperature graph
- Humidity and cooling status
- Target temperature info

### 4. **Statistics**
- Adherence rate (pie chart)
- Total/completed schedules
- 30-day temperature average
- Performance summary

## 🎯 Default Test User

```
User ID: USER_20260413_A1B2C3
Name: Test User
Phone: +94777154321
```

You can change the `userId` in `src/App.jsx` line 13:
```javascript
const [userId, setUserId] = useState('USER_20260413_A1B2C3');
```

## 🛠️ Technologies Used

- **React 18** - UI framework
- **Vite** - Build tool & dev server
- **Recharts** - Data visualization
- **Lucide React** - Icons
- **CSS3** - Modern styling

## 📊 Screenshots

### Dashboard Tab
- Temperature gauge
- Today's schedules list
- User information card
- Quick statistics

### Schedules Tab
- Create new schedule form
- Today's schedules table
- Completion status tracking

### Temperature Tab
- Temperature line chart (7 days)
- Current status details
- Humidity information
- Cooling status

### Statistics Tab
- Adherence rate pie chart
- Performance summary
- Average temperature
- Completion metrics

## 🔄 Auto-Refresh

The dashboard automatically refreshes schedules and temperature every 30 seconds. You can modify this interval in `src/App.jsx`:

```javascript
// Refresh every 30 seconds
const interval = setInterval(() => {
  fetchSchedules();
  fetchTemperature();
}, 30000);  // Change this value (milliseconds)
```

## 📱 Mobile Support

The dashboard is fully responsive and works on:
- ✅ Desktop (1440p+)
- ✅ Tablet (768px - 1024px)
- ✅ Mobile (< 768px)

Navigation tabs stack vertically on mobile devices.

## 🔗 API Integration

The dashboard consumes these endpoints:

```
GET  /api/user/profile?user_id=USER_ID
GET  /api/schedule/get-today?user_id=USER_ID
GET  /api/temperature/current?user_id=USER_ID
GET  /api/temperature/history?user_id=USER_ID&days=7
GET  /api/user/stats?user_id=USER_ID
POST /api/schedule/create
```

Ensure your API supports CORS or proxy requests through a backend.

## 🐛 Troubleshooting

### "Cannot fetch API data"
1. Verify API is running and accessible
2. Check CORS headers are enabled in API
3. Confirm API_URL in App.jsx is correct
4. Open browser console (F12) to see errors

### Dashboard won't load
```bash
# Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
npm run dev
```

### Data shows as placeholder
1. Arduino must be connected to API
2. User must exist in database
3. Check network tab in browser DevTools

## 📝 Customization

### Change Colors
Edit CSS variables in `src/App.css`:
```css
:root {
  --primary: #3b82f6;      /* Main color */
  --secondary: #8b5cf6;    /* Accent color */
  --success: #10b981;      /* Success color */
  --danger: #ef4444;       /* Error color */
}
```

### Add New Tabs
1. Add button in `.nav` section
2. Add state handler: `const [activeTab, setActiveTab] = useState('new-tab')`
3. Add content section with `{activeTab === 'new-tab' && (...)}`

### Change Refresh Interval
In `useEffect` in `src/App.jsx`, modify the interval time (milliseconds)

## 🚀 Deployment

### Deploy to Netlify
```bash
npm run build
# Drag 'dist' folder to Netlify
```

### Deploy to Vercel
```bash
npm install -g vercel
vercel
```

### Self-Hosted
```bash
npm run build
# Copy 'dist' folder to web server
# Configure server to serve index.html for all routes
```

## 📄 License

Proprietary - Smart Medi Box Project

## 💡 Tips

1. **Bookmark the dashboard** for quick access
2. **Set a schedule ahead** to test the alarm system
3. **Monitor temperature changes** by keeping dashboard open
4. **Check statistics daily** to track adherence
5. **Create recurring schedules** for daily medications

## 🤝 Support

For issues or feature requests, refer to the main [README.md](../README.md) or [SYSTEM_DOCUMENTATION.md](../SYSTEM_DOCUMENTATION.md).

---

**Version:** 1.0.0  
**Last Updated:** April 13, 2026  
**Status:** Production Ready ✅
