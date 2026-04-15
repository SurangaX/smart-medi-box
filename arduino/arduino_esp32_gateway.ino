/*
  ============================================================================
  SMART MEDI BOX - ESP32 (GSM/SERVER GATEWAY)
  ============================================================================
  
  This sketch runs ONLY on ESP32
  Handles: GSM/GPRS communication, Server API integration, Serial gateway
  Communicates with Arduino Leonardo via Serial
  
  Memory Usage: ~50KB (fits in ESP32's 4MB)
  
  ESP32 Pins for Serial (Leonardo):
  RX0 (GPIO3) - connects to Leonardo TX (D1)
  TX0 (GPIO1) - connects to Leonardo RX (D0)
  
  GSM Module (SIM800L):
  RX (GPIO16) - GSM TX
  TX (GPIO17) - GSM RX
  
  ============================================================================
*/

// ==================== LIBRARIES ====================
#include <HardwareSerial.h>
#include <WiFi.h>
#include <ArduinoJson.h>

// GSM Libraries
#define TINY_GSM_MODEM_SIM800
#include <TinyGSM.h>
#include <TinyGSMClient.h>

// ==================== PIN DEFINITIONS ====================
// GSM Module on ESP32 Serial2
#define GSM_RX_PIN 16
#define GSM_TX_PIN 17

// Leonardo on ESP32 Serial0 (default USB serial, but can use Serial1)
#define LEONARDO_RX_PIN 9
#define LEONARDO_TX_PIN 10

// ==================== CONFIGURATION ====================
// Server Configuration
const char* SERVER_HOST = "smart-medi-box.onrender.com";
const uint16_t SERVER_PORT = 80; // HTTP (not HTTPS for GSM)
const char* API_BASE_URL = "/api";

// GSM Configuration (SIM800L)
const char* GSM_APN = "hutch3g";
const char* GSM_USER = "";
const char* GSM_PASS = "";

// Device Configuration
const char* DEVICE_ID = "LEONARDO-001";
const char* DEVICE_TYPE = "ARDUINO_LEONARDO";

// Timing
const unsigned long SYNC_INTERVAL = 60000;      // 1 minute
const unsigned long HEARTBEAT_INTERVAL = 30000;  // 30 seconds
const unsigned long GSM_CHECK_INTERVAL = 15000;  // 15 seconds

// Debug Mode
const boolean DEBUG_MODE = true;

// ==================== OBJECTS ====================
// Hardware Serial for Leonardo (RX0, TX0 are used for Leonardo via USB converter)
HardwareSerial LeonardoSerial(0);  // UART0 connected to Leonardo

// GSM
TinyGsm modem(Serial2);
TinyGsmClient gsmClient(modem);

// ==================== DATA STRUCTURES ====================
struct SensorData {
  float temperature;
  float humidity;
  boolean doorOpen;
  boolean alarmActive;
  unsigned long timestamp;
};

struct DeviceStatus {
  boolean gsmConnected;
  boolean gprsConnected;
  boolean serverConnected;
  int signalStrength;
  unsigned long lastGSMCheck;
  unsigned long lastServerSync;
  unsigned long lastHeartbeat;
};

// ==================== GLOBALS ====================
SensorData sensorData;
DeviceStatus status;
String lastCommand = "";

// ==================== SETUP ====================
void setup() {
  // Initialize Serial for debugging (USB)
  Serial.begin(115200);
  delay(100);
  
  // Initialize Serial for Leonardo communication
  LeonardoSerial.begin(9600, SERIAL_8N1, LEONARDO_RX_PIN, LEONARDO_TX_PIN);
  
  // Initialize Serial2 for GSM
  Serial2.begin(9600, SERIAL_8N1, GSM_RX_PIN, GSM_TX_PIN);
  
  delay(2000);
  
  debugPrint("ESP32 Starting...");
  
  // Initialize GSM
  initGSM();
  
  // Register device
  registerDevice();
  
  debugPrint("ESP32 Ready");
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long now = millis();
  
  // Listen for data from Leonardo
  if (LeonardoSerial.available()) {
    processLeonardoData();
  }
  
  // GSM health check
  if (now - status.lastGSMCheck >= GSM_CHECK_INTERVAL) {
    checkGSMConnection();
    status.lastGSMCheck = now;
  }
  
  // Server sync (send sensor data)
  if (status.gprsConnected && now - status.lastServerSync >= SYNC_INTERVAL) {
    syncWithServer();
    status.lastServerSync = now;
  }
  
  // Heartbeat
  if (status.serverConnected && now - status.lastHeartbeat >= HEARTBEAT_INTERVAL) {
    sendHeartbeat();
    status.lastHeartbeat = now;
  }
  
  delay(100);
}

// ==================== GSM FUNCTIONS ====================
void initGSM() {
  debugPrint("Initializing GSM Module...");
  
  // Restart modem
  if (!modem.restart()) {
    debugPrint("Failed to restart modem");
    return;
  }
  
  // Check SIM
  String simStatus = modem.getSimStatus();
  debugPrint("SIM Status: " + simStatus);
  
  // Wait for network
  debugPrint("Waiting for GSM network...");
  if (!modem.waitForNetwork(30000L)) {
    debugPrint("Failed to connect to network");
    status.gsmConnected = false;
    return;
  }
  
  status.gsmConnected = true;
  debugPrint("GSM Connected");
  
  // Connect GPRS
  debugPrint("Connecting GPRS...");
  if (!modem.gprsConnect(GSM_APN, GSM_USER, GSM_PASS)) {
    debugPrint("Failed to connect GPRS");
    status.gprsConnected = false;
    return;
  }
  
  status.gprsConnected = true;
  debugPrint("GPRS Connected");
}

