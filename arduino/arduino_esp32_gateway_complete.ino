/*
 * ============================================================================
 * SMART MEDI BOX - ESP32 Gateway Sketch
 * ============================================================================
 * 
 * Features:
 * - QR Code Generation and Display
 * - WiFi Communication with Backend
 * - Schedule Management
 * - User Authentication
 * - Device Pairing
 * - SMS/App Notifications Handling
 * - Temperature Control Interface
 * 
 * Hardware:
 * - ESP32-DevKitC
 * - 3.5" TFT LCD Display (with SD card for images)
 * - Buzzer (GPIO 18)
 * 
 * ============================================================================
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <time.h>
#include <EEPROM.h>

// ============================================================================
// Configuration
// ============================================================================

#define WIFI_SSID "YOUR_SSID"
#define WIFI_PASSWORD "YOUR_PASSWORD"
#define API_URL "https://smart-medi-box.onrender.com"
#define BUZZER_PIN 18

// EEPROM addresses
#define EEPROM_USER_ID_ADDR 0
#define EEPROM_DEVICE_MAC_ADDR 10
#define EEPROM_SESSION_TOKEN_ADDR 50
#define EEPROM_QR_TOKEN_ADDR 150

// Timing
#define SCHEDULE_CHECK_INTERVAL 60000  // Check schedules every 1 minute
#define NOTIFICATION_CHECK_INTERVAL 30000  // Check notifications every 30 seconds
#define COMMAND_CHECK_INTERVAL 5000   // Check commands every 5 seconds
#define NTP_UPDATE_INTERVAL 3600000   // Update time from NTP every 1 hour

// Global variables
unsigned long lastScheduleCheck = 0;
unsigned long lastNotificationCheck = 0;
unsigned long lastCommandCheck = 0;
unsigned long lastNTPUpdate = 0;

int currentUserID = 0;
String deviceMAC = "";
String sessionToken = "";
String qrToken = "";
bool isAuthenticated = false;
bool alarmActive = false;

struct Schedule {
  int id;
  String type;
  int hour;
  int minute;
  String description;
  bool isCompleted;
};

Schedule activeSchedules[10];
int scheduleCount = 0;

// ============================================================================
// Setup & Initialization
// ============================================================================

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n\nSmart Medi Box - ESP32 Gateway Starting...");
  
  // Initialize pins
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);
  
  // Initialize EEPROM
  EEPROM.begin(512);
  
  // Get device MAC address
  deviceMAC = getDeviceMAC();
  Serial.println("Device MAC: " + deviceMAC);
  
  // Load saved credentials from EEPROM
  loadCredentials();
  
  // Initialize WiFi
  initWiFi();
  
  // Set timezone and sync time
  syncTime();
  
  // Initialize TFT Display
  initDisplay();
  
  // Show startup message
  displayMessage("Smart Medi Box", "Initializing...");
  
  delay(2000);
  
  // Check if already authenticated
  if (currentUserID > 0 && !sessionToken.isEmpty()) {
    isAuthenticated = true;
    Serial.println("User ID: " + String(currentUserID));
    Serial.println("Resuming authenticated session...");
    displayMessage("Smart Medi Box", "Resuming session...");
    delay(2000);
  } else {
    // Need to authenticate via QR
    Serial.println("No saved credentials. Waiting for QR scan...");
    displayQRPrompt();
  }
}

void loop() {
  // Check WiFi connection
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected. Reconnecting...");
    reconnectWiFi();
  }
  
  // Update time from NTP periodically
  if (millis() - lastNTPUpdate > NTP_UPDATE_INTERVAL) {
    syncTime();
    lastNTPUpdate = millis();
  }
  
  if (isAuthenticated) {
    // Check for scheduled alarms
    if (millis() - lastScheduleCheck > SCHEDULE_CHECK_INTERVAL) {
      checkScheduledAlarms();
      fetchSchedules();
      lastScheduleCheck = millis();
    }
    
    // Check for pending notifications
    if (millis() - lastNotificationCheck > NOTIFICATION_CHECK_INTERVAL) {
      checkNotifications();
      lastNotificationCheck = millis();
    }
    
    // Check for commands from server
    if (millis() - lastCommandCheck > COMMAND_CHECK_INTERVAL) {
      checkCommands();
      lastCommandCheck = millis();
    }
  } else {
    // Waiting for authentication
    displayQRPrompt();
  }
  
  delay(100);
}

// ============================================================================
// WiFi & Network Functions
// ============================================================================

void initWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(WIFI_SSID);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Connected!");
    Serial.println("IP: " + WiFi.localIP().toString());
    displayMessage("WiFi Connected", WiFi.localIP().toString());
    delay(2000);
  } else {
    Serial.println("\nWiFi Connection Failed!");
    displayMessage("WiFi Failed", "Check network");
  }
}

void reconnectWiFi() {
  WiFi.disconnect();
  delay(1000);
  initWiFi();
}

String getDeviceMAC() {
  return WiFi.macAddress();
}

// ============================================================================
// Time Synchronization (NTP)
// ============================================================================

void syncTime() {
  Serial.println("Syncing time with NTP...");
  configTime(5*3600 + 30*60, 0, "pool.ntp.org", "time.nist.gov"); // IST timezone
  
  time_t now = time(nullptr);
  int attempts = 0;
  while (now < 24*3600 && attempts < 20) {
    delay(500);
    now = time(nullptr);
    attempts++;
  }
  
  struct tm timeinfo = *localtime(&now);
  Serial.print("Current time: ");
  Serial.println(asctime(&timeinfo));
}

// ============================================================================
// Authentication & Device Pairing
// ============================================================================

void displayQRPrompt() {
  // Generate or display QR code for pairing
  // This would integrate with device display to show QR
  displayMessage("QR Pairing", "Waiting for scan...");
  
  // In a real implementation, generate QR code here
  String qrData = API_URL + "/pair?mac=" + deviceMAC;
  Serial.println("QR Data: " + qrData);
  
  // Display QR on TFT
  // displayQR(qrData);
}

void authenticateWithQR(String token) {
  // Send QR token to backend
  Serial.println("Authenticating with QR token: " + token);
  
  HTTPClient http;
  String url = API_URL + "/index.php/api/qr/authenticate";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  DynamicJsonDocument doc(1024);
  doc["qr_token"] = token;
  doc["device_mac"] = deviceMAC;
  doc["device_type"] = "ARDUINO_ESP32";
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  Serial.println("Sending: " + jsonString);
  
  int httpCode = http.POST(jsonString);
  String response = http.getString();
  
  Serial.println("Response code: " + String(httpCode));
  Serial.println("Response: " + response);
  
  if (httpCode == 200) {
    DynamicJsonDocument responseDoc(1024);
    deserializeJson(responseDoc, response);
    
    currentUserID = responseDoc["user_id"];
    sessionToken = responseDoc["session_token"].as<String>();
    qrToken = token;
    
    saveCredentials();
    isAuthenticated = true;
    
    displayMessage("Authenticated!", responseDoc["email"].as<String>());
    Serial.println("Authentication successful!");
  } else {
    displayMessage("Auth Failed", "Error: " + String(httpCode));
    Serial.println("Authentication failed!");
  }
  
  http.end();
}

void saveCredentials() {
  EEPROM.put(EEPROM_USER_ID_ADDR, currentUserID);
  EEPROM.putString(EEPROM_DEVICE_MAC_ADDR, deviceMAC);
  EEPROM.putString(EEPROM_SESSION_TOKEN_ADDR, sessionToken);
  EEPROM.putString(EEPROM_QR_TOKEN_ADDR, qrToken);
  EEPROM.commit();
  Serial.println("Credentials saved to EEPROM");
}

void loadCredentials() {
  currentUserID = EEPROM.get(EEPROM_USER_ID_ADDR, 0);
  sessionToken = EEPROM.getString(EEPROM_SESSION_TOKEN_ADDR);
  qrToken = EEPROM.getString(EEPROM_QR_TOKEN_ADDR);
  
  if (currentUserID > 0) {
    Serial.println("Credentials loaded from EEPROM");
  }
}

// ============================================================================
// Schedule Management & Alarms
// ============================================================================

void fetchSchedules() {
  if (currentUserID <= 0) return;
  
  Serial.println("Fetching schedules for user: " + String(currentUserID));
  
  HTTPClient http;
  String url = API_URL + "/index.php/api/device/schedules?user_id=" + String(currentUserID);
  
  http.begin(url);
  http.addHeader("Authorization", "Bearer " + sessionToken);
  
  int httpCode = http.GET();
  String response = http.getString();
  
  if (httpCode == 200) {
    DynamicJsonDocument doc(2048);
    deserializeJson(doc, response);
    
    scheduleCount = 0;
    JsonArray schedules = doc["schedules"];
    
    for (JsonObject schedule : schedules) {
      if (scheduleCount < 10) {
        activeSchedules[scheduleCount].id = schedule["id"];
        activeSchedules[scheduleCount].type = schedule["type"].as<String>();
        activeSchedules[scheduleCount].hour = schedule["hour"];
        activeSchedules[scheduleCount].minute = schedule["minute"];
        activeSchedules[scheduleCount].description = schedule["description"].as<String>();
        activeSchedules[scheduleCount].isCompleted = schedule["is_completed"];
        scheduleCount++;
      }
    }
    
    Serial.println("Fetched " + String(scheduleCount) + " schedules");
    displaySchedules();
  }
  
  http.end();
}

void checkScheduledAlarms() {
  // Check if any schedule time matches current time
  time_t now = time(nullptr);
  struct tm timeinfo = *localtime(&now);
  
  for (int i = 0; i < scheduleCount; i++) {
    if (activeSchedules[i].hour == timeinfo.tm_hour && 
        activeSchedules[i].minute == timeinfo.tm_min &&
        !activeSchedules[i].isCompleted) {
      
      Serial.println("Alarm triggered for: " + activeSchedules[i].type);
      triggerAlarm(activeSchedules[i]);
    }
  }
}

void triggerAlarm(Schedule schedule) {
  alarmActive = true;
  
  HTTPClient http;
  String url = API_URL + "/index.php/api/alarm/trigger";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  DynamicJsonDocument doc(512);
  doc["user_id"] = currentUserID;
  doc["schedule_id"] = schedule.id;
  doc["schedule_type"] = schedule.type;
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  int httpCode = http.POST(jsonString);
  
  if (httpCode == 201) {
    Serial.println("Alarm triggered successfully");
    
    // Activate buzzer
    activateBuzzer();
    
    // Display message
    displayMessage("ALARM!", schedule.type + " Time");
    
    // Send notification
    sendNotification(schedule);
  }
  
  http.end();
}

void activateBuzzer() {
  // Buzz pattern: 500ms on, 500ms off, repeat for 10 seconds
  unsigned long startTime = millis();
  while (millis() - startTime < 10000 && alarmActive) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(500);
    digitalWrite(BUZZER_PIN, LOW);
    delay(500);
  }
}

void stopAlarm() {
  alarmActive = false;
  digitalWrite(BUZZER_PIN, LOW);
  
  HTTPClient http;
  String url = API_URL + "/index.php/api/alarm/dismiss";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  DynamicJsonDocument doc(256);
  doc["user_id"] = currentUserID;
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  int httpCode = http.POST(jsonString);
  Serial.println("Alarm dismissed. Response: " + String(httpCode));
  
  http.end();
}

// ============================================================================
// Notifications
// ============================================================================

void checkNotifications() {
  if (currentUserID <= 0) return;
  
  HTTPClient http;
  String url = API_URL + "/index.php/api/notifications/pending?user_id=" + String(currentUserID);
  
  http.begin(url);
  
  int httpCode = http.GET();
  String response = http.getString();
  
  if (httpCode == 200) {
    DynamicJsonDocument doc(2048);
    deserializeJson(doc, response);
    
    JsonArray notifications = doc["notifications"];
    for (JsonObject notif : notifications) {
      String message = notif["message"].as<String>();
      Serial.println("Notification: " + message);
      displayMessage("Notification", message);
      delay(3000);
    }
  }
  
  http.end();
}

void sendNotification(Schedule schedule) {
  if (currentUserID <= 0) return;
  
  HTTPClient http;
  String url = API_URL + "/index.php/api/notifications/send";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  String message = "It's time for your " + schedule.type;
  
  DynamicJsonDocument doc(512);
  doc["user_id"] = currentUserID;
  doc["schedule_id"] = schedule.id;
  doc["type"] = "ALARM_" + schedule.type;
  doc["message"] = message;
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  int httpCode = http.POST(jsonString);
  Serial.println("Notification sent. Response: " + String(httpCode));
  
  http.end();
}

// ============================================================================
// Commands from Server
// ============================================================================

void checkCommands() {
  if (currentUserID <= 0) return;
  
  HTTPClient http;
  String url = API_URL + "/index.php/api/device/commands?user_id=" + String(currentUserID);
  
  http.begin(url);
  
  int httpCode = http.GET();
  String response = http.getString();
  
  if (httpCode == 200) {
    DynamicJsonDocument doc(1024);
    deserializeJson(doc, response);
    
    JsonArray commands = doc["commands"];
    for (JsonObject cmd : commands) {
      int cmdId = cmd["id"];
      String command = cmd["command"].as<String>();
      
      Serial.println("Command: " + command);
      executeCommand(cmdId, command);
    }
  }
  
  http.end();
}

void executeCommand(int cmdId, String command) {
  // Parse and execute commands from server
  if (command == "BUZZ:ON") {
    digitalWrite(BUZZER_PIN, HIGH);
  } else if (command == "BUZZ:OFF") {
    digitalWrite(BUZZER_PIN, LOW);
  } else if (command.startsWith("DISP:")) {
    String displayText = command.substring(5);
    displayMessage("Message", displayText);
  } else if (command.startsWith("SOL:UNLOCK")) {
    // Signal Leonardo to unlock solenoid
    sendCommandToLeonardo("UNLOCK");
  } else if (command.startsWith("TEMP:")) {
    // Parse temperature command
    String tempValue = command.substring(5);
    sendCommandToLeonardo("TEMP:" + tempValue);
  }
  
  // Mark command as executed
  // Would need to implement command feedback endpoint
  Serial.println("Command executed: " + command);
}

void sendCommandToLeonardo(String command) {
  // Would use Serial or I2C to communicate with Leonardo
  // Serial1.println(command);
}

// ============================================================================
// Display Functions (TFT)
// ============================================================================

void initDisplay() {
  // Initialize TFT display
  // This depends on your specific TFT library
  // Example: Adafruit_ILI9341 for common 3.5" displays
  
  Serial.println("Display initialized");
}

void displayMessage(String title, String message) {
  Serial.println("[DISPLAY] " + title + " - " + message);
  // Update TFT display
  // display.fillScreen(ILI9341_BLACK);
  // display.setTextColor(ILI9341_WHITE);
  // display.setTextSize(2);
  // display.setCursor(10, 20);
  // display.println(title);
  // display.setCursor(10, 60);
  // display.println(message);
}

void displaySchedules() {
  Serial.println("=== Scheduled Alarms ===");
  for (int i = 0; i < scheduleCount; i++) {
    Serial.print(String(activeSchedules[i].hour) + ":");
    Serial.print(String(activeSchedules[i].minute) + " - ");
    Serial.println(activeSchedules[i].type);
  }
}

void displayQR(String qrData) {
  Serial.println("QR Code: " + qrData);
  // Generate and display QR code on TFT
  // Would use QR library to generate and render on display
}

#endif

