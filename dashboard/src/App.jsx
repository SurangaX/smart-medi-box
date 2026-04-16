import React, { useState, useEffect, useRef } from 'react';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { AlertCircle, Thermometer, Clock, Users, LogOut, CheckCircle2, FileText, Plus, Edit, Trash2, Phone, MapPin, Calendar, Lock, Eye, EyeOff, X, Camera } from 'lucide-react';
import { Html5QrcodeScanner } from 'html5-qrcode';
import './App.css';

const API_URL = 'https://smart-medi-box.onrender.com';

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
  const [activeTab, setActiveTab] = useState('devices');
  const [devices, setDevices] = useState([]);
  const [doctors, setDoctors] = useState([]);
  const [pairingToken, setPairingToken] = useState('');
  const [showPairingCode, setShowPairingCode] = useState(false);
  const [scannedMac, setScannedMac] = useState('');
  const [showQRScanner, setShowQRScanner] = useState(false);
  const [manualMacInput, setManualMacInput] = useState('');
  const [temperature, setTemperature] = useState(null);
  const [loading, setLoading] = useState(false);
  const [scannerError, setScannerError] = useState('');
  const [scannerStarted, setScannerStarted] = useState(false);
  const qrScannerRef = useRef(null);
  const qrInstanceRef = useRef(null);

  useEffect(() => {
    if (activeTab === 'doctors') {
      fetchDoctors();
    } else if (activeTab === 'devices') {
      fetchDevices();
    }
  }, [activeTab]);

  const fetchDevices = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${API_URL}/index.php/api/patient/devices`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setDevices(data.devices || []);
      } else {
        console.error('Fetch devices error:', data.message || 'Unknown error');
        setDevices([]);
      }
    } catch (err) {
      console.error('Failed to fetch devices:', err);
      setDevices([]);
    } finally {
      setLoading(false);
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

  const generatePairingToken = async () => {
    try {
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
          setScannedMac(decodedText.trim());
          setScannerError('');
          qrScanner.pause();
          setScannerStarted(false);
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
        alert('✅ Device paired successfully!');
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

  return (
    <div className="dashboard patient-dashboard">
      <div className="dashboard-header">
        <div className="header-content">
          <h1>👤 Welcome, {profile.name}</h1>
          <p>NIC: {profile.nic} | ID: {profile.id}</p>
        </div>
        <button className="btn-secondary" onClick={onLogout}>
          <LogOut size={18} /> Logout
        </button>
      </div>

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
          className={`tab-btn ${activeTab === 'health' ? 'active' : ''}`}
          onClick={() => setActiveTab('health')}
        >
          ❤️ Health Data
        </button>
      </div>

      <div className="dashboard-content">
        {activeTab === 'devices' && (
          <div className="section">
            <div className="section-header">
              <h2>Paired Devices</h2>
              <button className="btn-primary" onClick={() => setShowQRScanner(!showQRScanner)}>
                <Plus size={18} /> {showQRScanner ? 'Cancel Scan' : 'Scan Device QR'}
              </button>
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

                {scannedMac && (
                  <div className="scanned-result">
                    <p>MAC Address detected: <strong>{scannedMac}</strong></p>
                    <button 
                      className="btn-primary"
                      onClick={() => completePairingWithMac(scannedMac)}
                      disabled={loading}
                    >
                      {loading ? 'Pairing...' : 'Pair Device'}
                    </button>
                  </div>
                )}
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

            {devices.length === 0 ? (
              <p className="empty-state">No devices paired yet. Click "Scan Device QR" to add your first device.</p>
            ) : (
              <div className="devices-list">
                {devices.map(device => (
                  <div key={device.device_id} className="device-card">
                    <div className="device-info">
                      <h3>{device.device_name || 'Smart Medi Box'}</h3>
                      <p>MAC: {device.mac_address}</p>
                      <p className={`status ${device.status ? device.status.toLowerCase() : 'active'}`}>
                        Status: {device.status || 'Active'}
                      </p>
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

        {activeTab === 'health' && (
          <div className="section">
            <h2>Health Data</h2>
            <div className="health-info">
              <div className="info-card">
                <span className="label">Blood Type:</span>
                <span className="value">{profile.blood_type}</span>
              </div>
              <div className="info-card">
                <span className="label">Transplanted Organ:</span>
                <span className="value">{profile.transplanted_organ || 'None'}</span>
              </div>
              {profile.transplantation_date && (
                <div className="info-card">
                  <span className="label">Transplantation Date:</span>
                  <span className="value">{new Date(profile.transplantation_date).toLocaleDateString()}</span>
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

// ==================== Doctor Dashboard ====================
const DoctorDashboard = ({ profile, token, onLogout }) => {
  const [activeTab, setActiveTab] = useState('patients');
  const [patients, setPatients] = useState([]);
  const [articles, setArticles] = useState([]);
  const [showNewArticle, setShowNewArticle] = useState(false);
  const [newArticle, setNewArticle] = useState({ title: '', content: '', summary: '', category: '' });
  const [assignPatient, setAssignPatient] = useState({ patient_nic: '', notes: '' });
  const [showAssignForm, setShowAssignForm] = useState(false);
  const [loading, setLoading] = useState(false);

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
      const response = await fetch(`${API_URL}/index.php/api/articles?limit=10`);
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setArticles(data.articles || []);
      }
    } catch (err) {
      console.error('Failed to fetch articles');
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
      const response = await fetch(`${API_URL}/index.php/api/doctor/article/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, ...newArticle })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setNewArticle({ title: '', content: '', summary: '', category: '' });
        setShowNewArticle(false);
        fetchArticles();
      }
    } catch (err) {
      console.error('Failed to create article');
    }
  };

  return (
    <div className="dashboard doctor-dashboard">
      <div className="dashboard-header">
        <div className="header-content">
          <h1>👨‍⚕️ Welcome, Dr. {profile.name}</h1>
          <p>Specialization: {profile.specialization} | Hospital: {profile.hospital}</p>
        </div>
        <button className="btn-secondary" onClick={onLogout}>
          <LogOut size={18} /> Logout
        </button>
      </div>

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
              <form onSubmit={handleCreateArticle} className="form-card">
                <div className="form-group">
                  <label>Title *</label>
                  <input
                    type="text"
                    value={newArticle.title}
                    onChange={(e) => setNewArticle({ ...newArticle, title: e.target.value })}
                    placeholder="Article title"
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Summary</label>
                  <input
                    type="text"
                    value={newArticle.summary}
                    onChange={(e) => setNewArticle({ ...newArticle, summary: e.target.value })}
                    placeholder="Brief summary"
                  />
                </div>
                <div className="form-group">
                  <label>Category</label>
                  <input
                    type="text"
                    value={newArticle.category}
                    onChange={(e) => setNewArticle({ ...newArticle, category: e.target.value })}
                    placeholder="e.g., Cardiology, Nephrology"
                  />
                </div>
                <div className="form-group">
                  <label>Content *</label>
                  <textarea
                    value={newArticle.content}
                    onChange={(e) => setNewArticle({ ...newArticle, content: e.target.value })}
                    placeholder="Article content"
                    rows="8"
                    required
                  />
                </div>
                <div className="form-buttons">
                  <button type="submit" className="btn-primary">Publish</button>
                  <button type="button" className="btn-secondary" onClick={() => setShowNewArticle(false)}>Cancel</button>
                </div>
              </form>
            )}

            {articles.length === 0 ? (
              <p className="empty-state">No articles published yet.</p>
            ) : (
              <div className="articles-list">
                {articles.map(article => (
                  <div key={article.id} className="article-card">
                    <h3>{article.title}</h3>
                    <p className="article-meta">By Dr. {article.doctor_name} • {article.specialization}</p>
                    {article.summary && <p className="article-summary">{article.summary}</p>}
                    <div className="article-footer">
                      <span className="category">{article.category}</span>
                      <span className="views">👁 {article.view_count} views</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
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
    </div>
  );
}
