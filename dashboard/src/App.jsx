import React, { useState, useEffect, useRef } from 'react';
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { AlertCircle, Thermometer, Clock, Users, CheckCircle, Activity, Lock, Wifi, QrCode, LogOut, CheckCircle2 } from 'lucide-react';
import { Html5QrcodeScanner } from 'html5-qrcode';
import './App.css';

const API_URL = 'https://smart-medi-box.onrender.com';

const PairingScreen = ({ onPaired }) => {
  const [scannerActive, setScannerActive] = useState(true);
  const [deviceInfo, setDeviceInfo] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!scannerActive) return;

    const scanner = new Html5QrcodeScanner('qr-scanner', {
      fps: 10,
      qrbox: 250,
      showTorchButtonIfSupported: true,
      showZoomSliderIfSupported: true
    }, false);

    const onScanSuccess = (decodedText) => {
      try {
        const parts = decodedText.split('|');
        if (parts.length >= 2) {
          const deviceId = parts[0];
          const macAddress = parts[1];
          const deviceName = parts[2] || 'Unknown Device';

          setDeviceInfo({ deviceId, macAddress, deviceName });
          setScannerActive(false);
          scanner.clear();
          setError('');
        } else {
          setError('Invalid QR code format');
        }
      } catch (err) {
        setError('Error parsing QR code: ' + err.message);
      }
    };

    scanner.render(onScanSuccess, () => {});

    return () => {
      try {
        scanner.clear();
      } catch (e) {}
    };
  }, [scannerActive]);

  const handlePairDevice = () => {
    if (deviceInfo) {
      localStorage.setItem('pairedDevice', JSON.stringify({
        deviceId: deviceInfo.deviceId,
        macAddress: deviceInfo.macAddress,
        deviceName: deviceInfo.deviceName,
        pairedAt: new Date().toISOString()
      }));
      onPaired(deviceInfo.macAddress);
    }
  };

  const handleRetry = () => {
    setDeviceInfo(null);
    setError('');
    setScannerActive(true);
  };

  return (
    <div className="pairing-screen">
      <div className="pairing-container">
        <div className="pairing-content">
          <div className="pairing-header">
            <div className="pairing-icon">🔗</div>
            <h1>Pair Your Arduino Device</h1>
            <p>Scan the QR code on your Arduino device to establish connection</p>
          </div>

          {!deviceInfo ? (
            <>
              {scannerActive && (
                <div className="qr-scanner-wrapper">
                  <div id="qr-scanner" style={{ width: '100%' }}></div>
                </div>
              )}
              {error && (
                <div className="error-message">
                  <AlertCircle size={20} />
                  {error}
                </div>
              )}
            </>
          ) : (
            <div className="device-confirmation">
              <div className="confirmation-icon">
                <CheckCircle2 size={48} color="#10b981" />
              </div>
              <div className="device-details">
                <h2>Device Found!</h2>
                <div className="detail-row">
                  <span className="label">Device ID:</span>
                  <span className="value">{deviceInfo.deviceId}</span>
                </div>
                <div className="detail-row">
                  <span className="label">MAC Address:</span>
                  <span className="value font-mono">{deviceInfo.macAddress}</span>
                </div>
                <div className="detail-row">
                  <span className="label">Device Name:</span>
                  <span className="value">{deviceInfo.deviceName}</span>
                </div>
              </div>

              <div className="confirmation-buttons">
                <button className="btn-primary" onClick={handlePairDevice}>
                  ✓ Pair This Device
                </button>
                <button className="btn-secondary" onClick={handleRetry}>
                  ⟲ Scan Again
                </button>
              </div>
            </div>
          )}

          <div className="pairing-info">
            <h3>How to pair:</h3>
            <ol>
              <li>Locate the QR code sticker on your Arduino device</li>
              <li>Click Start Scanning and point your camera at the QR code</li>
              <li>The device will be automatically detected</li>
              <li>Review the device details and click Pair This Device</li>
              <li>Once paired, you won't need to scan again</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  );
};

