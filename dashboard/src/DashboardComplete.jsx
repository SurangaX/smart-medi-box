import React, { useState, useEffect } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, AreaChart, Area } from 'recharts';
import { 
  AlertCircle, Thermometer, Clock, LogOut, Plus, Edit, Trash2, 
  CheckCircle2, AlertTriangle, Bell, Lock, Unlock, Settings, Droplets,
  Activity, TrendingUp, Home, Menu, X
} from 'lucide-react';

const API_URL = 'https://smart-medi-box.onrender.com';

// ============================================================================
// Main Dashboard Component
// ============================================================================

const Dashboard = ({ user, onLogout }) => {
  const [activeTab, setActiveTab] = useState('overview');
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [schedules, setSchedules] = useState([]);
  const [alarms, setAlarms] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [tempData, setTempData] = useState([]);
  const [currentTemp, setCurrentTemp] = useState(0);
  const [targetTemp, setTargetTemp] = useState(4.0);
  const [deviceStatus, setDeviceStatus] = useState({
    solenoid: 'LOCKED',
    door: 'CLOSED',
    alarm: 'INACTIVE',
    is_online: true
  });

  useEffect(() => {
    // Fetch initial data
    fetchSchedules();
    fetchDeviceStatus();
    fetchTemperatureData();
    fetchNotifications();

    // Set up periodic updates
    const scheduleInterval = setInterval(fetchSchedules, 60000);
    const statusInterval = setInterval(fetchDeviceStatus, 5000);
    const tempInterval = setInterval(fetchTemperatureData, 10000); // Poll temp every 10s
    const notifInterval = setInterval(fetchNotifications, 5000); // Increased frequency

    return () => {
      clearInterval(scheduleInterval);
      clearInterval(statusInterval);
      clearInterval(tempInterval);
      clearInterval(notifInterval);
    };
  }, [user?.user_id]);

  const fetchSchedules = async () => {
    try {
      const today = new Date().toISOString().split('T')[0];
      const response = await fetch(
        `${API_URL}/index.php/api/schedule/today`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            token: localStorage.getItem('token'),
            start_date: today,
            end_date: today
          })
        }
      );
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setSchedules(data.schedules);
      }
    } catch (err) {
      console.error('Error fetching schedules:', err);
    }
  };

  const fetchDeviceStatus = async () => {
    try {
      const response = await fetch(
        `${API_URL}/index.php/api/device/status?user_id=${user?.user_id}`,
        { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` } }
      );
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        const lastSyncStr = data.device_status.last_sync;
        let isOnline = false;
        if (lastSyncStr) {
          const lastSync = new Date(lastSyncStr.replace(' ', 'T'));
          const now = new Date();
          const diffMinutes = (now - lastSync) / 60000;
          isOnline = diffMinutes < 3;
        }
        setDeviceStatus({
          ...data.device_status,
          is_online: isOnline
        });
      }
    } catch (err) {
      console.error('Error fetching device status:', err);
    }
  };

  const fetchTemperatureData = async () => {
    try {
      // 1. Get current temp for the display
      const currentRes = await fetch(
        `${API_URL}/index.php/api/temperature/current?user_id=${user?.user_id}`,
        { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` } }
      );
      const currentData = await currentRes.json();
      if (currentData.status === 'SUCCESS') {
        setCurrentTemp(currentData.temperature.internal_temp);
      }

      // 2. Get history for charts
      const historyRes = await fetch(
        `${API_URL}/index.php/api/temperature/history?user_id=${user?.user_id}`,
        { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` } }
      );
      const historyData = await historyRes.json();
      if (historyData.status === 'SUCCESS') {
        setTempData(historyData.history);
      }
    } catch (err) {
      console.error('Error fetching temperature data:', err);
    }
  };

  const fetchNotifications = async () => {
    try {
      // Get current local time in YYYY-MM-DD HH:mm format for the server
      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const localTime = `${year}-${month}-${day} ${hours}:${minutes}`;

      // First, trigger any due schedules using local time
      await fetch(`${API_URL}/index.php/api/schedule/trigger-due?now=${encodeURIComponent(localTime)}`, { method: 'GET' });

      // Then fetch pending notifications
      const response = await fetch(
        `${API_URL}/index.php/api/notifications/pending?user_id=${user?.user_id}`,
        { headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` } }
      );
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setNotifications(data.notifications);
      }
    } catch (err) {
      console.error('Error fetching notifications:', err);
    }
  };

  const navigationItems = [
    { id: 'overview', label: 'Overview', icon: Home },
    { id: 'schedules', label: 'Schedules', icon: Clock },
    { id: 'temperature', label: 'Temperature', icon: Thermometer },
    { id: 'alarms', label: 'Alarms & Alerts', icon: AlertCircle },
    { id: 'notifications', label: 'Notifications', icon: Bell },
    { id: 'settings', label: 'Settings', icon: Settings }
  ];

  return (
    <div className="dashboard-container">
      {/* Sidebar */}
      <div className={`sidebar ${sidebarOpen ? 'open' : 'collapsed'}`}>
        <div className="sidebar-header">
          <div className="logo">💊 MediBox</div>
          <button className="toggle-btn" onClick={() => setSidebarOpen(!sidebarOpen)}>
            {sidebarOpen ? <X size={20} /> : <Menu size={20} />}
          </button>
        </div>

        <nav className="sidebar-nav">
          {navigationItems.map(item => (
            <button
              key={item.id}
              className={`nav-item ${activeTab === item.id ? 'active' : ''}`}
              onClick={() => setActiveTab(item.id)}
            >
              <item.icon size={18} />
              {sidebarOpen && <span>{item.label}</span>}
            </button>
          ))}
        </nav>

        <div className="sidebar-footer">
          <div className="user-info">
            <div className="user-avatar">{user?.email?.[0]?.toUpperCase()}</div>
            {sidebarOpen && <span className="user-name">{user?.email}</span>}
          </div>
          <button className="btn-logout" onClick={onLogout} title="Logout">
            <LogOut size={18} />
          </button>
        </div>
      </div>

        {/* Header */}
        <header className="dashboard-header">
          <h1 className="page-title">
            {navigationItems.find(i => i.id === activeTab)?.label || 'Dashboard'}
          </h1>
          <div className="header-right">
            <div className="device-status-indicator">
              <span className={`status-dot ${!deviceStatus.is_online ? 'offline' : (deviceStatus.alarm === 'ACTIVE' ? 'danger' : 'success')}`}></span>
              <span style={{ color: !deviceStatus.is_online ? '#94a3b8' : 'inherit', fontWeight: '500' }}>
                {!deviceStatus.is_online ? 'Device Offline' : (deviceStatus.alarm === 'ACTIVE' ? 'Alarm Active' : 'System Online')}
              </span>
            </div>
          </div>
        </header>

        {/* Content Sections */}
        <div className="content-area">
          {activeTab === 'overview' && <OverviewSection schedules={schedules} tempData={tempData} currentTemp={currentTemp} deviceStatus={deviceStatus} />}
          {activeTab === 'schedules' && <SchedulesSection schedules={schedules} onRefresh={fetchSchedules} userId={user?.user_id} />}
          {activeTab === 'temperature' && <TemperatureSection tempData={tempData} currentTemp={currentTemp} targetTemp={targetTemp} setTargetTemp={setTargetTemp} userId={user?.user_id} />}
          {activeTab === 'alarms' && <AlarmsSection alarms={alarms} deviceStatus={deviceStatus} />}
          {activeTab === 'notifications' && <NotificationsSection notifications={notifications} onRefresh={fetchNotifications} />}
          {activeTab === 'settings' && <SettingsSection userId={user?.user_id} />}
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// Overview Section
// ============================================================================

const OverviewSection = ({ schedules, tempData, currentTemp, deviceStatus }) => {
  const nextSchedule = schedules.length > 0 ? schedules[0] : null;

  return (
    <div className="section overview">
      <div className="grid-2">
        {/* System Status Card */}
        <div className="card">
          <h3>System Status</h3>
          {!deviceStatus.is_online && (
            <div className="offline-banner">
              <AlertCircle size={16} /> Device Offline - Showing last known data
            </div>
          )}
          <div className="status-grid">
            <div className="status-item">
              <Lock size={20} />
              <span>Solenoid</span>
              <strong className={!deviceStatus.is_online ? 'offline' : (deviceStatus.solenoid === 'LOCKED' ? 'success' : 'warning')}>
                {deviceStatus.solenoid}
              </strong>
            </div>
            <div className="status-item">
              <Home size={20} />
              <span>Door</span>
              <strong className={!deviceStatus.is_online ? 'offline' : (deviceStatus.door === 'CLOSED' ? 'success' : 'warning')}>
                {deviceStatus.door}
              </strong>
            </div>
            <div className="status-item">
              <AlertTriangle size={20} />
              <span>Alarm</span>
              <strong className={!deviceStatus.is_online ? 'offline' : (deviceStatus.alarm === 'INACTIVE' ? 'success' : 'danger')}>
                {deviceStatus.alarm}
              </strong>
            </div>
            <div className="status-item">
              <Thermometer size={20} />
              <span>Temperature</span>
              <strong className={!deviceStatus.is_online ? 'offline' : ''}>{currentTemp.toFixed(1)}°C</strong>
            </div>
          </div>
        </div>

        {/* Next Schedule Card */}
        {nextSchedule && (
          <div className="card">
            <h3>Next Scheduled Alarm</h3>
            <div className="schedule-card-content">
              <div className="time">{String(nextSchedule.hour).padStart(2, '0')}:{String(nextSchedule.minute).padStart(2, '0')}</div>
              <div className="type">{nextSchedule.type}</div>
              {nextSchedule.description && <div className="description">{nextSchedule.description}</div>}
              <div className="status">{nextSchedule.is_completed ? 'Completed' : 'Active'}</div>
            </div>
          </div>
        )}
      </div>

      {/* Temperature Trend */}
      {tempData.length > 0 && (
        <div className="card full-width">
          <h3>Temperature Trend (Last 24 Hours)</h3>
          <ResponsiveContainer width="100%" height={300}>
            <AreaChart data={tempData}>
              <defs>
                <linearGradient id="colorTemp" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8} />
                  <stop offset="95%" stopColor="#3b82f6" stopOpacity={0.1} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="time" />
              <YAxis domain={[0, 10]} />
              <Tooltip />
              <Area type="monotone" dataKey="temp" stroke="#3b82f6" fillOpacity={1} fill="url(#colorTemp)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Active Schedules */}
      {schedules.length > 0 && (
        <div className="card full-width">
          <h3>Today's Schedules</h3>
          <div className="schedules-list">
            {schedules.map((schedule, idx) => (
              <div key={idx} className="schedule-row">
                <div className="schedule-time">{String(schedule.hour).padStart(2, '0')}:{String(schedule.minute).padStart(2, '0')}</div>
                <div className="schedule-type" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <img 
                    src={schedule.type === 'MEDICINE' ? '/medicine.png' : schedule.type === 'FOOD' ? '/food.png' : '/blood.png'} 
                    style={{ width: '20px', height: '20px', objectFit: 'contain' }} 
                    alt="" 
                  />
                  {schedule.type}
                </div>
                <div className="schedule-status">
                  {schedule.status === 'MISSED' ? (
                    <span className="badge danger" style={{ background: 'rgba(239, 68, 68, 0.1)', color: '#ef4444', border: '1px solid rgba(239, 68, 68, 0.2)' }}>
                      <X size={14} /> MISSED
                    </span>
                  ) : schedule.is_completed ? (
                    <CheckCircle2 size={16} className="success" />
                  ) : (
                    <Clock size={16} className="warning" />
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

// ============================================================================
// Schedules Section
// ============================================================================

const SchedulesSection = ({ schedules, onRefresh, userId }) => {
  const [showForm, setShowForm] = useState(false);
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const dropdownRef = React.useRef(null);
  const [formData, setFormData] = useState({
    type: 'MEDICINE',
    hour: 8,
    minute: 0,
    description: ''
  });

  const scheduleTypes = [
    { value: 'MEDICINE', label: 'Medicine', icon: '/medicine.png' },
    { value: 'FOOD', label: 'Food', icon: '/food.png' },
    { value: 'BLOOD_CHECK', label: 'Blood Check', icon: '/blood.png' }
  ];

  React.useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleAddSchedule = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/schedule/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...formData, user_id: userId })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setShowForm(false);
        onRefresh();
      }
    } catch (err) {
      console.error('Error adding schedule:', err);
    }
  };

  const handleDeleteSchedule = async (scheduleId) => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/schedule/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ schedule_id: scheduleId, user_id: userId })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        onRefresh();
      }
    } catch (err) {
      console.error('Error deleting schedule:', err);
    }
  };

  return (
    <div className="section schedules">
      <div className="section-header">
        <h2>Medicine & Food Schedule</h2>
        <button className="btn-primary" onClick={() => setShowForm(!showForm)}>
          <Plus size={18} /> Add Schedule
        </button>
      </div>

      {showForm && (
        <div className="card form-card">
          <h3>Create New Schedule</h3>
          <div className="form-grid">
            <div className="form-group">
              <label>Type</label>
              <div className="custom-dropdown-container" ref={dropdownRef}>
                <div 
                  className="custom-dropdown-selected" 
                  onClick={() => setDropdownOpen(!dropdownOpen)}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <img 
                      src={scheduleTypes.find(t => t.value === formData.type)?.icon} 
                      style={{ width: '20px', height: '20px', objectFit: 'contain' }} 
                      alt="" 
                    />
                    <span>{scheduleTypes.find(t => t.value === formData.type)?.label}</span>
                  </div>
                  <div className="custom-dropdown-arrow"></div>
                </div>
                {dropdownOpen && (
                  <div className="custom-dropdown-options">
                    {scheduleTypes.map(type => (
                      <div 
                        key={type.value} 
                        className="custom-dropdown-option"
                        onClick={() => {
                          setFormData({ ...formData, type: type.value });
                          setDropdownOpen(false);
                        }}
                      >
                        <img src={type.icon} alt="" />
                        <span className="custom-dropdown-option-text">{type.label}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
            <div className="form-group">
              <label>Hour (0-23)</label>
              <input type="number" min="0" max="23" value={formData.hour} onChange={(e) => setFormData({ ...formData, hour: parseInt(e.target.value) })} />
            </div>
            <div className="form-group">
              <label>Minute (0-59)</label>
              <input type="number" min="0" max="59" value={formData.minute} onChange={(e) => setFormData({ ...formData, minute: parseInt(e.target.value) })} />
            </div>
            <div className="form-group full-width">
              <label>Description (Optional)</label>
              <input type="text" value={formData.description} onChange={(e) => setFormData({ ...formData, description: e.target.value })} placeholder="e.g., Take with food" />
            </div>
          </div>
          <div className="form-actions">
            <button className="btn-primary" onClick={handleAddSchedule}>Create Schedule</button>
            <button className="btn-secondary" onClick={() => setShowForm(false)}>Cancel</button>
          </div>
        </div>
      )}

      <div className="schedules-grid">
        {schedules.map((schedule, idx) => (
          <div key={idx} className="schedule-card">
            <div className="schedule-header">
              <h4 style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <img 
                  src={schedule.type === 'MEDICINE' ? '/medicine.png' : schedule.type === 'FOOD' ? '/food.png' : '/blood.png'} 
                  style={{ width: '20px', height: '20px', objectFit: 'contain' }} 
                  alt="" 
                />
                {schedule.type}
              </h4>
              <button className="btn-icon-danger" onClick={() => handleDeleteSchedule(schedule.schedule_id || schedule.id)}>
                <Trash2 size={16} />
              </button>
            </div>
            <div className="schedule-time">
              {String(schedule.hour).padStart(2, '0')}:{String(schedule.minute).padStart(2, '0')}
            </div>
            {schedule.description && <p className="schedule-description">{schedule.description}</p>}
            <div className="schedule-footer">
              {schedule.status === 'MISSED' ? (
                <span className="badge danger"><X size={14} /> Missed</span>
              ) : schedule.is_completed ? (
                <span className="badge success"><CheckCircle2 size={14} /> Completed</span>
              ) : (
                <span className="badge warning"><Clock size={14} /> Active</span>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

// ============================================================================
// Temperature Section
// ============================================================================

const TemperatureSection = ({ tempData, currentTemp, targetTemp, setTargetTemp, userId }) => {
  const [newTargetTemp, setNewTargetTemp] = useState(targetTemp);

  const handleSetTemperature = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/temperature/set-target`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, target_temp: newTargetTemp })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setTargetTemp(newTargetTemp);
      }
    } catch (err) {
      console.error('Error setting temperature:', err);
    }
  };

  return (
    <div className="section temperature">
      <div className="grid-2">
        <div className="card">
          <h3>Current Temperature</h3>
          <div className="temperature-display">
            <div className="temp-value">{currentTemp.toFixed(1)}°C</div>
            <div className="temp-status">
              {currentTemp <= targetTemp + 0.5 ? (
                <span className="success">Within Target Range</span>
              ) : (
                <span className="warning">Above Target</span>
              )}
            </div>
          </div>
        </div>

        <div className="card">
          <h3>Set Target Temperature</h3>
          <div className="temperature-control">
            <div className="control-group">
              <label>Target: {newTargetTemp.toFixed(1)}°C</label>
              <input
                type="range"
                min="0"
                max="10"
                step="0.1"
                value={newTargetTemp}
                onChange={(e) => setNewTargetTemp(parseFloat(e.target.value))}
                className="slider"
              />
              <div className="control-buttons">
                <button className="btn-primary" onClick={handleSetTemperature}>Apply</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      {tempData.length > 0 && (
        <div className="card full-width">
          <h3>Temperature History</h3>
          <ResponsiveContainer width="100%" height={400}>
            <LineChart data={tempData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="time" />
              <YAxis domain={[0, 10]} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="temp" stroke="#3b82f6" name="Temperature" />
              <Line type="monotone" dataKey="target" stroke="#10b981" name="Target" strokeDasharray="5 5" />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  );
};

// ============================================================================
// Alarms Section
// ============================================================================

const AlarmsSection = ({ alarms, deviceStatus }) => {
  const handleDismissAlarm = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/alarm/dismiss`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: localStorage.getItem('user_id') })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        try { window.appNotify({ message: 'Alarm dismissed', type: 'info' }); } catch (e) { /* fallback */ }
      }
    } catch (err) {
      console.error('Error dismissing alarm:', err);
    }
  };

  return (
    <div className="section alarms">
      <div className="card">
        <h3>Current Alarm Status</h3>
        <div className={`alarm-status-box ${deviceStatus.alarm === 'ACTIVE' ? 'active' : 'inactive'}`}>
          <div className="status-indicator">{deviceStatus.alarm}</div>
          {deviceStatus.alarm === 'ACTIVE' && (
            <button className="btn-danger" onClick={handleDismissAlarm}>Dismiss Alarm</button>
          )}
        </div>
      </div>

      <div className="card">
        <h3>System Controls</h3>
        <div className="controls-grid">
          <div className="control-item">
            <Lock size={24} />
            <span>Solenoid: {deviceStatus.solenoid}</span>
          </div>
          <div className="control-item">
            <Home size={24} />
            <span>Door: {deviceStatus.door}</span>
          </div>
          <div className="control-item">
            <AlertTriangle size={24} />
            <span>Alarm: {deviceStatus.alarm}</span>
          </div>
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// Notifications Section
// ============================================================================

const NotificationsSection = ({ notifications, onRefresh }) => {
  return (
    <div className="section notifications">
      <div className="section-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <h2>Notifications & Alerts</h2>
        <button className="btn-secondary" onClick={onRefresh} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <Activity size={16} /> Sync Notifications
        </button>
      </div>
      {notifications.length === 0 ? (
        <div className="card empty-state">
          <Bell size={32} />
          <p>No pending notifications</p>
        </div>
      ) : (
        <div className="notifications-list">
          {notifications.map((notif, idx) => (
            <div key={idx} className="notification-item">
              <div className="notif-icon">
                {notif.type.includes('ALARM') ? <AlertTriangle size={20} /> : <Bell size={20} />}
              </div>
              <div className="notif-content">
                <div className="notif-type">{notif.type}</div>
                <div className="notif-message">{notif.message}</div>
                <div className="notif-time">{new Date(notif.created_at).toLocaleString()}</div>
              </div>
              <div className="notif-status">
                {notif.sms_sent && <span className="badge success">SMS Sent</span>}
                {notif.app_sent && <span className="badge info">App Notified</span>}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

// ============================================================================
// Settings Section
// ============================================================================

const SettingsSection = ({ userId }) => {
  const [rfidTags, setRFIDTags] = useState([]);
  const [showRFIDForm, setShowRFIDForm] = useState(false);
  const [newTag, setNewTag] = useState('');

  useEffect(() => {
    fetchRFIDTags();
  }, [userId]);

  const fetchRFIDTags = async () => {
    // Would fetch RFID tags from API
  };

  const handleAddRFIDTag = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/rfid/add`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, rfid_tag: newTag })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setNewTag('');
        setShowRFIDForm(false);
        fetchRFIDTags();
      }
    } catch (err) {
      console.error('Error adding RFID tag:', err);
    }
  };

  return (
    <div className="section settings">
      <div className="card">
        <h3>Device Settings</h3>
        <div className="settings-group">
          <label>RFID Authorization Tags</label>
          <p className="setting-description">Add RFID tags to unlock the device without triggering alarm</p>
          
          {showRFIDForm && (
            <div className="form-group">
              <input
                type="text"
                value={newTag}
                onChange={(e) => setNewTag(e.target.value)}
                placeholder="Enter RFID tag code"
              />
              <div className="form-actions">
                <button className="btn-primary" onClick={handleAddRFIDTag}>Add Tag</button>
                <button className="btn-secondary" onClick={() => setShowRFIDForm(false)}>Cancel</button>
              </div>
            </div>
          )}

          {!showRFIDForm && (
            <button className="btn-primary" onClick={() => setShowRFIDForm(true)}>
              <Plus size={16} /> Add RFID Tag
            </button>
          )}

          {rfidTags.length > 0 && (
            <div className="tag-list">
              {rfidTags.map((tag, idx) => (
                <div key={idx} className="tag-item">{tag}</div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

// ============================================================================
// Main App Component
// ============================================================================

const App = () => {
  const [currentUser, setCurrentUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check if user is already logged in
    const token = localStorage.getItem('token');
    const user_id = localStorage.getItem('user_id');
    const profile = localStorage.getItem('profile');

    if (token && user_id && profile) {
      setCurrentUser({
        user_id: parseInt(user_id),
        email: JSON.parse(profile).email,
        role: localStorage.getItem('role')
      });
    }
    setLoading(false);
  }, []);

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user_id');
    localStorage.removeItem('role');
    localStorage.removeItem('profile');
    setCurrentUser(null);
  };

  if (loading) {
    return <div className="loading">Loading...</div>;
  }

  if (!currentUser) {
    return <LoginScreen onLoginSuccess={(data) => setCurrentUser({ user_id: data.user_id, email: data.profile.email, role: data.role })} />;
  }

  return <Dashboard user={currentUser} onLogout={handleLogout} />;
};

export default App;

