import React, { useState, useEffect } from 'react';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { AlertCircle, Thermometer, Clock, Users, LogOut, CheckCircle2, FileText, Plus, Edit, Trash2, Phone, MapPin, Calendar, Lock, Eye, EyeOff } from 'lucide-react';
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
      const endpoint = role === 'PATIENT' ? 'patient/signup' : 'doctor/signup';
      const response = await fetch(`${API_URL}/index.php/api/auth/${endpoint}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });

      const data = await response.json();

      if (data.status === 'SUCCESS') {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user_id', data.user_id);
        localStorage.setItem('role', data.role);
        localStorage.setItem('profile', JSON.stringify(data.profile));
        onSignupSuccess(data);
      } else {
        setError(data.message || 'Signup failed');
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
                <label>NIC/ID Number *</label>
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
                <label>Phone Number</label>
                <input
                  type="tel"
                  name="phone_number"
                  value={formData.phone_number}
                  onChange={handleInputChange}
                  placeholder="+1 (555) 000-0000"
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
  const [temperature, setTemperature] = useState(null);
  const [loading, setLoading] = useState(false);

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
      }
    } catch (err) {
      console.error('Failed to fetch devices');
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
              <button className="btn-primary" onClick={generatePairingToken}>
                <Plus size={18} /> Add Device
              </button>
            </div>

            {showPairingCode && (
              <div className="pairing-code-box">
                <p>Share this code with your device to pair:</p>
                <div className="code-display">{pairingToken}</div>
                <button className="btn-secondary" onClick={() => setShowPairingCode(false)}>Close</button>
              </div>
            )}

            {devices.length === 0 ? (
              <p className="empty-state">No devices paired yet. Generate a pairing code to add your first device.</p>
            ) : (
              <div className="devices-list">
                {devices.map(device => (
                  <div key={device.device_id} className="device-card">
                    <div className="device-info">
                      <h3>{device.device_name}</h3>
                      <p>MAC: {device.mac_address}</p>
                      <p className={`status ${device.status.toLowerCase()}`}>Status: {device.status}</p>
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
    }
  }, []);

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
          {currentPage === 'login' && (
            <div className="auth-switch">
              <button onClick={() => setCurrentPage('signup')} className="link-btn">
                Don't have an account? Sign up
              </button>
            </div>
          )}
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