export default function App() {
  const [pairedDevice, setPairedDevice] = useState(null);
  const [activeTab, setActiveTab] = useState('dashboard');
  const [userId, setUserId] = useState('USER_20260413_A1B2C3');
  const [userData, setUserData] = useState(null);
  const [schedules, setSchedules] = useState([]);
  const [temperature, setTemperature] = useState(null);
  const [tempHistory, setTempHistory] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [newSchedule, setNewSchedule] = useState({ type: 'MEDICINE', hour: 8, minute: 0, description: '' });
  const [deviceConnected, setDeviceConnected] = useState(true);

  useEffect(() => {
    const stored = localStorage.getItem('pairedDevice');
    if (stored) {
      setPairedDevice(JSON.parse(stored));
    }
  }, []);

  const fetchProfile = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_URL}/index.php/api/user/profile?user_id=${userId}`);
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setUserData(data.user);
        setError('');
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError('Failed to fetch profile');
    } finally {
      setLoading(false);
    }
  };

  const fetchSchedules = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/schedule/today?user_id=${userId}`);
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setSchedules(data.schedules || []);
      }
    } catch (err) {
      setError('Failed to fetch schedules');
    }
  };

  const fetchTemperature = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/temperature/current?user_id=${userId}`);
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setTemperature(data);
        setDeviceConnected(true);
      }
    } catch (err) {
      setDeviceConnected(false);
    }
  };

  const fetchTempHistory = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/temperature/history?user_id=${userId}&days=7`);
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setTempHistory(data.history || []);
      }
    } catch (err) {
      console.log('History data not available');
    }
  };

  const fetchStats = async () => {
    try {
      const response = await fetch(`${API_URL}/index.php/api/user/stats?user_id=${userId}`);
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setStats(data);
      }
    } catch (err) {
      console.log('Stats not available');
    }
  };

  const handleCreateSchedule = async (e) => {
    e.preventDefault();
    try {
      const response = await fetch(`${API_URL}/index.php/api/schedule/create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: userId,
          ...newSchedule
        })
      });
      const data = await response.json();
      if (data.status === 'SUCCESS') {
        setNewSchedule({ type: 'MEDICINE', hour: 8, minute: 0, description: '' });
        fetchSchedules();
        setError('');
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError('Failed to create schedule');
    }
  };

  useEffect(() => {
    if (pairedDevice) {
      fetchProfile();
      fetchSchedules();
      fetchTemperature();
      fetchTempHistory();
      fetchStats();

      const interval = setInterval(() => {
        fetchSchedules();
        fetchTemperature();
      }, 30000);

      return () => clearInterval(interval);
    }
  }, [pairedDevice, userId]);

  const handleUnpair = () => {
    localStorage.removeItem('pairedDevice');
    setPairedDevice(null);
  };

  if (!pairedDevice) {
    return <PairingScreen onPaired={(mac) => setPairedDevice(JSON.parse(localStorage.getItem('pairedDevice')))} />;
  }

  return (
    <div className="app">
      <header className="header">
        <div className="header-content">
          <h1>🏥 Smart Medi Box</h1>
          <div className="header-info">
            <span className={`status-badge ${deviceConnected ? 'connected' : 'disconnected'}`}>
              <Wifi size={16} /> {deviceConnected ? 'Connected' : 'Disconnected'}
            </span>
            <span className="device-badge">
              📱 {pairedDevice.deviceName}
            </span>
            {userData && <span className="user-name">{userData.name}</span>}
            <button className="btn-unpair" onClick={handleUnpair} title="Unpair device">
              <LogOut size={16} />
            </button>
          </div>
        </div>
      </header>

      <nav className="nav">
        <button 
          className={`nav-btn ${activeTab === 'dashboard' ? 'active' : ''}`}
          onClick={() => setActiveTab('dashboard')}
        >
          <Activity size={18} /> Dashboard
        </button>
        <button 
          className={`nav-btn ${activeTab === 'schedules' ? 'active' : ''}`}
          onClick={() => setActiveTab('schedules')}
        >
          <Clock size={18} /> Schedules
        </button>
        <button 
          className={`nav-btn ${activeTab === 'temperature' ? 'active' : ''}`}
          onClick={() => setActiveTab('temperature')}
        >
          <Thermometer size={18} /> Temperature
        </button>
        <button 
          className={`nav-btn ${activeTab === 'stats' ? 'active' : ''}`}
          onClick={() => setActiveTab('stats')}
        >
          <CheckCircle size={18} /> Statistics
        </button>
      </nav>

      <main className="content">
        {error && (
          <div className="error-banner">
            <AlertCircle size={20} />
            {error}
          </div>
        )}

        {!deviceConnected && (
          <div className="warning-banner">
            <AlertCircle size={20} />
            Arduino device is not responding. Check connection and power.
          </div>
        )}

        {activeTab === 'dashboard' && (
          <div className="dashboard-grid">
            <div className="card">
              <div className="card-header">
                <Thermometer size={24} />
                <h3>Temperature</h3>
              </div>
              {temperature && deviceConnected ? (
                <div className="card-content">
                  <div className="temp-display">
                    <div className="temp-main">{temperature.internal_temp}°C</div>
                    <div className="temp-sub">Target: {temperature.target_temp}°C</div>
                    <div className="temp-info">
                      Humidity: {temperature.humidity}%<br/>
                      Status: {temperature.cooling_active ? '🔵 Cooling ON' : '⚪ Cooling OFF'}
                    </div>
                  </div>
                </div>
              ) : (
                <p className="placeholder">
                  {!deviceConnected ? '❌ Arduino not connected' : '⏳ Loading...'}
                </p>
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
                    {schedules.map((sched) => (
                      <div key={sched.schedule_id} className="schedule-item">
                        <div className="schedule-time">
                          {String(sched.hour).padStart(2, '0')}:{String(sched.minute).padStart(2, '0')}
                        </div>
                        <div className="schedule-details">
                          <div className="schedule-type">{sched.type}</div>
                          <div className="schedule-status">
                            {sched.is_completed ? <CheckCircle size={16} className="done" /> : <Clock size={16} />}
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
              {userData && (
                <div className="card-content">
                  <div className="user-info">
                    <p><strong>Name:</strong> {userData.name}</p>
                    <p><strong>Age:</strong> {userData.age} years</p>
                    <p><strong>Phone:</strong> {userData.phone}</p>
                    <p><strong>Active Schedules:</strong> {userData.total_schedules}</p>
                  </div>
                </div>
              )}
            </div>

            {stats && (
              <div className="card">
                <div className="card-header">
                  <CheckCircle size={24} />
                  <h3>Quick Stats</h3>
                </div>
                <div className="card-content">
                  <div className="stats-quick">
                    <div className="stat-item">
                      <div className="stat-value">{stats.adherence_rate?.toFixed(1)}%</div>
                      <div className="stat-label">Adherence Rate</div>
                    </div>
                    <div className="stat-item">
                      <div className="stat-value">{stats.completed_this_week || 0}</div>
                      <div className="stat-label">Completed This Week</div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {activeTab === 'schedules' && (
          <div className="schedules-container">
            <div className="card">
              <div className="card-header">
                <Clock size={24} />
                <h3>Create New Schedule</h3>
              </div>
              <form onSubmit={handleCreateSchedule} className="form">
                <div className="form-group">
                  <label>Type</label>
                  <select 
                    value={newSchedule.type}
                    onChange={(e) => setNewSchedule({...newSchedule, type: e.target.value})}
                  >
                    <option>MEDICINE</option>
                    <option>FOOD</option>
                    <option>BLOOD_CHECK</option>
                  </select>
                </div>
                <div className="form-row">
                  <div className="form-group">
                    <label>Hour (0-23)</label>
                    <input 
                      type="number" 
                      min="0" 
                      max="23"
                      value={newSchedule.hour}
                      onChange={(e) => setNewSchedule({...newSchedule, hour: parseInt(e.target.value)})}
                    />
                  </div>
                  <div className="form-group">
                    <label>Minute (0-59)</label>
                    <input 
                      type="number" 
                      min="0" 
                      max="59"
                      value={newSchedule.minute}
                      onChange={(e) => setNewSchedule({...newSchedule, minute: parseInt(e.target.value)})}
                    />
                  </div>
                </div>
                <div className="form-group">
                  <label>Description (optional)</label>
                  <input 
                    type="text"
                    placeholder="e.g., Morning medication"
                    value={newSchedule.description}
                    onChange={(e) => setNewSchedule({...newSchedule, description: e.target.value})}
                  />
                </div>
                <button type="submit" className="btn-primary">Create Schedule</button>
              </form>
            </div>

            <div className="card">
              <div className="card-header">
                <Clock size={24} />
                <h3>Today's Schedules</h3>
              </div>
              <div className="schedule-table">
                {schedules.length > 0 ? (
                  <table>
                    <thead>
                      <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {schedules.map((sched) => (
                        <tr key={sched.schedule_id} className={sched.is_completed ? 'completed' : ''}>
                          <td>{String(sched.hour).padStart(2, '0')}:{String(sched.minute).padStart(2, '0')}</td>
                          <td><span className="badge">{sched.type}</span></td>
                          <td>{sched.description || '-'}</td>
                          <td>{sched.is_completed ? '✅ Done' : '⏳ Pending'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                ) : (
                  <p className="placeholder">No schedules created yet</p>
                )}
              </div>
            </div>
          </div>
        )}

        {activeTab === 'temperature' && (
          <div className="temperature-container">
            <div className="card card-wide">
              <div className="card-header">
                <Thermometer size={24} />
                <h3>Temperature Graph (Last 7 Days)</h3>
              </div>
              {tempHistory.length > 0 && deviceConnected ? (
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
                <p className="placeholder">
                  {!deviceConnected ? '❌ Arduino not connected' : '⏳ No data available'}
                </p>
              )}
            </div>
          </div>
        )}

        {activeTab === 'stats' && (
          <div className="stats-container">
            {stats && stats.trend ? (
              <div className="card card-wide">
                <div className="card-header">
                  <BarChart size={24} />
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
      </main>
    </div>
  );
}
