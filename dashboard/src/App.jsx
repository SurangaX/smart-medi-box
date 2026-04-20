// Smart Medi Box Dashboard - v1.5.2
import React, { useState, useEffect, useRef } from 'react';
import './notifications.css';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { AlertCircle, Thermometer, Clock, Users, LogOut, CheckCircle2, FileText, Plus, Edit, Trash2, Phone, MapPin, Calendar, Lock, Eye, EyeOff, X, Camera, Activity, Bell, Check } from 'lucide-react';
import { Html5Qrcode, Html5QrcodeScanner } from 'html5-qrcode';
import './App.css';

const API_URL = 'https://smart-medi-box.onrender.com';

// Debug: Log API URL on startup
if (typeof window !== 'undefined') {
  console.log('🌐 API URL:', API_URL);
}

// ==================== Login Screen ====================
const LoginScreen = ({ onLoginSuccess }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const response = await fetch(`${API_URL}/index.php/api/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      const data = await response.json();

      if (data.status === 'SUCCESS') {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user_id', data.user_id);
        localStorage.setItem('role', data.role);
        localStorage.setItem('profile', JSON.stringify(data.profile));
        onLoginSuccess(data);
      } else {
        setError(data.message || 'Login failed');
      }
    } catch (err) {
      setError('Network error: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-screen">
      <div className="auth-container">
        <div className="auth-box">
          <div className="auth-header">
            <div className="app-logo">💊</div>
            <h1>Smart Medi Box</h1>
            <p>Medical Device Management System</p>
          </div>

          <form onSubmit={handleSubmit} className="auth-form">
            <div className="form-group">
              <label>Email Address</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="Enter your email"
                required
              />
            </div>

            <div className="form-group">
              <label>Password</label>
              <div className="password-input-group">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter your password"
                  required
                />
                <button
                  type="button"
                  className="password-toggle"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            {error && (
              <div className="error-message">
                <AlertCircle size={16} />
                {error}
              </div>
            )}

            

            <button type="submit" className="btn-primary btn-block" disabled={loading}>
              {loading ? 'Signing in...' : 'Sign In'}
            </button>
          </form>

          <div className="auth-footer">
            <p>Don't have an account? <a href="#signup" onClick={(e) => { e.preventDefault(); window.location.hash = '#signup'; }}>Create one</a></p>
          </div>
        </div>
      </div>
    </div>
  );
};

// ==================== Signup Screen ====================
const SignupScreen = ({ onSignupSuccess }) => {
  const [role, setRole] = useState('PATIENT');
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    name: '',
    nic: '',
    date_of_birth: '',
    gender: 'MALE',
    blood_type: 'O+',
    phone_number: '',
    // Doctor specific
    specialization: '',
    hospital: '',
    license_number: '',
    // Patient specific
    transplanted_organ: 'NONE',
    transplantation_date: '',
    emergency_contact: ''
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
  const organs = ['KIDNEY', 'LIVER', 'HEART', 'LUNG', 'PANCREAS', 'INTESTINE', 'CORNEA', 'BONE_MARROW', 'TISSUE', 'NONE'];
  const genders = ['MALE', 'FEMALE', 'OTHER'];

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      // Map frontend field names to backend API field names
      const payload = {
        name: formData.name,
        email: formData.email,
        password: formData.password,
        nic: formData.nic,
        dob: formData.date_of_birth,  // Map date_of_birth to dob
        phone: formData.phone_number,  // Map phone_number to phone
      };

      // Add role-specific fields
      if (role === 'PATIENT') {
        // Patient doesn't need extra fields for basic signup
      } else if (role === 'DOCTOR') {
        payload.specialty = formData.specialization;  // Map specialization to specialty
        payload.license_number = formData.license_number;
        payload.hospital = formData.hospital;
      }

      const endpoint = role === 'PATIENT' ? 'patient/signup' : 'doctor/signup';
      const response = await fetch(`${API_URL}/index.php/api/auth/${endpoint}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (data.status === 'SUCCESS') {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user_id', data.user_id);
        localStorage.setItem('role', data.role);
        localStorage.setItem('profile', JSON.stringify(data.profile));
        onSignupSuccess(data);
      } else {
        // Show detailed error message
        let errorMsg = data.message || 'Signup failed';
        if (data.missing_fields) {
          errorMsg += '\nMissing: ' + data.missing_fields.join(', ');
        }
        if (data.error_details) {
          errorMsg += '\n' + data.error_details;
        }
        setError(errorMsg);
        console.error('Signup error:', data);
      }
    } catch (err) {
      setError('Network error: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  const calculateAge = (dob) => {
    if (!dob) return '';
    const today = new Date();
    const birthDate = new Date(dob);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return age >= 0 ? age : '';
  };

  return (
    <div className="auth-screen">
      <div className="auth-container">
        <div className="auth-box auth-box-wide">
          <div className="auth-header">
            <div className="app-logo">💊</div>
            <h1>Create Account</h1>
            <p>Join Smart Medi Box</p>
          </div>

          <div className="role-selector">
            <button
              type="button"
              className={`role-btn ${role === 'PATIENT' ? 'active' : ''}`}
              onClick={() => setRole('PATIENT')}
            >
              👤 Patient
            </button>
            <button
              type="button"
              className={`role-btn ${role === 'DOCTOR' ? 'active' : ''}`}
              onClick={() => setRole('DOCTOR')}
            >
              👨‍⚕️ Doctor
            </button>
          </div>

          <form onSubmit={handleSubmit} className="auth-form signup-form">
            <div className="form-row">
              <div className="form-group">
                <label>Email Address *</label>
                <input
                  type="email"
                  name="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  placeholder="your@email.com"
                  required
                />
              </div>
              <div className="form-group">
                <label>Password *</label>
                <div className="password-input-group">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    name="password"
                    value={formData.password}
                    onChange={handleInputChange}
                    placeholder="Min 8 characters"
                    required
                  />
                  <button
                    type="button"
                    className="password-toggle"
                    onClick={() => setShowPassword(!showPassword)}
                  >
                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                </div>
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label>Full Name *</label>
                <input
                  type="text"
                  name="name"
                  value={formData.name}
                  onChange={handleInputChange}
                  placeholder="Your full name"
                  required
                />
              </div>
              <div className="form-group">
                <label>NIC Number *</label>
                <input
                  type="text"
                  name="nic"
                  value={formData.nic}
                  onChange={handleInputChange}
                  placeholder="National ID"
                  required
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label>Date of Birth *</label>
                <input
                  type="date"
                  name="date_of_birth"
                  value={formData.date_of_birth}
                  onChange={handleInputChange}
                  required
                />
              </div>
              <div className="form-group">
                <label>Age</label>
                <input
                  type="text"
                  value={calculateAge(formData.date_of_birth)}
                  disabled
                  placeholder="Auto-calculated"
                />
              </div>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label>Gender</label>
                <select name="gender" value={formData.gender} onChange={handleInputChange}>
                  {genders.map(g => <option key={g} value={g}>{g}</option>)}
                </select>
              </div>
              <div className="form-group">
                <label>Phone Number *</label>
                <input
                  type="tel"
                  name="phone_number"
                  value={formData.phone_number}
                  onChange={handleInputChange}
                  placeholder="+94 7XX XXX XXX"
                  required
                />
              </div>
            </div>

            {role === 'PATIENT' && (
              <>
                <div className="form-row">
                  <div className="form-group">
                    <label>Blood Type</label>
                    <select name="blood_type" value={formData.blood_type} onChange={handleInputChange} required>
                      {bloodTypes.map(bt => <option key={bt} value={bt}>{bt}</option>)}
                    </select>
                  </div>
                  <div className="form-group">
                    <label>Transplanted Organ</label>
                    <select name="transplanted_organ" value={formData.transplanted_organ} onChange={handleInputChange} required>
                      {organs.map(o => <option key={o} value={o}>{o}</option>)}
                    </select>
                  </div>
                </div>

                {formData.transplanted_organ !== 'NONE' && (
                  <div className="form-group">
                    <label>Transplantation Date</label>
                    <input
                      type="date"
                      name="transplantation_date"
                      value={formData.transplantation_date}
                      onChange={handleInputChange}
                      required
                    />
                  </div>
                )}

                <div className="form-group">
                  <label>Emergency Contact</label>
                  <input
                    type="tel"
                    name="emergency_contact"
                    value={formData.emergency_contact}
                    onChange={handleInputChange}
                    placeholder="Emergency contact phone"
                  />
                </div>
              </>
            )}

            {role === 'DOCTOR' && (
              <>
                <div className="form-row">
                  <div className="form-group">
                    <label>Specialization *</label>
                    <input
                      type="text"
                      name="specialization"
                      value={formData.specialization}
                      onChange={handleInputChange}
                      placeholder="e.g., Cardiology"
                      required
                    />
                  </div>
                  <div className="form-group">
                    <label>Hospital *</label>
                    <input
                      type="text"
                      name="hospital"
                      value={formData.hospital}
                      onChange={handleInputChange}
                      placeholder="Your hospital"
                      required
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label>License Number *</label>
                  <input
                    type="text"
                    name="license_number"
                    value={formData.license_number}
                    onChange={handleInputChange}
                    placeholder="Medical license number"
                    required
                  />
                </div>
              </>
            )}

            {error && (
              <div className="error-message">
                <AlertCircle size={16} />
                {error}
              </div>
            )}

            <button type="submit" className="btn-primary btn-block" disabled={loading}>
              {loading ? 'Creating account...' : 'Create Account'}
            </button>
          </form>

          <div className="auth-footer">
            <p>Already have an account? <a href="#login" onClick={(e) => { e.preventDefault(); window.location.hash = '#login'; }}>Sign in</a></p>
          </div>
        </div>
      </div>
    </div>
  );
};

// ==================== Patient Dashboard ====================
const PatientDashboard = ({ profile, token, onLogout }) => {
  const [activeTab, setActiveTab] = useState('dashboard');
  const [devices, setDevices] = useState([]);
  const [devicesLoading, setDevicesLoading] = useState(false);
  const [devicesError, setDevicesError] = useState('');
  const [notifications, setNotifications] = useState([]);
  const [notifPanelOpen, setNotifPanelOpen] = useState(false);
  const headerRef = useRef(null);
  const notifPanelRef = useRef(null);
  const bellBtnRef = useRef(null);
  const [notifPanelStyle, setNotifPanelStyle] = useState({});
  const [doctors, setDoctors] = useState([]);
  const [schedules, setSchedules] = useState([]);
  const [schedulesLoading, setSchedulesLoading] = useState(false);
  const [isCreatingSchedule, setIsCreatingSchedule] = useState(false);
  const [isDeletingSchedule, setIsDeletingSchedule] = useState(null);
  const [tempHistory, setTempHistory] = useState([]);
  const [stats, setStats] = useState(null);
  const [pairingToken, setPairingToken] = useState('');
  const [showPairingCode, setShowPairingCode] = useState(false);
  const [scannedMac, setScannedMac] = useState('');
  const [showQRScanner, setShowQRScanner] = useState(false);
  const [manualMacInput, setManualMacInput] = useState('');
  const [temperature, setTemperature] = useState(null);
  const [loading, setLoading] = useState(false);
  const [scannerError, setScannerError] = useState('');
  const [scannerStarted, setScannerStarted] = useState(false);
  const [cameras, setCameras] = useState([]);
  const [selectedCameraId, setSelectedCameraId] = useState(null);
  const [showDeviceFound, setShowDeviceFound] = useState(false);
  const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [scheduleToDelete, setScheduleToDelete] = useState(null);
  const [activeMedicineAlert, setActiveMedicineAlert] = useState(null);
  const [isCompletingInModal, setIsCompletingInModal] = useState(false);
  const [isSnoozingInModal, setIsSnoozingInModal] = useState(false);
  
  // Helper to get current HH:mm
  const getCurrentTime = () => {
    const now = new Date();
    return { hour: now.getHours(), minute: now.getMinutes() };
  };

  const [newSchedule, setNewSchedule] = useState({ 
    type: 'MEDICINE', 
    schedule_date: new Date().toISOString().split('T')[0],
    ...getCurrentTime(), 
    description: '' 
  });
  const [scheduleFilterDate, setScheduleFilterDate] = useState(new Date().toISOString().split('T')[0]);
  const [showAddForm, setShowAddForm] = useState(false);
  const [articles, setArticles] = useState([]);
  const [selectedArticle, setSelectedArticle] = useState(null);
  const articleCacheRef = useRef({});
  const [isArticleLoading, setIsArticleLoading] = useState(false);
  const [articlesLoading, setArticlesLoading] = useState(false);
  const qrScannerRef = useRef(null);
  const qrInstanceRef = useRef(null);
  const fileInputRef = useRef(null);

  const handleLogoutClick = () => {
    console.log('🚪 Logout button clicked');
    setShowLogoutConfirm(true);
  };

  const handleConfirmLogout = () => {
    setShowLogoutConfirm(false);
    onLogout();
  };

  const handleCancelLogout = () => {
    setShowLogoutConfirm(false);
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

      console.log('🔔 Triggering due schedules and fetching notifications...');
      // First, trigger any due schedules using local time
      await fetchWithRetry(`${API_URL}/index.php/api/schedule/trigger-due?now=${encodeURIComponent(localTime)}`, { method: 'GET' });

      // Then fetch pending notifications
      const response = await fetchWithRetry(`${API_URL}/index.php/api/notifications/pending?user_id=${profile?.id || profile?.user_id}`, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        // Map the backend notifications to the frontend format
        const formattedNotifs = data.notifications.map(n => ({
          id: n.id,
          schedule_id: n.schedule_id,
          message: n.message,
          type: n.type.toLowerCase().includes('alarm') ? 'error' : 'info',
          rawType: n.type,
          timestamp: n.created_at,
          read: false
        }));
        
        // Merge with existing notifications, avoiding duplicates by ID
        setNotifications(prev => {
          const existingIds = new Set(prev.map(p => p.id));
          const newOnes = formattedNotifs.filter(n => !existingIds.has(n.id));
          
          // CRITICAL: Only trigger popup for VERY RECENT medicine alarms (created in last 60 seconds)
          // This prevents old history notifications from popping up when logging in
          const now = new Date();
          const medicineAlarm = newOnes.find(n => {
            if (n.rawType !== 'ALARM_MEDICINE') return false;
            const created = new Date(n.timestamp);
            const diffSeconds = (now - created) / 1000;
            return diffSeconds < 60; // Only if created in the last minute
          });
          
          if (medicineAlarm) {
            setActiveMedicineAlert(medicineAlarm);
          }
          
          return [...newOnes, ...prev];
        });
      }
    } catch (err) {
      console.error('Failed to fetch notifications:', err);
    }
  };

  useEffect(() => {
    if (activeTab === 'doctors') {
      fetchDoctors();
    } else if (activeTab === 'devices') {
      fetchDevices();
    } else if (activeTab === 'dashboard') {
      fetchSchedules();
      fetchTemperature();
      fetchStats();
      fetchNotifications(); // Fetch notifications on dashboard load
    } else if (activeTab === 'schedules') {
      fetchSchedules();
    } else if (activeTab === 'temperature') {
      fetchTempHistory();
    } else if (activeTab === 'stats') {
      fetchStats();
    } else if (activeTab === 'articles') {
      fetchArticles();
    }
  }, [activeTab]);

  // Set up periodic notification checks
  useEffect(() => {
    const interval = setInterval(fetchNotifications, 30000); // Check every 30 seconds
    return () => clearInterval(interval);
  }, [profile?.id, token]);

  const fetchDevices = async () => {
    setDevicesLoading(true);
    setDevicesError('');
    try {
      const response = await fetch(`${API_URL}/index.php/api/patient/devices`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      if (!response.ok) {
        const text = await response.text().catch(() => '');
        throw new Error(`API responded with ${response.status}: ${text}`);
      }
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setDevices(data.devices || []);
      } else {
        setDevices([]);
        setDevicesError(data.message || 'Failed to fetch devices');
      }
    } catch (err) {
      setDevices([]);
      setDevicesError(err.message || 'Network error');
    } finally {
      setDevicesLoading(false);
    }
  };

  // Notification helpers
  const addNotification = ({ title = null, message = '', type = 'info' }) => {
    const n = { id: Date.now() + Math.floor(Math.random() * 1000), title, message, type, timestamp: new Date().toISOString(), read: false };
    setNotifications(prev => [n, ...prev]);
  };

  useEffect(() => {
    window.appNotify = (payload) => {
      try {
        window.dispatchEvent(new CustomEvent('app-notification', { detail: payload }));
      } catch (e) { console.error('appNotify error', e); }
    };

    const handler = (e) => {
      if (e && e.detail) {
        if (!e.detail.toastOnly) addNotification(e.detail);
      }
    };

    window.addEventListener('app-notification', handler);
    return () => window.removeEventListener('app-notification', handler);
  }, []);

  const unreadCount = notifications.filter(n => !n.read).length;

  const positionNotifPanel = () => {
    try {
      if (!headerRef.current) return setNotifPanelStyle({ top: 64 });
      const hdr = headerRef.current.getBoundingClientRect();
      const panelWidth = 340;
      const top = Math.round(hdr.bottom + window.scrollY + 8);
      let left = Math.round(hdr.right - panelWidth - 8);
      if (left < 8) left = 8;
      if (left + panelWidth > window.innerWidth) left = window.innerWidth - panelWidth - 8;
      setNotifPanelStyle({ top: `${top}px`, left: `${left}px`, position: 'fixed' });
    } catch (e) { console.error('positionNotifPanel error', e); }
  };

  useEffect(() => {
    if (!notifPanelOpen) return;
    positionNotifPanel();
    const onScroll = () => positionNotifPanel();
    const onResize = () => positionNotifPanel();
    const onDocClick = (e) => {
      const tgt = e.target;
      if (notifPanelRef.current && bellBtnRef.current) {
        if (!notifPanelRef.current.contains(tgt) && !bellBtnRef.current.contains(tgt)) {
          setNotifPanelOpen(false);
        }
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onResize);
    document.addEventListener('mousedown', onDocClick);
    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onResize);
      document.removeEventListener('mousedown', onDocClick);
    };
  }, [notifPanelOpen]);

  const clearNotifications = async () => {
    try {
      console.log('🧹 Clearing all notifications...');
      setNotifications([]);
      setNotifPanelOpen(false);
      
      const response = await fetch(`${API_URL}/index.php/api/notifications/dismiss-all`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: profile?.id || profile?.user_id })
      });
      
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'All notifications cleared permanently', type: 'info', toastOnly: true });
      }
    } catch (err) {
      console.error('Failed to clear notifications:', err);
    }
  };

  const fetchDoctors = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${API_URL}/index.php/api/patient/doctors`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setDoctors(data.doctors || []);
      }
    } catch (err) {
      console.error('Failed to fetch doctors');
    } finally {
      setLoading(false);
    }
  };

  const fetchWithRetry = async (url, options = {}, retries = 3, backoff = 1000) => {
    try {
      const response = await fetch(url, options);
      if (!response.ok && retries > 0) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response;
    } catch (err) {
      if (retries > 0) {
        console.warn(`Fetch failed, retrying... (${retries} left)`, url);
        await new Promise(resolve => setTimeout(resolve, backoff));
        return fetchWithRetry(url, options, retries - 1, backoff * 2);
      }
      throw err;
    }
  };

  const fetchSchedules = async (dateFilter = null) => {
    try {
      setSchedulesLoading(true);
      const filterDate = dateFilter || scheduleFilterDate || new Date().toISOString().split('T')[0];
      
      const response = await fetchWithRetry(`${API_URL}/index.php/api/schedule/today`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          token,
          start_date: filterDate,
          end_date: filterDate
        })
      });
      
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setSchedules(data.schedules || []);
      } else {
        setSchedules([]);
      }
    } catch (err) {
      console.error('🚨 Schedule fetch exception:', err);
      setSchedules([]);
    } finally {
      setSchedulesLoading(false);
    }
  };

  const fetchTemperature = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/temperature/current`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setTemperature(data.temperature);
      }
    } catch (err) {
      console.error('Failed to fetch temperature:', err);
    }
  };

  const fetchTempHistory = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/temperature/history?days=7`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setTempHistory(data.history || []);
      }
    } catch (err) {
      console.error('Failed to fetch temperature history:', err);
    }
  };

  const fetchStats = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/schedule/stats`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setStats(data);
      }
    } catch (err) {
      console.error('🚨 Stats fetch exception:', err);
    }
  };

  const fetchArticles = async () => {
    try {
      setArticlesLoading(true);
      const response = await fetch(`${API_URL}/index.php/api/articles/list`);
      const data = await response.json();
      if (data && data.status === 'SUCCESS') {
        setArticles(data.articles || []);
      }
    } catch (err) {
      console.error('Failed to fetch articles:', err);
      setArticles([]);
    } finally {
      setArticlesLoading(false);
    }
  };

  const handleViewArticle = async (articleId) => {
    try {
      if (articleCacheRef.current[articleId]) {
        setSelectedArticle(articleCacheRef.current[articleId]);
        setIsArticleLoading(false);
        return;
      }
      setIsArticleLoading(true);
      const resp = await fetch(`${API_URL}/index.php/api/articles/view`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ article_id: articleId })
      });
      const data = await resp.json();
      if (data && data.status === 'SUCCESS' && data.article) {
        articleCacheRef.current[articleId] = data.article;
        setSelectedArticle(data.article);
      }
    } catch (err) {
      console.error('Failed to track/fetch article detail:', err);
    } finally {
      setIsArticleLoading(false);
    }
  };

  const handleCreateSchedule = async (e) => {
    e.preventDefault();
    try {
      setIsCreatingSchedule(true);
      const response = await fetchWithRetry(`${API_URL}/index.php/api/schedule/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token,
          type: newSchedule.type,
          schedule_date: newSchedule.schedule_date,
          hour: parseInt(newSchedule.hour),
          minute: parseInt(newSchedule.minute),
          description: newSchedule.description
        })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Schedule created successfully', type: 'success' });
        setNewSchedule({ 
          type: 'MEDICINE', 
          schedule_date: new Date().toISOString().split('T')[0], 
          ...getCurrentTime(), 
          description: '' 
        });
        setShowAddForm(false);
        fetchSchedules(newSchedule.schedule_date);
      }
    } catch (err) {
      console.error('🚨 Schedule creation exception:', err);
    } finally {
      setIsCreatingSchedule(false);
    }
  };

  const handleCompleteSchedule = async (scheduleId, fromModal = false) => {
    try {
      if (fromModal) setIsCompletingInModal(true);
      else setIsDeletingSchedule(scheduleId);

      const response = await fetchWithRetry(`${API_URL}/index.php/api/schedule/complete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, schedule_id: scheduleId })
      });
      const data = await response.json();
      
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Schedule marked as complete', type: 'success' });
        if (fromModal) setActiveMedicineAlert(null);
        fetchSchedules();
      }
    } catch (err) {
      console.error('🚨 Complete schedule exception:', err);
    } finally {
      setIsCompletingInModal(false);
      setIsDeletingSchedule(null);
    }
  };

  const handleSnoozeSchedule = async (scheduleId) => {
    try {
      setIsSnoozingInModal(true);
      const response = await fetchWithRetry(`${API_URL}/index.php/api/schedule/snooze`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, schedule_id: scheduleId })
      });
      const data = await response.json();
      
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: `Snoozed until ${data.new_time}`, type: 'info' });
        setActiveMedicineAlert(null);
        fetchSchedules();
      }
    } catch (err) {
      console.error('🚨 Snooze schedule exception:', err);
    } finally {
      setIsSnoozingInModal(false);
    }
  };

  const handleDeleteSchedule = async (scheduleId) => {
    try {
      setIsDeletingSchedule(scheduleId);
      const response = await fetchWithRetry(`${API_URL}/index.php/api/schedule/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          token, 
          schedule_id: scheduleId,
          user_id: profile?.id || profile?.user_id
        })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Schedule deleted', type: 'success' });
        fetchSchedules();
      }
    } catch (err) {
      console.error('Failed to delete schedule:', err);
    } finally {
      setIsDeletingSchedule(null);
    }
  };

  const completePairingWithMac = async (macAddress) => {
    if (!macAddress) return;
    setLoading(true);
    try {
      const tokenResponse = await fetch(`${API_URL}/index.php/api/auth/generate-pairing-token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const tokenData = await tokenResponse.json();
      if (tokenData.status !== 'SUCCESS') {
        setLoading(false);
        return;
      }
      const response = await fetch(`${API_URL}/index.php/api/auth/complete-pairing`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pairing_token: tokenData.pairing_token,
          mac_address: macAddress.toUpperCase().trim(),
          device_name: `Smart Medi Box - ${macAddress}`,
          token
        })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setShowQRScanner(false);
        setShowDeviceFound(false);
        fetchDevices();
        window.appNotify({ message: 'Device paired successfully', type: 'success' });
      }
    } catch (err) {
      console.error('Pairing error:', err);
    } finally {
      setLoading(false);
    }
  };

  const [showUnpairConfirm, setShowUnpairConfirm] = useState(false);
  const [deviceToUnpair, setDeviceToUnpair] = useState(null);

  const unpairDevice = async () => {
    if (!deviceToUnpair) return;
    setLoading(true);
    try {
      const response = await fetch(`${API_URL}/index.php/api/patient/unpair-device`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, device_id: deviceToUnpair })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setShowUnpairConfirm(false);
        fetchDevices();
        window.appNotify({ message: 'Device unpaired successfully', type: 'success' });
      }
    } catch (err) {
      console.error('Unpair error:', err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="dashboard patient-dashboard">
      <div className="dashboard-header" ref={headerRef}>
        <div className="header-content">
          <h1>👤 Welcome, {profile.name}</h1>
          <p>NIC: {profile.nic} | ID: {profile.id}</p>
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <button className="btn-icon" ref={bellBtnRef} onClick={() => setNotifPanelOpen(!notifPanelOpen)} title="Notifications">
            <Bell size={18} />
            {unreadCount > 0 && <span className="notif-badge">{unreadCount}</span>}
          </button>
          <button className="btn-secondary" onClick={handleLogoutClick}>
            <LogOut size={18} /> Logout
          </button>
        </div>
      </div>

      {notifPanelOpen && (
        <div className="notif-panel" ref={notifPanelRef} style={notifPanelStyle}>
          <div className="notif-panel-header">
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <strong>Notifications</strong>
              <button 
                className="btn-link" 
                onClick={async () => {
                  try {
                    const btn = document.activeElement;
                    if (btn) btn.disabled = true;
                    await fetchNotifications();
                    if (btn) btn.disabled = false;
                    window.appNotify({ message: 'Notifications synced', type: 'info', toastOnly: true });
                  } catch (e) { console.error('Sync error:', e); }
                }}
                style={{ fontSize: '11px', background: '#f0f0f0', padding: '2px 6px', borderRadius: '4px' }}
              >
                Sync Now
              </button>
            </div>
            <button className="btn-link" onClick={clearNotifications}>Clear</button>
          </div>
          <div className="notif-list">
            {notifications.length === 0 && <div className="notif-empty">No notifications</div>}
            {notifications.map(n => (
              <div key={n.id} className={`notif-item ${n.type || ''}`}>
                <div className="notif-message">{n.message}</div>
                <div className="notif-meta">
                  <small>{new Date(n.timestamp).toLocaleString()}</small>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="dashboard-tabs">
        {['devices', 'doctors', 'dashboard', 'schedules', 'temperature', 'stats', 'articles'].map(tab => (
          <button
            key={tab}
            className={`tab-btn ${activeTab === tab ? 'active' : ''}`}
            onClick={() => setActiveTab(tab)}
          >
            {tab === 'devices' ? '📱 Devices' : tab === 'doctors' ? '👨‍⚕️ My Doctors' : tab === 'dashboard' ? '📊 Dashboard' : tab === 'schedules' ? '⏰ Schedules' : tab === 'temperature' ? '🌡️ Temperature' : tab === 'stats' ? '📈 Stats' : '📰 Articles'}
          </button>
        ))}
      </div>

      <div className="dashboard-content">
        {activeTab === 'devices' && (
          <div className="section">
            <div className="section-header">
              <h2>Paired Devices</h2>
              {!devicesLoading && (devices && devices.length === 0) && (
                <button className="btn-primary" onClick={() => setShowQRScanner(!showQRScanner)}>
                  <Plus size={18} /> {showQRScanner ? 'Cancel Scan' : 'Scan Device QR'}
                </button>
              )}
            </div>

            {showQRScanner && (
              <div className="qr-scanner-box">
                <div id="html5qr-scanner" style={{ width: '100%' }} />
              </div>
            )}

            {showDeviceFound && scannedMac && (
              <div className="device-found-box">
                <h3>Device Found: {scannedMac}</h3>
                <div style={{ display: 'flex', gap: 8, marginTop: 12, justifyContent: 'center' }}>
                  <button className="btn-primary" onClick={() => completePairingWithMac(scannedMac)} disabled={loading}>
                    {loading ? 'Pairing...' : 'Pair Device'}
                  </button>
                  <button className="btn-secondary" onClick={() => setShowDeviceFound(false)}>Cancel</button>
                </div>
              </div>
            )}

            {devicesLoading ? <p className="empty-state">Loading devices...</p> : devices.length === 0 ? <p className="empty-state">No devices paired yet.</p> : (
              <div className="devices-list">
                {devices.map(device => (
                  <div key={device.device_id} className="device-card">
                    <h3>{device.device_name || 'Smart Medi Box'}</h3>
                    <p>MAC: {device.mac_address}</p>
                    <button className="btn-danger" onClick={() => { setDeviceToUnpair(device.device_id); setShowUnpairConfirm(true); }}>Unpair</button>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {activeTab === 'dashboard' && (
          <div className="section">
            <div className="dashboard-grid">
              <div className="card">
                <div className="card-header">
                  <Thermometer size={24} />
                  <h3>Current Temperature</h3>
                </div>
                {temperature ? (
                  <div className="card-content">
                    <div className="temp-display">
                      <div className="temp-main">{temperature.internal_temp}°C</div>
                      <div className="temp-sub">Target: {temperature.target_temp}°C</div>
                    </div>
                  </div>
                ) : <p className="placeholder">Loading...</p>}
              </div>

              <div className="card">
                <div className="card-header">
                  <Clock size={24} />
                  <h3>Today's Schedules</h3>
                </div>
                <div className="card-content">
                  {schedules.length > 0 ? (
                    <div className="schedule-list">
                      {schedules.slice(0, 5).map(sched => (
                        <div key={sched.schedule_id} className="schedule-item">
                          <div className="schedule-time">{String(sched.hour).padStart(2, '0')}:{String(sched.minute).padStart(2, '0')}</div>
                          <div className="schedule-details">
                            <div className="schedule-type">{sched.type}</div>
                            <div className="schedule-status">{sched.is_completed ? '✅ Done' : '⏳ Pending'}</div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : <p className="placeholder">No schedules</p>}
                </div>
              </div>

              <div className="card">
                <div className="card-header"><Users size={24} /><h3>Profile</h3></div>
                <div className="card-content">
                  <p><strong>Name:</strong> {profile.name}</p>
                  <p><strong>Email:</strong> {profile.email}</p>
                </div>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'schedules' && (
          <div className="section schedules-minimal">
            <div className="section-header">
              <h2>Medication Schedule</h2>
              <div className="header-actions">
                <input type="date" value={scheduleFilterDate} onChange={(e) => { setScheduleFilterDate(e.target.value); fetchSchedules(e.target.value); }} className="filter-date-minimal" />
                <button className="btn-primary" onClick={() => setShowAddForm(!showAddForm)}>
                  {showAddForm ? <X size={18} /> : <Plus size={18} />} {showAddForm ? 'Close' : 'Add New'}
                </button>
              </div>
            </div>

            {showAddForm && (
              <div className="add-schedule-panel">
                <form onSubmit={handleCreateSchedule} className="minimal-form">
                  <div className="form-group"><label>Type</label><select value={newSchedule.type} onChange={(e) => setNewSchedule({...newSchedule, type: e.target.value})}><option value="MEDICINE">💊 Medicine</option><option value="FOOD">🍽️ Food</option></select></div>
                  <div className="form-group"><label>Date</label><input type="date" value={newSchedule.schedule_date} onChange={(e) => setNewSchedule({...newSchedule, schedule_date: e.target.value})} /></div>
                  <div className="form-row">
                    <div className="form-group"><label>Hour</label><input type="number" value={newSchedule.hour} onChange={(e) => setNewSchedule({...newSchedule, hour: e.target.value})} /></div>
                    <div className="form-group"><label>Min</label><input type="number" value={newSchedule.minute} onChange={(e) => setNewSchedule({...newSchedule, minute: e.target.value})} /></div>
                  </div>
                  <button type="submit" className="btn-primary btn-large" disabled={isCreatingSchedule}>{isCreatingSchedule ? <div className="spinner-mini"></div> : 'Save Reminder'}</button>
                </form>
              </div>
            )}

            <div className="timeline-container">
              {schedulesLoading ? <div className="loading-container"><div className="spinner"></div></div> : (
                <div className="minimal-timeline">
                  {schedules.map(sched => (
                    <div key={sched.schedule_id} className={`timeline-item ${sched.is_completed ? 'is-done' : ''}`}>
                      <div className="timeline-time">{String(sched.hour).padStart(2, '0')}:{String(sched.minute).padStart(2, '0')}</div>
                      <div className="timeline-dot"></div>
                      <div className="timeline-card">
                        <div className="card-info"><h4>{sched.type}</h4><p>{sched.description}</p></div>
                        <div className="card-actions">
                          {!sched.is_completed && <button className="btn-success" onClick={() => handleCompleteSchedule(sched.schedule_id)}><Check size={18} /></button>}
                          <button className="btn-danger" onClick={() => { if(window.confirm('Delete?')) handleDeleteSchedule(sched.schedule_id); }}><Trash2 size={16} /></button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Modals */}
      {showLogoutConfirm && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h2>Logout?</h2>
            <div className="modal-buttons">
              <button className="btn-secondary" onClick={handleCancelLogout}>Cancel</button>
              <button className="btn-danger" onClick={handleConfirmLogout}>Logout</button>
            </div>
          </div>
        </div>
      )}

      {activeMedicineAlert && (
        <div className="modal-overlay urgent-alert">
          <div className="modal-content pulse-border">
            <div className="urgent-icon">💊</div>
            <h2>Medicine Reminder!</h2>
            <p className="urgent-message">{activeMedicineAlert.message}</p>
            <div className="modal-buttons">
              <button className="btn-success btn-large" disabled={isCompletingInModal || isSnoozingInModal} onClick={() => handleCompleteSchedule(activeMedicineAlert.schedule_id, true)}>
                {isCompletingInModal ? <div className="spinner-mini"></div> : "OK, I'm Taking It"}
              </button>
              <button className="btn-secondary btn-large" disabled={isCompletingInModal || isSnoozingInModal} onClick={() => handleSnoozeSchedule(activeMedicineAlert.schedule_id)}>
                {isSnoozingInModal ? <div className="spinner-mini"></div> : "Snooze 5m"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// ==================== Doctor Dashboard ====================
const DoctorDashboard = ({ profile, token, onLogout }) => {
  const [activeTab, setActiveTab] = useState('patients');
  const [patients, setPatients] = useState([]);
  const [articles, setArticles] = useState([]);
  const [showNewArticle, setShowNewArticle] = useState(false);
  const [newArticle, setNewArticle] = useState({ title: '', content: '' });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (activeTab === 'patients') fetchPatients();
    else if (activeTab === 'articles') fetchArticles();
  }, [activeTab]);

  const fetchPatients = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${API_URL}/index.php/api/doctor/patients`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') setPatients(data.patients || []);
    } catch (err) { console.error(err); } finally { setLoading(false); }
  };

  const fetchArticles = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/articles/my`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') setArticles(data.articles || []);
    } catch (err) { console.error(err); }
  };

  return (
    <div className="dashboard">
      <div className="dashboard-header">
        <h1>👨‍⚕️ Welcome, Dr. {profile.name}</h1>
        <button className="btn-secondary" onClick={onLogout}>Logout</button>
      </div>
      <div className="dashboard-tabs">
        <button className={`tab-btn ${activeTab === 'patients' ? 'active' : ''}`} onClick={() => setActiveTab('patients')}>Patients</button>
        <button className={`tab-btn ${activeTab === 'articles' ? 'active' : ''}`} onClick={() => setActiveTab('articles')}>Articles</button>
      </div>
      <div className="dashboard-content">
        {activeTab === 'patients' && (
          <div className="section">
            <h2>My Patients</h2>
            <div className="patients-list">
              {patients.map(p => (
                <div key={p.id} className="patient-card">
                  <h3>{p.name}</h3>
                  <p>NIC: {p.nic}</p>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

// ==================== Main App ====================
export default function App() {
  const [currentUser, setCurrentUser] = useState(null);
  const [currentPage, setCurrentPage] = useState('login');
  const [toasts, setToasts] = useState([]);

  useEffect(() => {
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');
    const profile = localStorage.getItem('profile');
    if (token && role && profile) {
      setCurrentUser({ token, role, profile: JSON.parse(profile) });
    }
  }, []);

  const handleLoginSuccess = (data) => {
    localStorage.setItem('token', data.token);
    localStorage.setItem('user_id', data.user_id);
    localStorage.setItem('role', data.role);
    localStorage.setItem('profile', JSON.stringify(data.profile));
    setCurrentUser(data);
  };

  const handleLogout = () => {
    localStorage.clear();
    setCurrentUser(null);
    setCurrentPage('login');
  };

  return (
    <div className="app">
      {!currentUser ? (
        currentPage === 'login' ? <LoginScreen onLoginSuccess={handleLoginSuccess} /> : <SignupScreen onSignupSuccess={handleLoginSuccess} />
      ) : currentUser.role === 'PATIENT' ? (
        <PatientDashboard profile={currentUser.profile} token={currentUser.token} onLogout={handleLogout} />
      ) : (
        <DoctorDashboard profile={currentUser.profile} token={currentUser.token} onLogout={handleLogout} />
      )}
    </div>
  );
}