void checkGSMConnection() {
  if (modem.isNetworkConnected()) {
    if (!status.gsmConnected) {
      status.gsmConnected = true;
      debugPrint("GSM Reconnected");
      registerDevice();
    }
    
    status.signalStrength = modem.getSignalQuality();
    
    if (modem.isGprsConnected()) {
      if (!status.gprsConnected) {
        status.gprsConnected = true;
        debugPrint("GPRS Reconnected");
      }
    } else {
      if (status.gprsConnected) {
        status.gprsConnected = false;
        status.serverConnected = false;
        debugPrint("GPRS Disconnected");
      }
    }
  } else {
    if (status.gsmConnected) {
      status.gsmConnected = false;
      status.gprsConnected = false;
      status.serverConnected = false;
      debugPrint("GSM Disconnected");
    }
  }
}

// ==================== SERVER FUNCTIONS ====================
void registerDevice() {
  if (!status.gprsConnected) return;
  
  debugPrint("Registering device...");
  
  String payload = "{";
  payload += "\"device_type\":\"" + String(DEVICE_TYPE) + "\",";
  payload += "\"imei\":\"" + modem.getIMEI() + "\"";
  payload += "}";
  
  String response = postRequest("/device/register", payload);
  
  if (response.indexOf("SUCCESS") != -1) {
    status.serverConnected = true;
    debugPrint("Device registered successfully");
  } else {
    status.serverConnected = false;
    debugPrint("Device registration failed");
  }
}

void syncWithServer() {
  if (!status.serverConnected) return;
  
  debugPrint("Syncing with server...");
  
  // Send sensor data
  String payload = "{";
  payload += "\"device_id\":\"" + String(DEVICE_ID) + "\",";
  payload += "\"temperature\":" + String(sensorData.temperature, 2) + ",";
  payload += "\"humidity\":" + String(sensorData.humidity, 2) + ",";
  payload += "\"door_open\":" + String(sensorData.doorOpen ? "true" : "false") + ",";
  payload += "\"alarm_active\":" + String(sensorData.alarmActive ? "true" : "false");
  payload += "}";
  
  postRequest("/device/status", payload);
}

void sendHeartbeat() {
  if (!status.serverConnected) return;
  
  String payload = "{";
  payload += "\"device_id\":\"" + String(DEVICE_ID) + "\",";
  payload += "\"gsm_signal\":" + String(status.signalStrength) + ",";
  payload += "\"temperature\":" + String(sensorData.temperature, 2);
  payload += "}";
  
  postRequest("/device/heartbeat", payload);
}

String postRequest(String endpoint, String payload) {
  if (!status.gprsConnected) return "";
  
  debugPrint("POST " + endpoint);
  
  if (!gsmClient.connect(SERVER_HOST, SERVER_PORT)) {
    debugPrint("Failed to connect to server");
    return "";
  }
  
  // Send HTTP POST
  gsmClient.print("POST " + String(API_BASE_URL) + endpoint + " HTTP/1.0\r\n");
  gsmClient.print("Host: " + String(SERVER_HOST) + "\r\n");
  gsmClient.print("Content-Type: application/json\r\n");
  gsmClient.print("Content-Length: " + String(payload.length()) + "\r\n");
  gsmClient.print("Connection: close\r\n\r\n");
  gsmClient.print(payload);
  
  // Read response
  String response = "";
  unsigned long timeout = millis() + 10000;
  
  while (gsmClient.connected() && millis() < timeout) {
    while (gsmClient.available()) {
      char c = gsmClient.read();
      response += c;
    }
  }
  
  gsmClient.stop();
  return response;
}

// ==================== LEONARDO COMMUNICATION ====================
void processLeonardoData() {
  String data = LeonardoSerial.readStringUntil('\n');
  data.trim();
  
  if (data.length() == 0) return;
  
  debugPrint("Leonardo: " + data);
  
  // Parse JSON from Leonardo
  DynamicJsonDocument doc(512);
  DeserializationError error = deserializeJson(doc, data);
  
  if (error) {
    debugPrint("JSON parse error");
    return;
  }
  
  // Extract sensor data
  if (doc["action"] == "data") {
    sensorData.temperature = doc["temp"];
    sensorData.humidity = doc["humidity"];
    sensorData.doorOpen = doc["door_open"];
    sensorData.alarmActive = doc["alarm_active"];
    sensorData.timestamp = millis();
  }
}

void sendCommandToLeonardo(String command) {
  debugPrint("Sending to Leonardo: " + command);
  LeonardoSerial.println(command);
}

// ==================== UTILITY FUNCTIONS ====================
void debugPrint(String msg) {
  if (DEBUG_MODE) {
    Serial.println("[ESP32] " + msg);
  }
}

// ==================== END OF ESP32 SKETCH ====================
