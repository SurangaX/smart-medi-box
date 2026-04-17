import React, { useState, useEffect, useRef } from 'react';
import './notifications.css';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { AlertCircle, Thermometer, Clock, Users, LogOut, CheckCircle2, FileText, Plus, Edit, Trash2, Phone, MapPin, Calendar, Lock, Eye, EyeOff, X, Camera, Activity, Bell } from 'lucide-react';
import { Html5QrcodeScanner } from 'html5-qrcode';
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

            {showDeviceFound && scannedMac && (
              <div className="device-found-box">
                <h3>Device Found</h3>
                <p>MAC Address: <strong>{scannedMac}</strong></p>
                <p>Detected device name: <strong>{`Smart Medi Box - ${scannedMac}`}</strong></p>
                <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
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
                />
              </div>
            </div>

            {role === 'PATIENT' && (
              <>
                <div className="form-row">
                  <div className="form-group">
                    <label>Blood Type</label>
                    <select name="blood_type" value={formData.blood_type} onChange={handleInputChange}>
                      {bloodTypes.map(bt => <option key={bt} value={bt}>{bt}</option>)}
                    </select>
                  </div>
                  <div className="form-group">
                    <label>Transplanted Organ</label>
                    <select name="transplanted_organ" value={formData.transplanted_organ} onChange={handleInputChange}>
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
  const [showDeviceFound, setShowDeviceFound] = useState(false);
  const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
  const [newSchedule, setNewSchedule] = useState({ 
    type: 'MEDICINE', 
    schedule_date: new Date().toISOString().split('T')[0],
    hour: 9, 
    minute: 0, 
    description: '' 
  });
  const [scheduleFilterDate, setScheduleFilterDate] = useState(new Date().toISOString().split('T')[0]);
  const [articles, setArticles] = useState([]);
  const [selectedArticle, setSelectedArticle] = useState(null);
  const [articlesLoading, setArticlesLoading] = useState(false);
  const qrScannerRef = useRef(null);
  const qrInstanceRef = useRef(null);

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

  useEffect(() => {
    if (activeTab === 'doctors') {
      fetchDoctors();
    } else if (activeTab === 'devices') {
      fetchDevices();
    } else if (activeTab === 'dashboard') {
      fetchSchedules();
      fetchTemperature();
      fetchStats();
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

  const fetchSchedules = async (dateFilter = null) => {
    try {
      const filterDate = dateFilter || scheduleFilterDate || new Date().toISOString().split('T')[0];
      console.log('📅 Fetching schedules for date:', filterDate);
      console.log('🔑 Token:', token ? token.substring(0, 20) + '...' : 'NO TOKEN');
      
      const response = await fetch(`${API_URL}/index.php/api/schedule/today`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          token,
          start_date: filterDate,
          end_date: filterDate
        })
      });
      
      console.log('📡 Schedule API Response Status:', response.status);
      const data = await response.json();
      console.log('📊 Schedule API Response Data:', data);
      
      if (data.status === 'SUCCESS') {
        console.log('✅ Schedules fetched successfully:', data.schedules?.length || 0, 'items');
        setSchedules(data.schedules || []);
      } else {
        console.error('❌ Schedule fetch failed:', data.message || 'Unknown error');
        setSchedules([]);
      }
    } catch (err) {
      console.error('🚨 Schedule fetch exception:', err);
      setSchedules([]);
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
      const data = await response.json();
      
      if (data.status === 'SUCCESS') {
        setArticles(data.articles || []);
      } else {
        console.error('Failed to fetch articles:', data.message);
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
      await fetch(`${API_URL}/index.php/api/articles/view`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ article_id: articleId })
      });
    } catch (err) {
      console.error('Failed to track view:', err);
    }
  };

  const handleCreateSchedule = async (e) => {
    e.preventDefault();
    try {
      console.log('💊 Creating new schedule:', newSchedule);
      const response = await fetch(`${API_URL}/index.php/api/schedule/create`, {
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
      console.log('📡 Create Schedule API Response Status:', response.status);
      const data = await response.json();
      console.log('📊 Create Schedule API Response:', data);
      
      if (data.status === 'SUCCESS') {
        console.log('✅ Schedule created successfully!');
        window.appNotify({ message: 'Schedule created successfully', type: 'success' });
        const today = new Date().toISOString().split('T')[0];
        setNewSchedule({ type: 'MEDICINE', schedule_date: today, hour: 9, minute: 0, description: '' });
        // Refresh schedules from the newly created schedule's date
        fetchSchedules(newSchedule.schedule_date);
      } else {
        console.error('❌ Schedule creation failed:', data.message);
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to create schedule'), type: 'error' });
      }
    } catch (err) {
      console.error('🚨 Schedule creation exception:', err);
      window.appNotify({ message: 'Failed to create schedule: ' + err.message, type: 'error' });
    }
  };

  const handleCompleteSchedule = async (scheduleId) => {
    try {
      console.log('✓ Marking schedule as complete:', scheduleId);
      const response = await fetch(`${API_URL}/index.php/api/schedule/complete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, schedule_id: scheduleId })
      });
      console.log('📡 Complete Schedule API Response Status:', response.status);
      const data = await response.json();
      console.log('📊 Complete Schedule API Response:', data);
      
      if (data.status === 'SUCCESS') {
        console.log('✅ Schedule marked as complete!');
        window.appNotify({ message: 'Schedule marked as complete', type: 'success' });
        fetchSchedules();
      } else {
        console.error('❌ Failed to mark schedule complete:', data.message);
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to mark schedule complete'), type: 'error' });
      }
    } catch (err) {
      console.error('🚨 Complete schedule exception:', err);
      window.appNotify({ message: 'Failed to mark schedule complete: ' + err.message, type: 'error' });
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

      const qrScanner = new Html5QrcodeScanner(
        'qr-reader',
        {
          fps: 10,
          qrbox: { width: 250, height: 250 },
          rememberLastUsedCamera: true,
          aspectRatio: 1.0,
        },
        false
      );

      // Render returns a promise
      await qrScanner.render(
        (decodedText) => {
          console.log('QR Scanned:', decodedText);
          const mac = decodedText.trim();
          setScannedMac(mac);
          setScannerError('');
          try {
            qrScanner.pause();
            qrScanner.stop().catch(() => {});
          } catch (e) {}
          qrInstanceRef.current = null;
          setScannerStarted(false);
          setShowQRScanner(false);
          setShowDeviceFound(true);
        },
        (errorMessage) => {
          // Suppress logging for performance
        }
      );

      qrInstanceRef.current = qrScanner;
      setScannerStarted(true);
      console.log('QR Scanner started successfully');
    } catch (error) {
      console.error('Failed to start QR scanner:', error);
      setScannerError('Camera access denied or not available. Please check permissions: ' + error.message);
      setScannerStarted(false);
    }
  };

  const stopQRScanner = () => {
    try {
      if (qrInstanceRef.current) {
        // Stop the scanner properly
        qrInstanceRef.current.pause();
        qrInstanceRef.current.stop().catch(err => console.log('Scanner already stopped:', err));
        qrInstanceRef.current = null;
        
        // Clear the scanner element
        if (qrScannerRef.current) {
          qrScannerRef.current.innerHTML = '';
        }
      }
      setScannerStarted(false);
    } catch (error) {
      console.error('Error stopping scanner:', error);
      setScannerStarted(false);
    }
  };

  useEffect(() => {
    return () => {
      // Cleanup: stop scanner when component unmounts
      stopQRScanner();
    };
  }, []);

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
        fetchDevices(); // Refresh device list
        window.appNotify({ message: 'Device paired successfully', type: 'success' });
      } else {
        setScannerError(`Failed to pair device: ${data.message || 'Unknown error'}`);
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
            <strong>Notifications</strong>
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

                {!scannerStarted ? (
                  <div className="scanner-start-section">
                    <p>Click the button below to start scanning your device's QR code</p>
                    <button 
                      className="btn-primary"
                      onClick={async () => {
                        setScannerError('');
                        await startQRScanner();
                      }}
                    >
                      <Camera size={18} /> Start Camera
                    </button>
                  </div>
                ) : null}

                <div ref={qrScannerRef} className="qr-scanner-container" style={{ display: scannerStarted ? 'block' : 'none' }}></div>

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
                    <p>Specialization: {doctor.specialization}</p>
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
          <div className="section">
            <div className="schedules-container">
              <div className="card">
                <div className="card-header">
                  <Plus size={24} />
                  <h3>Schedule New Reminder</h3>
                </div>
                <form onSubmit={handleCreateSchedule} className="schedule-form">
                  <div className="form-grid">
                    <div className="form-group">
                      <label>Type of Reminder</label>
                      <div className="type-selector">
                        {['MEDICINE', 'FOOD', 'BLOOD_CHECK'].map(type => (
                          <button
                            key={type}
                            type="button"
                            className={`type-btn ${newSchedule.type === type ? 'active' : ''}`}
                            onClick={() => setNewSchedule({...newSchedule, type})}
                          >
                            {type === 'MEDICINE' ? '💊' : type === 'FOOD' ? '🍽️' : '🩸'}
                            <span>{type}</span>
                          </button>
                        ))}
                      </div>
                    </div>

                    <div className="form-group">
                      <label>Date</label>
                      <input 
                        type="date"
                        value={newSchedule.schedule_date}
                        onChange={(e) => setNewSchedule({...newSchedule, schedule_date: e.target.value})}
                        required
                        className="date-input"
                      />
                    </div>

                    <div className="form-group">
                      <label>Time</label>
                      <div className="time-inputs">
                        <div className="time-field">
                          <label>Hour</label>
                          <input 
                            type="number" 
                            min="0" 
                            max="23"
                            value={String(newSchedule.hour).padStart(2, '0')}
                            onChange={(e) => setNewSchedule({...newSchedule, hour: parseInt(e.target.value) || 0})}
                            className="time-input"
                          />
                        </div>
                        <span className="time-separator">:</span>
                        <div className="time-field">
                          <label>Min</label>
                          <input 
                            type="number" 
                            min="0" 
                            max="59"
                            value={String(newSchedule.minute).padStart(2, '0')}
                            onChange={(e) => setNewSchedule({...newSchedule, minute: parseInt(e.target.value) || 0})}
                            className="time-input"
                          />
                        </div>
                      </div>
                    </div>

                    <div className="form-group form-full">
                      <label>Notes (Optional)</label>
                      <input 
                        type="text"
                        placeholder="Add any notes (e.g., take with food)"
                        value={newSchedule.description}
                        onChange={(e) => setNewSchedule({...newSchedule, description: e.target.value})}
                        className="note-input"
                      />
                    </div>
                  </div>

                  <button type="submit" className="btn-primary btn-large">
                    <Plus size={18} /> Add Reminder
                  </button>
                </form>
              </div>

              <div className="card">
                <div className="card-header">
                  <Clock size={24} />
                  <h3>Your Reminders</h3>
                  <div className="header-actions">
                    <input 
                      type="date"
                      value={scheduleFilterDate}
                      onChange={(e) => {
                        setScheduleFilterDate(e.target.value);
                        fetchSchedules(e.target.value);
                      }}
                      className="filter-date"
                    />
                  </div>
                </div>
                <div className="schedules-list">
                  {schedules.length > 0 ? (
                    schedules.map((sched) => (
                      <div key={sched.schedule_id} className={`schedule-card ${sched.is_completed ? 'completed' : ''}`}>
                        <div className="schedule-badge">
                          {sched.type === 'MEDICINE' ? '💊' : sched.type === 'FOOD' ? '🍽️' : '🩸'}
                        </div>
                        <div className="schedule-content">
                          <div className="schedule-main">
                            <h4>{sched.type}</h4>
                            <p className="schedule-datetime">
                              {new Date(sched.schedule_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })} 
                              {' '} 
                              <span className="time-display">{String(sched.hour).padStart(2, '0')}:{String(sched.minute).padStart(2, '0')}</span>
                            </p>
                            {sched.description && <p className="schedule-description">{sched.description}</p>}
                          </div>
                          <div className="schedule-status-badge">
                            {sched.is_completed ? '✅ Done' : '⏳ Pending'}
                          </div>
                        </div>
                        {!sched.is_completed && (
                          <button 
                            className="btn-check"
                            onClick={() => handleCompleteSchedule(sched.schedule_id)}
                            title="Mark as complete"
                          >
                            ✓
                          </button>
                        )}
                      </div>
                    ))
                  ) : (
                    <div className="empty-state">
                      <Clock size={48} />
                      <p>No reminders for this date</p>
                      <small>Create a new reminder to get started</small>
                    </div>
                  )}
                </div>
              </div>
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
              <p style={{ color: 'var(--text-secondary)' }}>Loading articles...</p>
            ) : articles.length === 0 ? (
              <p style={{ color: 'var(--text-secondary)' }}>No articles available yet</p>
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
        <div className="modal-overlay" onClick={() => setSelectedArticle(null)}>
          <div className="modal-content" style={{ maxWidth: '600px', maxHeight: '80vh', overflowY: 'auto' }} onClick={e => e.stopPropagation()}>
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
              {selectedArticle.content}
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
    </div>
  );
};

// ==================== Doctor Dashboard ====================
const DoctorDashboard = ({ profile, token, onLogout }) => {
  const [activeTab, setActiveTab] = useState('patients');
  const [patients, setPatients] = useState([]);
  const [articles, setArticles] = useState([]);
  const [showNewArticle, setShowNewArticle] = useState(false);
  const [newArticle, setNewArticle] = useState({ title: '', content: '', cover_image: '' });
  const [assignPatient, setAssignPatient] = useState({ patient_nic: '', notes: '' });
  const [showAssignForm, setShowAssignForm] = useState(false);
  const [loading, setLoading] = useState(false);
  const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
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
    setLoading(true);
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
        setArticles([]);
      }
    } catch (err) {
      console.error('Failed to fetch articles:', err);
      setArticles([]);
    } finally {
      setLoading(false);
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
    try {
      const response = await fetch(`${API_URL}/index.php/api/articles/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token,
          title: newArticle.title,
          content: newArticle.content,
          cover_image: newArticle.cover_image || null
        })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Article published successfully', type: 'success' });
        setNewArticle({ title: '', content: '', summary: '', category: '', cover_image: '' });
        setShowNewArticle(false);
        fetchArticles();
      } else {
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to create article'), type: 'error' });
      }
    } catch (err) {
      window.appNotify({ message: 'Error creating article: ' + err.message, type: 'error' });
    }
  };

  const handleDeleteArticle = async (articleId) => {
    if (!confirm('Are you sure you want to delete this article?')) return;
    
    try {
      const response = await fetch(`${API_URL}/index.php/api/articles/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, article_id: articleId })
      });
      const data = await response.json();
      
      if (data.status === 'SUCCESS') {
        window.appNotify({ message: 'Article deleted successfully', type: 'success' });
        // Immediately remove from UI
        setArticles(articles.filter(article => article.id !== articleId && article.article_id !== articleId));
        // Also refresh to be sure
        await fetchArticles();
      } else {
        window.appNotify({ message: 'Error: ' + (data.message || 'Failed to delete article'), type: 'error' });
      }
    } catch (err) {
      window.appNotify({ message: 'Error deleting article: ' + err.message, type: 'error' });
    }
  };

  return (
    <div className="dashboard doctor-dashboard">
      <div className="dashboard-header" ref={headerRefDoc}>
        <div className="header-content">
          <h1>👨‍⚕️ Welcome, Dr. {profile.name}</h1>
          <p>Specialization: {profile.specialization} | Hospital: {profile.hospital}</p>
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
                  <label>Cover Image URL (optional)</label>
                  <input
                    type="url"
                    value={newArticle.cover_image}
                    onChange={(e) => setNewArticle({ ...newArticle, cover_image: e.target.value })}
                    placeholder="https://example.com/image.jpg"
                  />
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
                  <button type="submit" className="btn-primary">Publish Article</button>
                  <button type="button" className="btn-secondary" onClick={() => setShowNewArticle(false)}>Cancel</button>
                </div>
              </form>
            )}

            {articles.length === 0 ? (
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
                          style={{ padding: '6px 12px', fontSize: '12px', flex: 1 }}
                          onClick={() => {
                            window.appNotify({ message: 'Edit functionality coming soon', type: 'info' });
                          }}
                        >
                          Edit
                        </button>
                        <button
                          className="btn-danger"
                          style={{ padding: '6px 12px', fontSize: '12px', flex: 1 }}
                          onClick={() => handleDeleteArticle(article.article_id)}
                        >
                          Delete
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
    setCurrentUser(data);
    setCurrentPage(data.role === 'PATIENT' ? 'patient-dashboard' : 'doctor-dashboard');
  };

  const handleSignupSuccess = (data) => {
    setCurrentUser(data);
    setCurrentPage(data.role === 'PATIENT' ? 'patient-dashboard' : 'doctor-dashboard');
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user_id');
    localStorage.removeItem('role');
    localStorage.removeItem('profile');
    setCurrentUser(null);
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
