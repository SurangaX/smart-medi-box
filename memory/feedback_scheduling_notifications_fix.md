---
name: scheduling_notifications_fix
description: Fixed scheduling UI delete bug and notification sending issues
type: feedback
---

Fixed two related issues in the scheduling and notifications system:

**Dashboard Fix (App.jsx):**
- Fixed delete article confirmation bug where deleting state wasn't cleared properly
- Added proper cleanup of deletingArticleId and articleIdToDelete state after delete operations
- This resolves the issue where second delete attempt shows 'deleting' but does nothing

**Schedule API Fix (schedule.php):**
- Added RETURNING clause to notification insert in handleTriggerDueSchedules
- Added error logging for failed notification insertion
- This ensures notifications are properly created when schedules are triggered
- Fixes the issue where scheduled medicine due notifications aren't sent to dashboard

**Why:** 
1. UI state wasn't being reset after delete operations, causing subsequent deletes to fail silently
2. Notification inserts weren't being verified for success, leading to silent failures when schedules were triggered

**How to apply:** 
- For UI confirmation dialogs, always ensure loading/deleting states are cleared in both success and error paths
- For database inserts that trigger user-facing actions, verify insert success and log errors appropriately