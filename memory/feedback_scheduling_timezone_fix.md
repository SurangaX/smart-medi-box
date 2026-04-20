---
name: scheduling_timezone_fix
description: Fixed scheduling timezone and API endpoint issues for proper notification timing
type: feedback
---

Fixed two critical issues in the scheduling system:

**Dashboard Fix (DashboardComplete.jsx):**
- Changed fetchSchedules to use correct endpoint: /index.php/api/schedule/today
- Switched from GET with query params to POST method with JSON body containing token
- Added proper authentication headers for secure API access

**Schedule API Fix (schedule.php):**
- Changed timezone handling in handleTriggerDueSchedules to use GMT+5:30 (Asia/Kolkata)
- This ensures schedules trigger at correct local time instead of server UTC time
- Added proper error logging for notification insertion failures

**Why:** 
1. Schedules weren't showing in dashboard due to incorrect API endpoint and missing authentication
2. Notifications weren't sending at correct times due to server timezone (UTC) vs user local time (GMT+5:30)
3. Silent failures in notification insertion during trigger-due process

**How to apply:** 
- For dashboard API calls, always use proper endpoints with authentication
- For time-sensitive operations, use user's local timezone instead of server time
- Add error logging for critical operations like notification insertion to catch silent failures