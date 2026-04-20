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
  const [newSchedule, setNewSchedule] = useState({ 
    type: 'MEDICINE', 
    schedule_date: new Date().toISOString().split('T')[0],
    hour: 9, 
    minute: 0, 
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
    console.log('Current showLogoutConfirm state:', showLogoutConfirm);
    setShowLogoutConfirm(true);
    console.log('Set showLogoutConfirm to true');
  };

  const handleConfirmLogout = () => {
    console.log('🔓 Confirming logout');
    setShowLogoutConfirm(false);
    onLogout();
  };

  const handleCancelLogout = () => {
    console.log('❌ Logout cancelled');
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
          
          // CRITICAL: If there are NEW medicine alarms, trigger the popup
          const medicineAlarm = newOnes.find(n => n.rawType === 'ALARM_MEDICINE');
          if (medicineAlarm) {
            setActiveMedicineAlert(medicineAlarm);
          }
          
          return [...newOnes, ...prev];
        });
        
        if (formattedNotifs.length > 0) {
          console.log(`✅ Fetched ${formattedNotifs.length} notifications`);
        }
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
      // Network-level errors will throw; handle non-OK responses explicitly
      if (!response.ok) {
        const text = await response.text().catch(() => '');
        throw new Error(`API responded with ${response.status}: ${text}`);
      }
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setDevices(data.devices || []);
      } else {
        console.error('Fetch devices error:', data.message || 'Unknown error');
        setDevices([]);
        setDevicesError(data.message || 'Failed to fetch devices');
      }
    } catch (err) {
      console.error('Failed to fetch devices:', err);
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
    // Expose global dispatch helper so other modules can push notifications
    window.appNotify = (payload) => {
      try {
        window.dispatchEvent(new CustomEvent('app-notification', { detail: payload }));
      } catch (e) { console.error('appNotify error', e); }
    };

    const handler = (e) => {
      if (e && e.detail) {
        // Only add to the notification list when not a toast-only message
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

  const clearNotifications = () => {
    setNotifications([]);
    setNotifPanelOpen(false);
    // Only show as transient toast; do not re-add to the notifications list
    window.appNotify({ message: 'Notifications cleared', type: 'info', toastOnly: true });
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
      console.log('📊 Fetching schedule stats...');
      const response = await fetch(`${API_URL}/index.php/api/schedule/stats`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      console.log('📡 Stats API Response Status:', response.status);
      const data = await response.json();
      console.log('📊 Stats API Response Data:', data);
      if (data.status === 'SUCCESS') {
        console.log('✅ Stats fetched successfully');
        setStats(data);
      } else {
        console.error('❌ Stats fetch failed:', data.message);
      }
    } catch (err) {
      console.error('🚨 Stats fetch exception:', err);
    }
  };

  const fetchArticles = async () => {
    try {
      setArticlesLoading(true);
      const response = await fetch(`${API_URL}/index.php/api/articles/list`);
      const raw = await response.text();

      if (!raw) {
        console.error('Empty articles API response');
        setArticles([]);
        return;
      }

      let data;
      try {
        data = JSON.parse(raw);
      } catch (parseErr) {
        console.error('Failed to parse articles API JSON:', parseErr);
        console.error('Raw response:', raw);
        setArticles([]);
        return;
      }

      if (data && data.status === 'SUCCESS') {
        setArticles(data.articles || []);
      } else {
        console.error('Failed to fetch articles:', data && data.message ? data.message : data);
        setArticles([]);
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
      // If we have a cached full article, use it immediately
      if (articleCacheRef.current[articleId]) {
        setSelectedArticle(articleCacheRef.current[articleId]);
        setIsArticleLoading(false);
        return;
      }

      // Show loading state (modal already shows excerpt when opening)
      setIsArticleLoading(true);

      const resp = await fetch(`${API_URL}/index.php/api/articles/view`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ article_id: articleId })
      });
      const data = await resp.json();
      if (data && data.status === 'SUCCESS' && data.article) {
        // cache and set
        articleCacheRef.current[articleId] = data.article;
        setSelectedArticle(data.article);
      } else {
        console.warn('Article detail not returned by API, falling back to list item');
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
      console.log('💊 Creating new schedule:', newSchedule);
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
        const today = new Date().toISOString().split('T')[0];
        setNewSchedule({ type: 'MEDICINE', schedule_date: today, hour: 9, minute: 0, description: '' });
        setShowAddForm(false);
        fetchSchedules(newSchedule.schedule_date);
      } else {
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to create schedule'), type: 'error' });
      }
    } catch (err) {
      console.error('🚨 Schedule creation exception:', err);
      window.appNotify({ message: 'Failed to create schedule: ' + err.message, type: 'error' });
    } finally {
      setIsCreatingSchedule(false);
    }
  };

  const handleCompleteSchedule = async (scheduleId) => {
    try {
      setIsDeletingSchedule(scheduleId); // Reusing as action loading
      const response = await fetchWithRetry(`${API_URL}/index.php/api/schedule/complete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, schedule_id: scheduleId })
      });
      const data = await response.json();
      
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Schedule marked as complete', type: 'success' });
        fetchSchedules();
      } else {
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to update'), type: 'error' });
      }
    } catch (err) {
      console.error('🚨 Complete schedule exception:', err);
    } finally {
      setIsDeletingSchedule(null);
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
      } else {
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to delete'), type: 'error' });
      }
    } catch (err) {
      console.error('Failed to delete schedule:', err);
    } finally {
      setIsDeletingSchedule(null);
    }
  };

  const generatePairingToken = async () => {
    try {
      // Prevent creating a pairing token if user already has a device (one account -> one device)
      if (devices && devices.length > 0) {
        window.appNotify({ message: 'You already have a paired device. Unpair first to pair a new device.', type: 'info' });
        return;
      }
      const response = await fetch(`${API_URL}/index.php/api/auth/generate-pairing-token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setPairingToken(data.pairing_token);
        setShowPairingCode(true);
      }
    } catch (err) {
      console.error('Failed to generate pairing token');
    }
  };

  const handleQRScan = (decodedText) => {
    // decodedText should be the device MAC address from the QR code
    console.log('QR Code Scanned:', decodedText);
    setScannedMac(decodedText.trim());
    setScannerError('');
    // Auto-stop scanner after successful scan
    if (qrInstanceRef.current) {
      qrInstanceRef.current.pause();
      setScannerStarted(false);
    }
  };

  const startQRScanner = async () => {
    if (scannerStarted) return;
    
    setScannerError('');
    
    try {
      // Check if the container exists
      if (!qrScannerRef.current) {
        setScannerError('Scanner container not found. Please try refreshing the page.');
        console.error('qrScannerRef.current is null');
        return;
      }

      // Clear any existing content and create the qr-reader div
      qrScannerRef.current.innerHTML = '<div id="qr-reader"></div>';
      
      // Small delay to ensure DOM is updated
      await new Promise(resolve => setTimeout(resolve, 100));
      
      // Verify the qr-reader div was created
      const qrReaderDiv = document.getElementById('qr-reader');
      if (!qrReaderDiv) {
        setScannerError('Failed to create scanner element. Please try again.');
        console.error('qr-reader div not found after creation');
        return;
      }

      // Use lower-level Html5Qrcode to control camera selection and avoid
      // library-injected dropdown overlays. Try Html5Qrcode.getCameras(),
      // fallback to navigator.mediaDevices.enumerateDevices if needed.
      let devices = [];
      try {
        devices = await Html5Qrcode.getCameras();
      } catch (e) {
        console.warn('Html5Qrcode.getCameras failed, falling back to enumerateDevices', e);
      }

      if (!devices || devices.length === 0) {
        try {
          const list = await navigator.mediaDevices.enumerateDevices();
          devices = list.filter(d => d.kind === 'videoinput').map(d => ({ id: d.deviceId, label: d.label || d.deviceId }));
        } catch (e) {
          console.error('enumerateDevices failed', e);
        }
      }

      setCameras(devices || []);

      // Auto-select the first camera if none selected
      const initialCamera = selectedCameraId || (devices && devices.length ? devices[0].id : null);
      if (!initialCamera) {
        setScannerError('No camera found on this device');
        return;
      }

      const html5Qr = new Html5Qrcode('qr-reader');
      qrInstanceRef.current = html5Qr;

      // If on mobile, try to default to the back camera using facingMode first
      const isMobile = typeof navigator !== 'undefined' && /Mobi|Android|iPhone|iPad|Mobile/i.test(navigator.userAgent || '');
      if (isMobile && !selectedCameraId) {
        try {
          await html5Qr.start(
            { facingMode: { ideal: 'environment' } },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => {
              console.log('QR Scanned:', decodedText);
              const mac = decodedText.trim();
              setScannedMac(mac);
              setScannerError('');
              try { html5Qr.pause(); html5Qr.stop().catch(() => {}); } catch (e) {}
              qrInstanceRef.current = null;
              setScannerStarted(false);
              setShowQRScanner(false);
              setShowDeviceFound(true);
            },
            (errorMessage) => { /* ignore minor scan errors */ }
          );
          setScannerStarted(true);
          console.log('QR Scanner started with facingMode=environment on mobile');
          return;
        } catch (fmErr) {
          console.warn('FacingMode start failed, falling back to device list start', fmErr);
        }
      }

      // Try to start with the selected camera; if it fails, attempt other cameras sequentially
      const probeCamera = async (camDeviceId) => {
        try {
          const s = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: camDeviceId } } });
          try { s.getTracks().forEach(t => t.stop()); } catch (e) {}
          return true;
        } catch (e1) {
          try {
            const s2 = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { ideal: camDeviceId } } });
            try { s2.getTracks().forEach(t => t.stop()); } catch (e) {}
            return true;
          } catch (e2) {
            try {
              const s3 = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } });
              try { s3.getTracks().forEach(t => t.stop()); } catch (e) {}
              return true;
            } catch (e3) {
              return false;
            }
          }
        }
      };

      const tryCameras = selectedCameraId ? [selectedCameraId, ...devices.filter(d => d.id !== selectedCameraId).map(d => d.id)] : devices.map(d => d.id);
      let started = false;
      for (const camId of tryCameras) {
        try {
          const ok = await probeCamera(camId);
          if (!ok) {
            console.warn('Camera probe unable to obtain stream for', camId);
            continue;
          }

          try {
            await html5Qr.start(
              camId,
              { fps: 10, qrbox: { width: 250, height: 250 } },
              (decodedText) => {
                console.log('QR Scanned:', decodedText);
                const mac = decodedText.trim();
                setScannedMac(mac);
                setScannerError('');
                try { html5Qr.pause(); html5Qr.stop().catch(() => {}); } catch (e) {}
                qrInstanceRef.current = null;
                setScannerStarted(false);
                setShowQRScanner(false);
                setShowDeviceFound(true);
              },
              (errorMessage) => { /* ignore minor scan errors */ }
            );
            setSelectedCameraId(camId);
            started = true;
            break;
          } catch (startErr) {
            console.warn('Failed to start html5Qr on', camId, startErr);
            // Try fallback: start with facingMode constraint
            try {
              await html5Qr.start(
                { facingMode: { ideal: 'environment' } },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                  console.log('QR Scanned (fallback):', decodedText);
                  const mac = decodedText.trim();
                  setScannedMac(mac);
                  setScannerError('');
                  try { html5Qr.pause(); html5Qr.stop().catch(() => {}); } catch (e) {}
                  qrInstanceRef.current = null;
                  setScannerStarted(false);
                  setShowQRScanner(false);
                  setShowDeviceFound(true);
                },
                (errorMessage) => { /* ignore */ }
              );
              setSelectedCameraId(camId);
              started = true;
              break;
            } catch (fallbackErr) {
              console.warn('Fallback start also failed for', camId, fallbackErr);
            }
          }
        } catch (outerErr) {
          console.warn('Unexpected error while probing/starting camera', camId, outerErr);
        }
      }

      if (!started) {
        setScannerError('Unable to open any camera. Check browser permissions or close other apps using the camera.');
        try { html5Qr.clear(); } catch (e) {}
        qrInstanceRef.current = null;
        setScannerStarted(false);
        return;
      }

      setScannerStarted(true);
      console.log('QR Scanner started successfully (Html5Qrcode)');
    } catch (error) {
      console.error('Failed to start QR scanner:', error);
      setScannerError('Camera access denied or not available. Please check permissions: ' + error.message);
      setScannerStarted(false);
    }
  };

  const scanImageFile = async (file) => {
    if (!file) return;
    setScannerError('');
    setScannerStarted(false);

    // If a live camera scan is running, stop it first so scanFile can run
    let restartCameraAfter = false;
    if (qrInstanceRef.current && scannerStarted) {
      restartCameraAfter = true;
      try {
        await stopQRScanner();
        await new Promise(r => setTimeout(r, 200));
      } catch (e) { console.warn('Error stopping live scanner before file scan', e); }
    }

    // Create a temporary off-DOM container to avoid interfering with live scanner
    const tempContainerId = 'qr-file-scanner-' + Date.now();
    const container = document.createElement('div');
    container.id = tempContainerId;
    container.style.cssText = 'position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;';
    document.body.appendChild(container);

    const tempInstance = new Html5Qrcode(tempContainerId);
    try {
      const result = await tempInstance.scanFile(file, true);
      console.log('scanFile result:', result);
      let decoded = null;
      if (!result) {
        decoded = null;
      } else if (typeof result === 'string') {
        decoded = result;
      } else if (result.decodedText) {
        decoded = result.decodedText;
      } else if (Array.isArray(result) && result.length) {
        decoded = result[0].decodedText || result[0];
      }

      if (decoded) {
        const mac = String(decoded).trim();
        setScannedMac(mac);
        setShowDeviceFound(true);
        setShowQRScanner(false);
        setScannerError('');
      } else {
        setScannerError('No QR code found in the selected image.');
      }
    } catch (err) {
      console.error('Image scan failed:', err);
      if (err && err.name === 'AbortError') {
        setScannerError('Image scanning was aborted.');
      } else {
        setScannerError('Failed to decode image. Make sure the QR code is clear and try a different image.');
      }
    } finally {
      try { await tempInstance.clear(); } catch (e) { /* ignore */ }
      try { document.body.removeChild(container); } catch (e) { /* ignore */ }
      if (restartCameraAfter && showQRScanner) {
        // Restart camera scanner after a short delay to let browser free the device
        setTimeout(() => startQRScanner().catch(() => {}), 400);
      }
    }
  };

  const stopQRScanner = async () => {
    try {
      if (qrInstanceRef.current) {
        // Defensive stop for either Html5Qrcode or Html5QrcodeScanner
        try { if (qrInstanceRef.current.pause) await qrInstanceRef.current.pause(); } catch(e) {}
        try { if (qrInstanceRef.current.stop) await qrInstanceRef.current.stop(); } catch(e) {}
        try { if (qrInstanceRef.current.clear) await qrInstanceRef.current.clear(); } catch(e) {}
        qrInstanceRef.current = null;

        // Clear the scanner element
        if (qrScannerRef.current) {
          qrScannerRef.current.innerHTML = '';
        }
      }
      // Additionally stop any media tracks that might still be attached to video elements
      try {
        const vids = document.querySelectorAll('#qr-reader video');
        vids.forEach(v => {
          try {
            const s = v.srcObject;
            if (s && s.getTracks) s.getTracks().forEach(t => { try { t.stop(); } catch(e) {} });
            v.srcObject = null;
          } catch(e) {}
        });
      } catch(e) {}
      setScannerStarted(false);
    } catch (error) {
      console.error('Error stopping scanner:', error);
      setScannerStarted(false);
    }
  };

  // Explicit camera switch helper to ensure clean restart
  const switchCameraTo = async (camId) => {
    console.log('Switching camera to', camId);
    // Guard against undefined/null camera id from mobile selection bugs
    if (!camId) {
      console.error('switchCameraTo called with empty camId', { camId, cameras });
      // try fallback to first available camera
      if (cameras && cameras.length) {
        const fallback = cameras[0].id;
        console.warn('Falling back to first available camera:', fallback);
        setSelectedCameraId(fallback);
        // don't continue here; the useEffect watching selectedCameraId will call switchCameraTo again
      } else {
        setScannerError('No camera selected and no cameras available');
      }
      return;
    }
    try {
      await stopQRScanner();
      // small safety delay
      await new Promise(r => setTimeout(r, 250));

      // rebuild reader container
      if (qrScannerRef.current) qrScannerRef.current.innerHTML = '<div id="qr-reader"></div>';
      // Ensure the #qr-reader element is attached and has layout before creating Html5Qrcode
      const ensureReaderReady = async () => {
        const start = Date.now();
        let el = document.getElementById('qr-reader');
        while ((!el || (el && el.clientWidth === 0)) && Date.now() - start < 2000) {
          // try to set minimal sizing to help mobile layout engines
          if (el) {
            try { el.style.width = el.style.width || '320px'; el.style.height = el.style.height || '240px'; } catch(e){}
          }
          await new Promise(r => setTimeout(r, 100));
          el = document.getElementById('qr-reader');
        }
        return document.getElementById('qr-reader');
      };

      const readerEl = await ensureReaderReady();
      if (!readerEl) console.warn('qr-reader element not available or has zero size when switching camera');

      const html5Qr = new Html5Qrcode('qr-reader');
      qrInstanceRef.current = html5Qr;
      await html5Qr.start(
        camId,
        { fps: 10, qrbox: { width: 250, height: 250 } },
        (decoded) => {
          console.log('QR Scanned:', decoded);
          const mac = String(decoded).trim();
          setScannedMac(mac);
          setScannerError('');
          try { html5Qr.pause(); html5Qr.stop().catch(()=>{}); } catch(e){}
          qrInstanceRef.current = null;
          setScannerStarted(false);
          setShowQRScanner(false);
          setShowDeviceFound(true);
        },
        (err) => { /* ignore */ }
      );
      setScannerStarted(true);
      console.log('Camera switched and started on', camId);
    } catch (err) {
      console.error('Failed to switch/start camera', camId, err);
      const message = err && err.message ? err.message : (typeof err === 'string' ? err : JSON.stringify(err || 'unknown'));
      setScannerError('Failed to switch camera: ' + message);
      // if camera failed to start, clear selectedCameraId so UI doesn't remain stuck
      if (selectedCameraId === camId) {
        setSelectedCameraId(null);
      }
    }
  };

  // When using default Html5QrcodeScanner UI we do not control camera via selectedCameraId
  // Keep the old switch behavior available for programmatic instance but avoid interfering with library UI

  // When cameras list populates, pick the first camera by default
  useEffect(() => {
    if (cameras && cameras.length > 0 && !selectedCameraId) {
      setSelectedCameraId(cameras[0].id);
    }
  }, [cameras, selectedCameraId]);

  useEffect(() => {
    return () => {
      // Cleanup: stop scanner when component unmounts
      stopQRScanner();
    };
  }, []);

  // Auto-start scanner when the scan view is opened, stop when closed
  useEffect(() => {
    let scannerInstance = null;
    const manage = async () => {
      if (showQRScanner) {
        setScannerError('');
        try {
          // Use library default UI (Html5QrcodeScanner)
          const elId = 'html5qr-scanner';
          // clear any previous content
          const el = document.getElementById(elId);
          if (el) el.innerHTML = '';
          scannerInstance = new Html5QrcodeScanner(elId, { fps: 10, qrbox: 250 }, false);
          // render with mobile form factor true so library adapts UI
          scannerInstance.render((decodedText) => {
            const mac = String(decodedText).trim();
            setScannedMac(mac);
            setScannerError('');
            try { scannerInstance.clear().catch(()=>{}); } catch(e){}
            setScannerStarted(false);
            setShowQRScanner(false);
            setShowDeviceFound(true);
          }, (errorMessage, error) => {
            // ignore per-frame scan errors
          }, true);
        } catch (e) {
          console.error('Failed to render Html5QrcodeScanner', e);
          setScannerError('Failed to start scanner: ' + (e && e.message));
        }
      } else {
        try {
          if (window && window.__Html5QrcodeScannerInstance) {
            try { window.__Html5QrcodeScannerInstance.clear().catch(()=>{}); } catch(e){}
            window.__Html5QrcodeScannerInstance = null;
          }
          // also attempt to clear local scannerInstance
          if (scannerInstance) {
            try { scannerInstance.clear().catch(()=>{}); } catch(e){}
            scannerInstance = null;
          }
        } catch (e) {}
      }
    };
    manage().catch(e => console.error('Error managing scanner on visibility change', e));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [showQRScanner]);

  // Ensure scanner stops when user navigates away from Devices tab
  useEffect(() => {
    if (activeTab !== 'devices') {
      if (showQRScanner) setShowQRScanner(false);
      try { stopQRScanner(); } catch (e) {}
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

  const completePairingWithMac = async (macAddress) => {
    if (!macAddress) {
      setScannerError('Please enter or scan a valid device MAC address');
      return;
    }

    if (!token) {
      setScannerError('User authentication token not found. Please refresh and login again.');
      console.error('Token not available:', { currentUserToken: token, localStorage: localStorage.getItem('token') });
      return;
    }

    setLoading(true);
    try {
      console.log('Starting pairing with MAC:', macAddress, 'Token:', token.substring(0, 20) + '...');

      // First, generate a pairing token for authorization
      const tokenResponse = await fetch(`${API_URL}/index.php/api/auth/generate-pairing-token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });

      const tokenData = await tokenResponse.json();
      
      console.log('Pairing token response:', tokenData);
      
      if (tokenData.status !== 'SUCCESS') {
        setScannerError(`Failed to generate pairing token: ${tokenData.message || 'Unknown error'}`);
        console.error('Token generation failed:', tokenData);
        setLoading(false);
        return;
      }

      // Now complete pairing with the token and MAC address
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
      
      console.log('Complete pairing response:', data);
      
      if (data.status === 'SUCCESS') {
        setShowQRScanner(false);
        setScannedMac('');
        setManualMacInput('');
        setScannerError('');
        setShowDeviceFound(false);
        fetchDevices(); // Refresh device list
        window.appNotify({ message: 'Device paired successfully', type: 'success' });
      } else {
        setScannerError(`Failed to pair device: ${data.message || 'Unknown error'}`);
        setShowDeviceFound(false);
        console.error('Pairing failed:', data);
      }
    } catch (err) {
      console.error('Pairing error:', err);
      setScannerError('Network error: ' + err.message);
    } finally {
      setLoading(false);
    }
  };

  // Unpair device API
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
        setDeviceToUnpair(null);
        fetchDevices();
        window.appNotify({ message: 'Device unpaired successfully', type: 'success' });
      } else {
        window.appNotify({ message: 'Failed to unpair device: ' + (data.message || 'Unknown'), type: 'error' });
      }
    } catch (err) {
      console.error('Unpair error:', err);
      window.appNotify({ message: 'Network error while unpairing device', type: 'error' });
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
                  } catch (e) {
                    console.error('Sync error:', e);
                  }
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
        <button
          className={`tab-btn ${activeTab === 'devices' ? 'active' : ''}`}
          onClick={() => setActiveTab('devices')}
        >
          📱 Devices
        </button>
        <button
          className={`tab-btn ${activeTab === 'doctors' ? 'active' : ''}`}
          onClick={() => setActiveTab('doctors')}
        >
          👨‍⚕️ My Doctors
        </button>
        <button
          className={`tab-btn ${activeTab === 'dashboard' ? 'active' : ''}`}
          onClick={() => setActiveTab('dashboard')}
        >
          📊 Dashboard
        </button>
        <button
          className={`tab-btn ${activeTab === 'schedules' ? 'active' : ''}`}
          onClick={() => setActiveTab('schedules')}
        >
          ⏰ Schedules
        </button>
        <button
          className={`tab-btn ${activeTab === 'temperature' ? 'active' : ''}`}
          onClick={() => setActiveTab('temperature')}
        >
          🌡️ Temperature
        </button>
        <button
          className={`tab-btn ${activeTab === 'stats' ? 'active' : ''}`}
          onClick={() => setActiveTab('stats')}
        >
          📈 Stats
        </button>
        <button
          className={`tab-btn ${activeTab === 'articles' ? 'active' : ''}`}
          onClick={() => setActiveTab('articles')}
        >
          📰 Articles
        </button>
      </div>

      <div className="dashboard-content">
        {activeTab === 'devices' && (
          <div className="section">
            <div className="section-header">
              <h2>Paired Devices</h2>
              {devicesLoading ? null : devicesError ? (
                <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                  <span style={{ color: 'var(--danger)', marginRight: 8 }}>Failed to connect: {devicesError}</span>
                  <button className="btn-secondary" onClick={() => fetchDevices()}>Retry</button>
                </div>
              ) : (devices && devices.length === 0 ? (
                <button className="btn-primary" onClick={() => setShowQRScanner(!showQRScanner)}>
                  <Plus size={18} /> {showQRScanner ? 'Cancel Scan' : 'Scan Device QR'}
                </button>
              ) : (
                <div style={{ display: 'flex', gap: 8 }}>
                  <span style={{ alignSelf: 'center', color: 'var(--text-secondary)' }}>{devices.length} device{devices.length !== 1 ? 's' : ''} paired</span>
                </div>
              ))}
            </div>

            {showQRScanner && (
              <div className="qr-scanner-box">
                <div className="qr-scanner-header">
                  <h3>Scan Device QR Code</h3>
                  <button 
                    className="close-btn"
                    onClick={() => {
                      setShowQRScanner(false);
                      stopQRScanner();
                    }}
                  >
                    <X size={20} />
                  </button>
                </div>

                {/* Scanner auto-starts when the scan view opens; manual start removed */}

                <div style={{ marginBottom: 10, display: showQRScanner ? 'block' : 'none' }}>
                  {/* Camera selector and file-scan controls removed to use library default UI */}
                  <div ref={qrScannerRef} id="html5qr-scanner-wrapper" className="qr-scanner-container" style={{ display: showQRScanner ? 'block' : 'none' }}>
                    <div id="html5qr-scanner" style={{ width: '100%' }} />
                  </div>

                  {scannerStarted && (
                    <button 
                      className="btn-secondary"
                      onClick={() => {
                        stopQRScanner();
                        setShowQRScanner(false);
                      }}
                    >
                      Stop Scanning
                    </button>
                  )}
                </div>

                {scannerError && (
                  <div className="error-message">
                    <AlertCircle size={16} />
                    {scannerError}
                  </div>
                )}

                {/* scannedMac handled by Device Found panel (shown after scanner) */}
              </div>
            )}

            {showPairingCode && (
              <div className="pairing-code-box">
                <h3>Share this code with your device:</h3>
                <div className="code-display">{pairingToken}</div>
                <p className="hint">Your device can scan this code for pairing</p>
                <button className="btn-secondary" onClick={() => setShowPairingCode(false)}>Close</button>
              </div>
            )}

            {showDeviceFound && scannedMac && (
              <div className="device-found-box">
                <h3>Device Found</h3>
                <p>MAC Address: <strong>{scannedMac}</strong></p>
                <p>Detected device name: <strong>{`Smart Medi Box - ${scannedMac}`}</strong></p>
                <div style={{ display: 'flex', gap: 8, marginTop: 12, justifyContent: 'center' }}>
                  <button
                    className="btn-primary"
                    onClick={() => completePairingWithMac(scannedMac)}
                    disabled={loading}
                  >
                    {loading ? 'Pairing...' : 'Pair Device'}
                  </button>
                  <button
                    className="btn-secondary"
                    onClick={() => {
                      setShowDeviceFound(false);
                      setScannedMac('');
                    }}
                  >
                    Cancel
                  </button>
                </div>
              </div>
            )}

            {devicesLoading ? (
              <p className="empty-state"><span className="loading-inline"><div className="loader" aria-hidden="true"></div>Connecting to device service...</span></p>
            ) : devicesError ? (
              <div className="empty-state" style={{ color: 'var(--danger)' }}>
                <p>Failed to fetch devices: {devicesError}</p>
                <button className="btn-secondary" onClick={() => fetchDevices()}>Retry</button>
              </div>
            ) : devices.length === 0 ? (
              <p className="empty-state">No devices paired yet. Click "Scan Device QR" to add your first device.</p>
            ) : (
              <div className="devices-list">
                {devices.map(device => (
                  <div key={device.device_id} className="device-card">
                      <div className="device-info">
                        <h3>{device.device_name || 'Smart Medi Box'}</h3>
                        <p>MAC: {device.mac_address}</p>
                      </div>
                      <div className="device-meta-row">
                        <div>
                          <p className={`status ${device.status ? device.status.toLowerCase() : 'active'}`}>
                            {device.status || 'Active'}
                          </p>
                        </div>
                        <div className="device-actions">
                          <button
                            className="btn-unpair"
                            title="Unpair device"
                            onClick={() => {
                              setDeviceToUnpair(device.device_id);
                              setShowUnpairConfirm(true);
                            }}
                          >
                            <Trash2 size={16} /> Unpair
                          </button>
                        </div>
                      </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {activeTab === 'doctors' && (
          <div className="section">
            <h2>Your Doctors</h2>
            {doctors.length === 0 ? (
              <p className="empty-state">No doctors assigned yet. Wait for a doctor to assign you.</p>
            ) : (
              <div className="doctors-list">
                {doctors.map(doctor => (
                  <div key={doctor.id} className="doctor-card">
                    <h3>{doctor.name}</h3>
                    <p>Specialization: {doctor.specialty || doctor.specialization}</p>
                    <p>Hospital: {doctor.hospital}</p>
                    <p>Phone: {doctor.phone_number}</p>
                    <p className="assigned-date">Assigned: {new Date(doctor.assigned_at).toLocaleDateString()}</p>
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
                      <div className="temp-info">
                        Humidity: {temperature.external_humidity}%<br/>
                        Status: {temperature.cooling_status ? '🟢 Cooling ON' : '⚪ Cooling OFF'}
                      </div>
                    </div>
                  </div>
                ) : (
                  <p className="placeholder">⏳ Loading temperature...</p>
                )}
              </div>

              <div className="card">
                <div className="card-header">
                  <Clock size={24} />
                  <h3>Today's Schedules</h3>
                </div>
                <div className="card-content">
                  {schedules.length > 0 ? (
                    <div className="schedule-list">
                      {schedules.slice(0, 5).map((sched) => (
                        <div key={sched.schedule_id} className="schedule-item">
                          <div className="schedule-time">
                            {String(sched.hour).padStart(2, '0')}:{String(sched.minute).padStart(2, '0')}
                          </div>
                          <div className="schedule-details">
                            <div className="schedule-type">{sched.type}</div>
                            <div className="schedule-status">
                              {sched.is_completed ? <CheckCircle2 size={16} /> : <Clock size={16} />}
                              {sched.is_completed ? 'Completed' : 'Pending'}
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="placeholder">No schedules for today</p>
                  )}
                </div>
              </div>

              <div className="card">
                <div className="card-header">
                  <Users size={24} />
                  <h3>User Profile</h3>
                </div>
                <div className="card-content">
                  <div className="user-info">
                    <p><strong>Name:</strong> {profile.name}</p>
                    <p><strong>Email:</strong> {profile.email}</p>
                    {profile.blood_type && <p><strong>Blood Type:</strong> {profile.blood_type}</p>}
                    {profile.transplanted_organ && <p><strong>Organ:</strong> {profile.transplanted_organ}</p>}
                  </div>
                </div>
              </div>

              {stats && (
                <div className="card">
                  <div className="card-header">
                    <CheckCircle2 size={24} />
                    <h3>Quick Stats</h3>
                  </div>
                  <div className="card-content">
                    <div className="stats-quick">
                      <div className="stat-item">
                        <div className="stat-value">{stats.adherence_rate?.toFixed(1) || 0}%</div>
                        <div className="stat-label">Adherence Rate</div>
                      </div>
                      <div className="stat-item">
                        <div className="stat-value">{stats.completed_today || 0}</div>
                        <div className="stat-label">Completed Today</div>
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {activeTab === 'schedules' && (
          <div className="section schedules-minimal">
            <div className="section-header">
              <div className="header-left">
                <h2>Medication Schedule</h2>
                <p className="subtitle">Keep track of your daily health routines</p>
              </div>
              <div className="header-actions">
                <input
                  type="date"
                  value={scheduleFilterDate}
                  onChange={(e) => {
                    setScheduleFilterDate(e.target.value);
                    fetchSchedules(e.target.value);
                  }}
                  onClick={(e) => {
                    try {
                      if (e.target.showPicker) e.target.showPicker();
                    } catch (err) {
                      console.log('showPicker not supported');
                    }
                  }}
                  onKeyDown={(e) => e.preventDefault()}
                  className="filter-date-minimal"
                />
                <button 
                  className={`btn-add-toggle ${showAddForm ? 'active' : ''}`}
                  onClick={() => {
                    const newStatus = !showAddForm;
                    setShowAddForm(newStatus);
                    if (newStatus) {
                      // Sync new schedule date with currently filtered date when opening
                      setNewSchedule(prev => ({ ...prev, schedule_date: scheduleFilterDate }));
                    }
                  }}
                >
                  {showAddForm ? <X size={20} /> : <Plus size={20} />}
                  <span>{showAddForm ? 'Close' : 'Add New'}</span>
                </button>
              </div>
            </div>

            {showAddForm && (
              <div className="add-schedule-panel animate-slide-down">
                <form onSubmit={handleCreateSchedule} className="minimal-form">
                  <div className="minimal-form-grid">
                    <div className="form-group">
                      <label>Type</label>
                      <select 
                        value={newSchedule.type} 
                        onChange={(e) => setNewSchedule({...newSchedule, type: e.target.value})}
                      >
                        <option value="MEDICINE">💊 Medicine</option>
                        <option value="FOOD">🍽️ Food</option>
                        <option value="BLOOD_CHECK">🩸 Blood Check</option>
                      </select>
                    </div>
                    <div className="form-group">
                      <label>Date</label>
                      <input
                        type="date"
                        value={newSchedule.schedule_date}
                        onChange={(e) => setNewSchedule({...newSchedule, schedule_date: e.target.value})}
                        onClick={(e) => {
                          try {
                            if (e.target.showPicker) e.target.showPicker();
                          } catch (err) {}
                        }}
                      />
                    </div>
                    <div className="form-group">
                      <label>Time</label>
                      <div className="time-picker-minimal">
                        <input
                          type="number"
                          min="0"
                          max="23"
                          placeholder="HH"
                          value={String(newSchedule.hour).padStart(2, '0')}
                          onChange={(e) => setNewSchedule({...newSchedule, hour: parseInt(e.target.value) || 0})}
                          onWheel={(e) => e.target.blur()}
                        />
                        <span>:</span>
                        <input
                          type="number"
                          min="0"
                          max="59"
                          placeholder="MM"
                          value={String(newSchedule.minute).padStart(2, '0')}
                          onChange={(e) => setNewSchedule({...newSchedule, minute: parseInt(e.target.value) || 0})}
                          onWheel={(e) => e.target.blur()}
                        />
                      </div>
                    </div>
                    <div className="form-group full-width">
                      <label>Note (Optional)</label>
                      <input
                        type="text"
                        placeholder="e.g. Take with water"
                        value={newSchedule.description}
                        onChange={(e) => setNewSchedule({...newSchedule, description: e.target.value})}
                      />
                    </div>
                  </div>
                  <button type="submit" className="btn-save-schedule" disabled={isCreatingSchedule}>
                    {isCreatingSchedule ? (
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px' }}>
                        <div className="spinner-mini"></div> Saving...
                      </div>
                    ) : 'Save Reminder'}
                  </button>
                </form>
              </div>
            )}

            <div className="timeline-container">
              {schedulesLoading ? (
                <div className="loading-container" style={{ padding: '40px' }}>
                  <div className="spinner"></div>
                  <p style={{ marginTop: '16px' }}>Fetching your schedule...</p>
                </div>
              ) : schedules.length > 0 ? (
                <div className="minimal-timeline">
                  {schedules.map((sched, idx) => (
                    <div key={sched.schedule_id} className={`timeline-item ${sched.is_completed ? 'is-done' : ''}`}>
                      <div className="timeline-time">
                        <span className="time-text">{String(sched.hour).padStart(2, '0')}:{String(sched.minute).padStart(2, '0')}</span>
                        <div className="timeline-dot"></div>
                      </div>
                      <div className="timeline-card" style={{ opacity: isDeletingSchedule === sched.schedule_id ? 0.6 : 1 }}>
                        <div className="card-icon">
                          {sched.type === 'MEDICINE' ? '💊' : sched.type === 'FOOD' ? '🍽️' : '🩸'}
                        </div>
                        <div className="card-info">
                          <div className="card-top">
                            <h4>{sched.type}</h4>
                            <span className={`status-pill ${sched.is_completed ? 'done' : 'pending'}`}>
                              {sched.is_completed ? 'Completed' : 'Upcoming'}
                            </span>
                          </div>
                          {sched.description && <p className="card-desc">{sched.description}</p>}
                        </div>
                        <div className="card-actions">
                          {isDeletingSchedule === sched.schedule_id ? (
                            <div className="spinner-mini" style={{ margin: '0 10px' }}></div>
                          ) : (
                            <>
                              {!sched.is_completed && (
                                <button
                                  className="btn-action-done"
                                  onClick={() => handleCompleteSchedule(sched.schedule_id)}
                                  title="Mark as complete"
                                >
                                  <Check size={18} />
                                </button>
                              )}
                              <button
                                className="btn-action-delete"
                                onClick={() => {
                                  setScheduleToDelete(sched.schedule_id);
                                  setShowDeleteConfirm(true);
                                }}
                              >
                                <Trash2 size={16} />
                              </button>
                            </>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="empty-timeline">
                  <div className="empty-icon">📅</div>
                  <p>Your schedule is clear for today</p>
                  <button className="btn-primary" style={{ marginTop: '8px' }} onClick={() => {
                    setNewSchedule(prev => ({ ...prev, schedule_date: scheduleFilterDate }));
                    setShowAddForm(true);
                  }}>
                    <Plus size={18} /> Add your first reminder
                  </button>
                </div>
              )}
            </div>
          </div>
        )}
        {activeTab === 'temperature' && (
          <div className="section">
            <div className="card card-wide">
              <div className="card-header">
                <Thermometer size={24} />
                <h3>Temperature Graph (Last 7 Days)</h3>
              </div>
              {tempHistory.length > 0 ? (
                <div style={{ width: '100%', height: 300 }}>
                  <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={tempHistory}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="date" />
                      <YAxis domain={[0, 10]} />
                      <Tooltip />
                      <Legend />
                      <Line 
                        type="monotone" 
                        dataKey="avg_temp" 
                        stroke="#3b82f6" 
                        name="Avg Temp (°C)"
                        dot={false}
                      />
                      <Line 
                        type="monotone" 
                        dataKey="target_temp" 
                        stroke="#10b981" 
                        name="Target Temp (°C)"
                        strokeDasharray="5 5"
                        dot={false}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              ) : (
                <p className="placeholder">⏳ Loading temperature data...</p>
              )}
            </div>
          </div>
        )}

        {activeTab === 'stats' && (
          <div className="section">
            {stats && stats.trend ? (
              <div className="card card-wide">
                <div className="card-header">
                  <Activity size={24} />
                  <h3>7-Day Adherence Trend</h3>
                </div>
                <div style={{ width: '100%', height: 300 }}>
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={stats.trend}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="date" />
                      <YAxis />
                      <Tooltip />
                      <Legend />
                      <Bar dataKey="completed" fill="#10b981" name="Completed" />
                      <Bar dataKey="total" fill="#3b82f6" name="Total" />
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </div>
            ) : (
              <p className="placeholder">No statistics available yet</p>
            )}
          </div>
        )}

        {activeTab === 'articles' && (
          <div className="section">
            <h2 style={{ marginBottom: '20px' }}>📰 Latest Articles</h2>
            
            {articlesLoading ? (
              <div className="loading-container">
                <div className="spinner"></div>
                <p style={{ marginTop: '16px', color: 'var(--text-secondary)' }}>Loading articles...</p>
              </div>
            ) : articles.length === 0 ? (
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '120px' }}>
                <p style={{ color: 'var(--text-secondary)', margin: 0 }}>No articles available yet</p>
              </div>
            ) : (
              <div className="articles-grid">
                {articles.map(article => (
                  <div
                    key={article.id}
                    className="article-card"
                    onClick={() => {
                      setSelectedArticle(article);
                      handleViewArticle(article.article_id);
                    }}
                  >
                    <div className="article-cover">
                      {article.cover_image ? (
                        <img src={article.cover_image} alt={article.title} />
                      ) : (
                        <span style={{ fontSize: '48px' }}>📄</span>
                      )}
                    </div>
                    <div className="article-content">
                      <h3 className="article-title">{article.title}</h3>
                      <p className="article-excerpt">{article.excerpt || article.content.substring(0, 100)}</p>
                      <div className="article-meta">
                        <div className="article-author">
                          {article.doctor_name || 'Anonymous'}
                        </div>
                        <div className="article-views">
                          👁️ {article.views || 0}
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Article Detail Modal */}
      {selectedArticle && (
        <div className={`modal-overlay ${isArticleLoading ? 'no-backdrop-blur' : ''}`} onClick={() => setSelectedArticle(null)}>
          <div className={`modal-content ${isArticleLoading ? 'loading-blur' : ''}`} style={{ maxWidth: '600px', maxHeight: '80vh', overflowY: 'auto' }} onClick={e => e.stopPropagation()}>
            <button
              style={{ float: 'right', background: 'none', border: 'none', color: 'var(--text-primary)', cursor: 'pointer', fontSize: '24px', padding: '0' }}
              onClick={() => setSelectedArticle(null)}
            >
              ✕
            </button>
            <h2 style={{ marginBottom: '12px' }}>{selectedArticle.title}</h2>
            {selectedArticle.cover_image && (
              <img src={selectedArticle.cover_image} alt={selectedArticle.title} style={{ width: '100%', borderRadius: '8px', marginBottom: '16px', maxHeight: '300px', objectFit: 'cover' }} />
            )}
            <p style={{ marginBottom: '16px', color: 'var(--text-secondary)', fontSize: '14px' }}>
              By <strong>{selectedArticle.doctor_name || 'Anonymous'}</strong> • {new Date(selectedArticle.created_at).toLocaleDateString()} • 👁️ {selectedArticle.views || 0} views
            </p>
            <div style={{ color: 'var(--text-primary)', lineHeight: '1.8', fontSize: '15px', whiteSpace: 'pre-wrap' }}>
              {(selectedArticle.content && !isArticleLoading) ? selectedArticle.content : (selectedArticle.excerpt || '')}
            </div>
          </div>

          {isArticleLoading && (
            <div className="modal-loading-overlay" onClick={e => e.stopPropagation()}>
              <div className="loader modal-loader" aria-hidden="true" />
              <div>Loading full article...</div>
            </div>
          )}
        </div>
      )}

      {/* Logout Confirmation Modal */}
      {showLogoutConfirm && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to logout? You'll need to log in again to access your dashboard.</p>
            <div className="modal-buttons">
              <button className="btn-secondary" onClick={handleCancelLogout}>
                Cancel
              </button>
              <button className="btn-danger" onClick={handleConfirmLogout}>
                Logout
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Delete Confirmation Modal */}
      {showDeleteConfirm && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h2>Delete Reminder?</h2>
            <p>Are you sure you want to remove this medication reminder from your schedule?</p>
            <div className="modal-buttons">
              <button 
                className="btn-secondary" 
                onClick={() => {
                  setShowDeleteConfirm(false);
                  setScheduleToDelete(null);
                }}
              >
                Cancel
              </button>
              <button 
                className="btn-danger" 
                onClick={() => {
                  handleDeleteSchedule(scheduleToDelete);
                  setShowDeleteConfirm(false);
                  setScheduleToDelete(null);
                }}
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Unpair Confirmation Modal */}
      {showUnpairConfirm && (
        <div className="modal-overlay" onClick={() => setShowUnpairConfirm(false)}>
          <div className="modal-content" onClick={e => e.stopPropagation()} style={{ maxWidth: '420px' }}>
            <h3>Unpair device?</h3>
            <p>Are you sure you want to unpair this device? This will remove it from your account.</p>
            <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
              <button className="btn-unpair confirm-unpair" onClick={unpairDevice} disabled={loading}>
                {loading ? 'Unpairing...' : 'Yes, Unpair'}
              </button>
              <button className="btn-secondary" onClick={() => setShowUnpairConfirm(false)}>Cancel</button>
            </div>
          </div>
        </div>
      )}

      {/* Medicine Alert Popup (Urgent) */}
      {activeMedicineAlert && (
        <div className="modal-overlay urgent-alert">
          <div className="modal-content urgent-content pulse-border">
            <div className="urgent-header">
              <div className="urgent-icon">💊</div>
              <h2>Medicine Reminder!</h2>
            </div>
            <p className="urgent-message">{activeMedicineAlert.message}</p>
            <p className="urgent-hint">Please take your medicine now and check the Smart Medi Box.</p>
            <div className="modal-buttons">
              <button 
                className="btn-success btn-large btn-block" 
                onClick={() => {
                  setActiveMedicineAlert(null);
                  // Also mark as read in local state
                  setNotifications(prev => prev.map(n => n.id === activeMedicineAlert.id ? {...n, read: true} : n));
                }}
              >
                OK, I'm Taking It
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
  // Debug: show profile shape when doctor dashboard mounts
  useEffect(() => {
    try {
      console.log('DoctorDashboard profile:', profile);
      // Also log a serialized version so console expansion/collapse doesn't hide fields
      console.log('DoctorDashboard profile (JSON):', JSON.stringify(profile, null, 2));
    } catch (e) { }
  }, [profile]);

  // Friendly fallbacks for various backend field names
  // Prefer real name fields; DO NOT fall back to email to avoid showing email in header
  const displayName = (profile && (profile.name || profile.full_name || profile.display_name || profile.username)) || '';
  const displaySpecialization = (profile && (profile.specialty || profile.specialization || profile.speciality || profile.field)) || '';
  const displayHospital = (profile && (profile.hospital || profile.hospital_name || profile.clinic || profile.affiliation)) || '';
  const [activeTab, setActiveTab] = useState('patients');
  const [patients, setPatients] = useState([]);
  const [articles, setArticles] = useState([]);
  const [showNewArticle, setShowNewArticle] = useState(false);
  const [newArticle, setNewArticle] = useState({ title: '', content: '', cover_image: '', cover_image_data_url: null, cover_image_base64: null, cover_image_mime: null, cover_image_filename: null });
  const [assignPatient, setAssignPatient] = useState({ patient_nic: '', notes: '' });
  const [showAssignForm, setShowAssignForm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [articlesLoading, setArticlesLoading] = useState(false);
  const [creatingArticle, setCreatingArticle] = useState(false);
  const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
  const [showDeleteArticleConfirm, setShowDeleteArticleConfirm] = useState(false);
  const [articleIdToDelete, setArticleIdToDelete] = useState(null);
  const [deletingArticleId, setDeletingArticleId] = useState(null);
  // Local notification state for doctor dashboard
  const [notificationsDoc, setNotificationsDoc] = useState([]);
  const [notifPanelOpenDoc, setNotifPanelOpenDoc] = useState(false);
  const headerRefDoc = useRef(null);
  const notifPanelRefDoc = useRef(null);
  const bellBtnRefDoc = useRef(null);
  const [notifPanelStyleDoc, setNotifPanelStyleDoc] = useState({});

  const positionNotifPanelDoc = () => {
    try {
      if (!headerRefDoc.current) return setNotifPanelStyleDoc({ top: 64 });
      const hdr = headerRefDoc.current.getBoundingClientRect();
      const panelWidth = 340;
      const top = Math.round(hdr.bottom + window.scrollY + 8);
      let left = Math.round(hdr.right - panelWidth - 8);
      if (left < 8) left = 8;
      if (left + panelWidth > window.innerWidth) left = window.innerWidth - panelWidth - 8;
      setNotifPanelStyleDoc({ top: `${top}px`, left: `${left}px`, position: 'fixed' });
    } catch (e) { console.error('positionNotifPanelDoc error', e); }
  };

  useEffect(() => {
    if (!notifPanelOpenDoc) return;
    positionNotifPanelDoc();
    const onScroll = () => positionNotifPanelDoc();
    const onResize = () => positionNotifPanelDoc();
    const onDocClick = (e) => {
      const tgt = e.target;
      if (notifPanelRefDoc.current && bellBtnRefDoc.current) {
        if (!notifPanelRefDoc.current.contains(tgt) && !bellBtnRefDoc.current.contains(tgt)) {
          setNotifPanelOpenDoc(false);
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
  }, [notifPanelOpenDoc]);

  const clearNotificationsDoc = () => {
    setNotificationsDoc([]);
    setNotifPanelOpenDoc(false);
    window.appNotify({ message: 'Notifications cleared', type: 'info', toastOnly: true });
  };

  const handleLogoutClick = () => {
    console.log('🚪 Logout button clicked');
    setShowLogoutConfirm(true);
  };

  const handleConfirmLogout = () => {
    console.log('🔑 Confirming logout');
    setShowLogoutConfirm(false);
    onLogout();
  };

  const handleCancelLogout = () => {
    console.log('❌ Logout cancelled');
    setShowLogoutConfirm(false);
  };

  useEffect(() => {
    if (activeTab === 'patients') {
      fetchPatients();
    } else if (activeTab === 'articles') {
      fetchArticles();
    }
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
      if (data.status === 'SUCCESS') {
        setPatients(data.patients || []);
      }
    } catch (err) {
      console.error('Failed to fetch patients');
    } finally {
      setLoading(false);
    }
  };

  const fetchArticles = async () => {
    setArticlesLoading(true);
    try {
      const response = await fetch(`${API_URL}/index.php/api/articles/my`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setArticles(data.articles || []);
      } else {
        console.error('Failed to fetch articles:', data.message);
        // Leave existing articles in place; log the error for debugging
        window.appNotify && window.appNotify({ message: 'Failed to fetch articles: ' + (data.message || 'Unknown'), type: 'error' });
      }
    } catch (err) {
      console.error('Failed to fetch articles:', err);
      window.appNotify && window.appNotify({ message: 'Network error fetching articles: ' + err.message, type: 'error' });
    } finally {
      setArticlesLoading(false);
    }
  };

  const handleAssignPatient = async (e) => {
    e.preventDefault();
    try {
      const response = await fetch(`${API_URL}/index.php/api/doctor/assign-patient`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, ...assignPatient })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setAssignPatient({ patient_nic: '', notes: '' });
        setShowAssignForm(false);
        fetchPatients();
      }
    } catch (err) {
      console.error('Failed to assign patient');
    }
  };

  const handleCreateArticle = async (e) => {
    e.preventDefault();
    setCreatingArticle(true);
    try {
      // If a raw file was chosen, upload as multipart/form-data to preserve bytes
      let response;
      if (newArticle.cover_file) {
        const form = new FormData();
        form.append('token', token);
        form.append('title', newArticle.title);
        form.append('content', newArticle.content);
        form.append('cover_file', newArticle.cover_file, newArticle.cover_image_filename || newArticle.cover_file.name);

        response = await fetch(`${API_URL}/index.php/api/articles/create`, {
          method: 'POST',
          body: form
        });
      } else {
        const payload = {
          token,
          title: newArticle.title,
          content: newArticle.content,
          cover_image: newArticle.cover_image || null
        };

        if (newArticle.cover_image_data_url) {
          payload.cover_image_data_url = newArticle.cover_image_data_url;
        } else if (newArticle.cover_image_base64) {
          payload.cover_image_base64 = newArticle.cover_image_base64;
          payload.cover_image_mime = newArticle.cover_image_mime;
          payload.cover_image_filename = newArticle.cover_image_filename;
        }

        response = await fetch(`${API_URL}/index.php/api/articles/create`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
      }
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Article published successfully', type: 'success' });
        setNewArticle({ title: '', content: '', summary: '', category: '', cover_image: '', cover_image_data_url: null, cover_image_base64: null, cover_image_mime: null, cover_image_filename: null, cover_file: null });
        setShowNewArticle(false);
        setCreatingArticle(false);
        // Show loading spinner while refreshing articles
        setArticlesLoading(true);
        await fetchArticles();
      } else {
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to create article'), type: 'error' });
        setCreatingArticle(false);
      }
    } catch (err) {
      window.appNotify({ message: 'Error creating article: ' + err.message, type: 'error' });
      setCreatingArticle(false);
    }
  };

  const handleDeleteArticle = (articleId) => {
    setArticleIdToDelete(articleId);
    setShowDeleteArticleConfirm(true);
  };

  const handleConfirmDeleteArticle = async () => {
    setShowDeleteArticleConfirm(false);
    const articleId = articleIdToDelete;
    setDeletingArticleId(articleId);

    try {
      const response = await fetch(`${API_URL}/index.php/api/articles/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, article_id: articleId })
      });
      const data = await response.json();

      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Article deleted successfully', type: 'success' });
        // Keep spinner visible for 500ms, then remove from UI
        await new Promise(resolve => setTimeout(resolve, 500));
        // Remove from UI
        setArticles(articles.filter(article => article.id !== articleId && article.article_id !== articleId));
        // Also refresh to be sure
        await fetchArticles();
        // Clear deleting state so future delete operations work
        setDeletingArticleId(null);
        setArticleIdToDelete(null);
      } else {
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to delete article'), type: 'error' });
        // Clear deleting state on error
        setDeletingArticleId(null);
        setArticleIdToDelete(null);
      }
    } catch (err) {
      window.appNotify({ message: 'Error deleting article: ' + err.message, type: 'error' });
      // Clear deleting state on error
      setDeletingArticleId(null);
      setArticleIdToDelete(null);
    }
  };

  const handleCancelDeleteArticle = () => {
    setShowDeleteArticleConfirm(false);
    setArticleIdToDelete(null);
  };

  return (
    <div className="dashboard doctor-dashboard">
      <div className="dashboard-header" ref={headerRefDoc}>
        <div className="header-content">
          <h1>👨‍⚕️ Welcome, Dr. {displayName}</h1>
          <p>Specialization: {displaySpecialization} | Hospital: {displayHospital}</p>
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <button className="btn-icon" ref={bellBtnRefDoc} onClick={() => setNotifPanelOpenDoc(!notifPanelOpenDoc)} title="Notifications">
            <Bell size={18} />
            {notificationsDoc.filter(n => !n.read).length > 0 && <span className="notif-badge">{notificationsDoc.filter(n => !n.read).length}</span>}
          </button>
          <button className="btn-secondary" onClick={handleLogoutClick}>
            <LogOut size={18} /> Logout
          </button>
        </div>
      </div>

      {notifPanelOpenDoc && (
        <div className="notif-panel" ref={notifPanelRefDoc} style={notifPanelStyleDoc}>
          <div className="notif-panel-header">
            <strong>Notifications</strong>
            <button className="btn-link" onClick={clearNotificationsDoc}>Clear</button>
          </div>
          <div className="notif-list">
            {notificationsDoc.length === 0 && <div className="notif-empty">No notifications</div>}
            {notificationsDoc.map(n => (
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
        <button
          className={`tab-btn ${activeTab === 'patients' ? 'active' : ''}`}
          onClick={() => setActiveTab('patients')}
        >
          👥 My Patients ({patients.length})
        </button>
        <button
          className={`tab-btn ${activeTab === 'articles' ? 'active' : ''}`}
          onClick={() => setActiveTab('articles')}
        >
          📄 Articles
        </button>
      </div>

      <div className="dashboard-content">
        {activeTab === 'patients' && (
          <div className="section">
            <div className="section-header">
              <h2>Assigned Patients</h2>
              <button className="btn-primary" onClick={() => setShowAssignForm(!showAssignForm)}>
                <Plus size={18} /> Assign Patient
              </button>
            </div>

            {showAssignForm && (
              <form onSubmit={handleAssignPatient} className="form-card">
                <div className="form-group">
                  <label>Patient NIC</label>
                  <input
                    type="text"
                    value={assignPatient.patient_nic}
                    onChange={(e) => setAssignPatient({ ...assignPatient, patient_nic: e.target.value })}
                    placeholder="Enter patient NIC"
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Notes</label>
                  <textarea
                    value={assignPatient.notes}
                    onChange={(e) => setAssignPatient({ ...assignPatient, notes: e.target.value })}
                    placeholder="Add notes about this assignment"
                  />
                </div>
                <div className="form-buttons">
                  <button type="submit" className="btn-primary">Assign</button>
                  <button type="button" className="btn-secondary" onClick={() => setShowAssignForm(false)}>Cancel</button>
                </div>
              </form>
            )}

            {patients.length === 0 ? (
              <p className="empty-state">No patients assigned yet.</p>
            ) : (
              <div className="patients-list">
                {patients.map(patient => (
                  <div key={patient.id} className="patient-card">
                    <div className="patient-header">
                      <h3>{patient.name}</h3>
                      <span className="age-badge">{patient.age} years</span>
                    </div>
                    <p>NIC: {patient.nic}</p>
                    <p>Blood Type: {patient.blood_type}</p>
                    <p>Phone: {patient.phone_number}</p>
                    <p className="assigned-date">Assigned: {new Date(patient.assigned_at).toLocaleDateString()}</p>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {activeTab === 'articles' && (
          <div className="section">
            <div className="section-header">
              <h2>Medical Articles</h2>
              <button className="btn-primary" onClick={() => setShowNewArticle(!showNewArticle)}>
                <Plus size={18} /> New Article
              </button>
            </div>

            {showNewArticle && (
              <form onSubmit={handleCreateArticle} className="article-form">
                <div className="article-form-group">
                  <label>Title *</label>
                  <input
                    type="text"
                    value={newArticle.title}
                    onChange={(e) => setNewArticle({ ...newArticle, title: e.target.value })}
                    placeholder="Article title"
                    required
                  />
                </div>
                <div className="article-form-group">
                  <label>Or upload cover image (optional)</label>
                  <input
                    type="file"
                    accept="image/*"
                    onChange={(e) => {
                      const file = e.target.files && e.target.files[0];
                      if (!file) return;
                      // keep raw file for multipart upload
                      setNewArticle({ ...newArticle, cover_file: file, cover_image_filename: file.name, cover_image_mime: file.type });
                    }}
                  />
                  {newArticle.cover_image_filename && (
                    <div className="small-text">Selected: {newArticle.cover_image_filename} ({Math.round((newArticle.cover_file?.size||0)/1024)} KB)</div>
                  )}
                </div>
                <div className="article-form-group">
                  <label>Content *</label>
                  <textarea
                    value={newArticle.content}
                    onChange={(e) => setNewArticle({ ...newArticle, content: e.target.value })}
                    placeholder="Write your article content here..."
                    required
                  />
                </div>
                <div className="article-button-group">
                  <button type="submit" className="btn-primary" disabled={creatingArticle}>
                    {creatingArticle ? (
                      <>
                        <span className="spinner-mini" style={{ marginRight: '6px', display: 'inline-block' }}></span>
                        Publishing...
                      </>
                    ) : (
                      'Publish Article'
                    )}
                  </button>
                  <button type="button" className="btn-secondary" onClick={() => setShowNewArticle(false)} disabled={creatingArticle}>Cancel</button>
                </div>
              </form>
            )}

            {articlesLoading ? (
              <div className="loading-container">
                <div className="spinner"></div>
                <p style={{ marginTop: '16px', color: 'var(--text-secondary)' }}>Loading articles...</p>
              </div>
            ) : articles.length === 0 ? (
              <p className="empty-state">No articles published yet.</p>
            ) : (
              <div className="articles-grid">
                {articles.map(article => (
                  <div key={article.id} className="article-card">
                    <div className="article-cover">
                      {article.cover_image ? (
                        <img src={article.cover_image} alt={article.title} />
                      ) : (
                        <span style={{ fontSize: '48px' }}>📄</span>
                      )}
                    </div>
                    <div className="article-content">
                      <h3 className="article-title">{article.title}</h3>
                      <p className="article-excerpt">{article.content.substring(0, 100)}...</p>
                      <div className="article-meta">
                        <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>
                          {new Date(article.created_at).toLocaleDateString()}
                        </span>
                        <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>
                          👁️ {article.views}
                        </span>
                      </div>
                      <div style={{ display: 'flex', gap: '8px', marginTop: '12px' }}>
                        <button
                          className="btn-secondary"
                          style={{ padding: '8px 12px', fontSize: '13px', flex: 1, background: '#333', color: 'white', border: 'none' }}
                          onClick={() => {
                            window.appNotify({ message: 'Edit functionality coming soon', type: 'info' });
                          }}
                        >
                          ✏️ Edit
                        </button>
                        <button
                          className="btn-danger"
                          style={{ padding: '8px 12px', fontSize: '13px', flex: 1.4 }}
                          onClick={() => handleDeleteArticle(article.article_id)}
                          disabled={deletingArticleId === article.id || deletingArticleId === article.article_id}
                        >
                          {deletingArticleId === article.id || deletingArticleId === article.article_id ? (
                            <>
                              <span className="spinner-mini" style={{ marginRight: '4px', display: 'inline-block', verticalAlign: 'middle' }}></span>
                              Deleting...
                            </>
                          ) : (
                            '🗑️ Delete'
                          )}
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Delete Article Confirmation Modal */}
      {showDeleteArticleConfirm && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h2>Delete Article</h2>
            <p>Are you sure you want to delete this article? This action cannot be undone.</p>
            <div className="modal-buttons">
              <button className="btn-secondary" onClick={handleCancelDeleteArticle} disabled={deletingArticleId !== null}>
                Cancel
              </button>
              <button className="btn-danger" onClick={handleConfirmDeleteArticle} disabled={deletingArticleId !== null}>
                {deletingArticleId !== null ? (
                  <>
                    <span className="spinner-mini" style={{ marginRight: '4px', display: 'inline-block', verticalAlign: 'middle' }}></span>
                    Deleting...
                  </>
                ) : (
                  'Delete'
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Logout Confirmation Modal */}
      {showLogoutConfirm && (
        <div className="modal-overlay">
          <div className="modal-content">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to logout? You'll need to log in again to access your dashboard.</p>
            <div className="modal-buttons">
              <button className="btn-secondary" onClick={handleCancelLogout}>
                Cancel
              </button>
              <button className="btn-danger" onClick={handleConfirmLogout}>
                Logout
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// ==================== Main App ====================
export default function App() {
  const [currentUser, setCurrentUser] = useState(null);
  const [currentPage, setCurrentPage] = useState('login');
  const [toasts, setToasts] = useState([]);

  useEffect(() => {
    // Check if user is already logged in
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');
    const profile = localStorage.getItem('profile');

    if (token && role && profile) {
      setCurrentUser({
        token,
        role,
        profile: JSON.parse(profile)
      });
      setCurrentPage(role === 'PATIENT' ? 'patient-dashboard' : 'doctor-dashboard');
    } else {
      // Handle hash routing for auth pages
      const hash = window.location.hash.slice(1) || 'login';
      if (hash === 'signup' || hash === 'login') {
        setCurrentPage(hash);
      }
    }
  }, []);

  // Global notification -> toast bridge (available for all pages)
  useEffect(() => {
    const handler = (e) => {
      const payload = (e && e.detail) ? e.detail : {};
      const t = { id: Date.now() + Math.floor(Math.random()*1000), message: payload.message || '', type: payload.type || 'info' };
      setToasts(prev => [t, ...prev]);
      // auto-remove after 4s
      setTimeout(() => {
        setToasts(prev => prev.filter(x => x.id !== t.id));
      }, 4000);
    };

    window.addEventListener('app-notification', handler);
    // ensure window.appNotify exists
    window.appNotify = (payload) => {
      try { window.dispatchEvent(new CustomEvent('app-notification', { detail: payload })); } catch (e) { console.error(e); }
    };

    return () => window.removeEventListener('app-notification', handler);
  }, []);

  // Handle hash changes for navigation
  useEffect(() => {
    const handleHashChange = () => {
      if (!currentUser) {
        const hash = window.location.hash.slice(1) || 'login';
        if (hash === 'signup' || hash === 'login') {
          setCurrentPage(hash);
        }
      }
    };

    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, [currentUser]);

  const handleLoginSuccess = (data) => {
    // Ensure localStorage is synchronized and log profile for debugging
    try {
      if (data.token) localStorage.setItem('token', data.token);
      if (data.user_id) localStorage.setItem('user_id', data.user_id);
      if (data.role) localStorage.setItem('role', data.role);
      if (data.profile) localStorage.setItem('profile', JSON.stringify(data.profile));
    } catch (e) { console.error('LocalStorage sync error', e); }
    console.log('LOGIN SUCCESS profile:', data.profile);
    setCurrentUser(data);
    // clear any auth hashes from URL and go to dashboard
    try { window.location.hash = ''; } catch (e) {}
    setCurrentPage(data.role === 'PATIENT' ? 'patient-dashboard' : 'doctor-dashboard');
  };

  const handleSignupSuccess = (data) => {
    // Ensure localStorage is synchronized and log profile for debugging
    try {
      if (data.token) localStorage.setItem('token', data.token);
      if (data.user_id) localStorage.setItem('user_id', data.user_id);
      if (data.role) localStorage.setItem('role', data.role);
      if (data.profile) localStorage.setItem('profile', JSON.stringify(data.profile));
    } catch (e) { console.error('LocalStorage sync error', e); }
    console.log('SIGNUP SUCCESS profile:', data.profile);
    setCurrentUser(data);
    // clear any auth hashes from URL and go to dashboard
    try { window.location.hash = ''; } catch (e) {}
    setCurrentPage(data.role === 'PATIENT' ? 'patient-dashboard' : 'doctor-dashboard');
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user_id');
    localStorage.removeItem('role');
    localStorage.removeItem('profile');
    setCurrentUser(null);
    // keep URL hash in sync so clicking auth links works immediately
    try { window.location.hash = '#login'; } catch (e) {}
    setCurrentPage('login');
  };

  return (
    <div className="app">
      {!currentUser ? (
        <>
          {currentPage === 'login' && <LoginScreen onLoginSuccess={handleLoginSuccess} />}
          {currentPage === 'signup' && <SignupScreen onSignupSuccess={handleSignupSuccess} />}
        </>
      ) : currentUser.role === 'PATIENT' ? (
        <PatientDashboard
          profile={currentUser.profile}
          token={currentUser.token}
          onLogout={handleLogout}
        />
      ) : (
        <DoctorDashboard
          profile={currentUser.profile}
          token={currentUser.token}
          onLogout={handleLogout}
        />
      )}
      {/* Toast container (bottom-left transient notifications) */}
      <div className="toast-container" aria-live="polite">
        {toasts.map(t => (
          <div key={t.id} className={`toast ${t.type || ''}`}>
            <div className="toast-message">{t.message}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
