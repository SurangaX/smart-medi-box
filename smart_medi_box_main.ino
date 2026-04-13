/*
  ============================================================================
  SMART MEDI BOX - QR Based Medicine & Food Scheduler with Arduino Leonardo
  ============================================================================
  
  System Features:
  - QR Authentication with Android app
  - LCD Display (ST7920 128x64)
  - GSM/SMS Notifications (SIM800L)
  - Real-Time Clock (DS3231)
  - Temperature Monitoring (DHT22, DS18B20)
  - RFID Reader (RC522)
  - Scheduled Alarms (Medicine, Food, Blood Check)
  - Solenoid Lock Control
  - Door Sensor Integration
  - Buzzer Notifications
  - Peltier Temperature Control
  
  Pin Configuration:
  D0/D1: SIM800L TX/RX (crossover)
  D2: Door Switch (INPUT_PULLUP)
  D3: Cooling System (MOSFET)
  D4: Stepper STEP
  D5: Solenoid (MOSFET Gate)
  D6: RFID RST
  D7: Buzzer
  D8: LCD Reset
  D9: RFID CS
  D10: LCD RS
  D11: LCD RW
  D12: Stepper DIR
  D13: LCD E
  A0: DHT22
  A1: DS18B20
  A2: Servo Signal (MG995)
  
  ============================================================================
*/

// ==================== LIBRARIES ====================
#include <Wire.h>
#include <RTClib.h>
#include <U8g2lib.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <SPI.h>
#include <RF24.h>  // For RFID - alternative: use MFRC522 library
#include <Servo.h>

// ==================== PIN DEFINITIONS ====================
// LCD Pins
#define LCD_RST 8
#define LCD_RS 10
#define LCD_RW 11
#define LCD_E 13

// Sensor Pins
#define DHT_PIN A0
#define DS18B20_PIN A1
#define DOOR_PIN 2

// Control Pins
#define SOLENOID_PIN 5
#define COOLING_PIN 3
#define BUZZER_PIN 7
#define SERVO_PIN A2

// RFID Pins
#define RFID_RST 6
#define RFID_CS 9

// Stepper Motor
#define STEPPER_STEP 4
#define STEPPER_DIR 12

// Serial Pins
#define GSM_RX 0  // GSM TX -> Arduino RX
#define GSM_TX 1  // GSM RX -> Arduino TX

// ==================== CONFIGURATION ====================
// Server Details (Update with XAMPP: http://localhost or your PC IP)
const char* SERVER_URL = "http://localhost/smart-medi-box/robot_api/index.php";  // XAMPP Apache server
const char* SERVER_HOST = "localhost";  // XAMPP server
const int SERVER_PORT = 80;  // Apache default port

// GSM Configuration (for SIM800L)
const char* GSM_APN = "hutch3g";      // Sri Lanka carrier APN
const char* GSM_USER = "";                     // Usually empty
const char* GSM_PASS = "";                     // Usually empty

// Timezone Configuration
const float TIMEZONE_OFFSET = 5.5;  // Sri Lanka (UTC+5:30)

// Temperature Settings
const float TARGET_TEMP = 20;       // Refrigerator target: 20°C
const float TEMP_HYSTERESIS = 0.5;  // ±0.5°C band for cooling

// Alarm Settings
const unsigned long ALARM_INTERVAL = 300000;   // 5 minutes between SMS reminders
const int BUZZER_LEVEL = 255;                   // PWM level (0-255)

// Debug Mode
const boolean DEBUG_MODE = true;    // Set to false in production

// ==================== OBJECT INSTANTIATION ====================
// LCD Display (ST7920 128x64 with U8g2)
U8G2_ST7920_128X64_F_SW_SPI u8g2(U8G2_R0, 13, 11, 10, 8);

// RTC Clock
RTC_DS3231 rtc;

// Temperature Sensors
DHT dht(DHT_PIN, DHT22);
OneWire oneWire(DS18B20_PIN);
DallasTemperature sensors(&oneWire);

// Servo Motor
Servo servo;

// ==================== DATA STRUCTURES ====================
struct User {
  char userID[20];
  char userName[50];
  int age;
  char phoneNumber[20];
  char macAddress[18];
  boolean isNewUser;
  boolean isAuthenticated;
};

struct Schedule {
  int scheduleID;
  char type[20];  // "MEDICINE", "FOOD", "BLOOD_CHECK"
  int hour;
  int minute;
  boolean isActive;
  boolean isCompleted;
  char notes[100];
};

struct SystemState {
  boolean doorOpen;
  boolean alarmActive;
  boolean solenoidLocked;
  float currentTemp;
  float currentHumidity;
  boolean doorForcedOpen;
  unsigned long alarmStartTime;
  int currentScheduleID;
};

// ==================== GLOBAL VARIABLES ====================
User currentUser;
Schedule schedules[10];
SystemState sysState;
int scheduleCount = 0;
unsigned long lastGSMCheck = 0;
unsigned long lastTempCheck = 0;
unsigned long lastDoorCheck = 0;
unsigned long lastAlarmReminder = 0;

const unsigned long GSM_CHECK_INTERVAL = 5000;
const unsigned long TEMP_CHECK_INTERVAL = 10000;
const unsigned long DOOR_CHECK_INTERVAL = 100;

// ==================== SETUP FUNCTION ====================
void setup() {
  // Initialize Serial Communications
  Serial.begin(9600);        // USB Serial for debugging
  Serial1.begin(9600);       // GSM Module Serial (SIM800L)
  
  delay(2000);
  
  // Initialize LCD
  u8g2.begin();
  u8g2.setFont(u8g2_font_ncenB08_tr);
  displayMessage("System Init...", "");
  
  // Initialize Pins
  pinMode(DOOR_PIN, INPUT_PULLUP);
  pinMode(SOLENOID_PIN, OUTPUT);
  pinMode(COOLING_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(STEPPER_STEP, OUTPUT);
  pinMode(STEPPER_DIR, OUTPUT);
  pinMode(RFID_RST, OUTPUT);
  pinMode(RFID_CS, OUTPUT);
  
  // Set initial states
  digitalWrite(SOLENOID_PIN, LOW);
  digitalWrite(COOLING_PIN, LOW);
  digitalWrite(BUZZER_PIN, LOW);
  
  // Initialize RTC
  if (!rtc.begin()) {
    displayMessage("RTC Error", "Check SDA/SCL");
    while (1);
  }
  
  // Initialize DHT22
  dht.begin();
  
  // Initialize DS18B20
  sensors.begin();
  
  // Initialize Servo
  servo.attach(SERVO_PIN);
  servo.write(90);  // Center position
  
  // Initialize GSM Module
  initGSM();
  
  // Initialize system state
  initSystemState();
  
  displayMessage("Ready", "Waiting for QR");
  
  if (DEBUG_MODE) {
    Serial.println("Smart Medi Box System Initialized");
  }
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long currentTime = millis();
  
  // Check GSM for messages/authentication
  if (currentTime - lastGSMCheck >= GSM_CHECK_INTERVAL) {
    checkGSMMessages();
    lastGSMCheck = currentTime;
  }
  
  // Read temperature and humidity
  if (currentTime - lastTempCheck >= TEMP_CHECK_INTERVAL) {
    readTemperatureSensors();
    lastTempCheck = currentTime;
  }
  
  // Check door sensor
  if (currentTime - lastDoorCheck >= DOOR_CHECK_INTERVAL) {
    checkDoorSensor();
    lastDoorCheck = currentTime;
  }
  
  // Check scheduled alarms
  if (currentUser.isAuthenticated) {
    checkScheduledAlarms();
  }
  
  // Handle active alarm
  if (sysState.alarmActive) {
    handleActiveAlarm(currentTime);
  }
  
  // Monitor temperature and adjust cooling
  monitorTemperature();
  
  // Update LCD display
  updateDisplay();
  
  delay(100);
}

// ==================== AUTHENTICATION FUNCTIONS ====================
void checkGSMMessages() {
  // Read incoming GSM data (QR auth from mobile app)
  if (Serial1.available()) {
    String data = "";
    while (Serial1.available()) {
      char c = Serial1.read();
      data += c;
      delay(10);
    }
    
    // Parse incoming QR/Auth data
    parseAuthenticationData(data);
  }
}

void parseAuthenticationData(String authData) {
  /*
    Expected format from mobile app:
    AUTH|USER_ID|MAC_ADDRESS|DEVICE_ID
    or
    NEW_USER|NAME|AGE|PHONE|MAC_ADDRESS
  */
  
  int delimiter1 = authData.indexOf('|');
  if (delimiter1 == -1) return;
  
  String authType = authData.substring(0, delimiter1);
  
  if (authType == "AUTH") {
    // Existing user authentication
    int delimiter2 = authData.indexOf('|', delimiter1 + 1);
    int delimiter3 = authData.indexOf('|', delimiter2 + 1);
    
    String userID = authData.substring(delimiter1 + 1, delimiter2);
    String macAddress = authData.substring(delimiter2 + 1, delimiter3);
    
    authenticateUser(userID, macAddress);
  }
  else if (authType == "NEW_USER") {
    // New user registration
    int del2 = authData.indexOf('|', delimiter1 + 1);
    int del3 = authData.indexOf('|', del2 + 1);
    int del4 = authData.indexOf('|', del3 + 1);
    int del5 = authData.indexOf('|', del4 + 1);
    
    String name = authData.substring(delimiter1 + 1, del2);
    String age = authData.substring(del2 + 1, del3);
    String phone = authData.substring(del3 + 1, del4);
    String mac = authData.substring(del4 + 1, del5);
    
    registerNewUser(name, age.toInt(), phone, mac);
  }
}

void authenticateUser(String userID, String macAddress) {
  displayMessage("Authenticating...", "User: " + userID);
  
  // Send request to API to verify user
  String request = "GET /smart-medi-box/robot_api/index.php/api/auth/verify?user_id=" + userID + "&mac=" + macAddress + " HTTP/1.1\r\n";
  Serial1.print(request);
  Serial1.print(String("Host: ") + SERVER_HOST + "\r\n");
  Serial1.print("Connection: close\r\n\r\n");
  
  // Wait for response
  delay(2000);
  
  String response = "";
  while (Serial1.available()) {
    response += (char)Serial1.read();
  }
  
  // Parse response (simplified)
  if (response.indexOf("SUCCESS") != -1) {
    currentUser.isAuthenticated = true;
    strcpy(currentUser.userID, userID.c_str());
    strcpy(currentUser.macAddress, macAddress.c_str());
    
    displayMessage("Auth Success", "Loading schedule...");
    
    // Fetch user schedule from database
    fetchUserSchedule(userID);
    
    delay(1000);
  } else {
    displayMessage("Auth Failed", "Try QR again");
    currentUser.isAuthenticated = false;
  }
}

void registerNewUser(String name, int age, String phone, String mac) {
  displayMessage("New User", name);
  delay(1000);
  
  // Send registration to API
  String request = "POST /smart-medi-box/robot_api/index.php/api/auth/register HTTP/1.1\r\n";
  Serial1.print(request);
  Serial1.print(String("Host: ") + SERVER_HOST + "\r\n");
  Serial1.print("Content-Type: application/x-www-form-urlencoded\r\n");
  Serial1.print("Connection: close\r\n\r\n");
  
  String postData = "name=" + name + "&age=" + String(age) + "&phone=" + phone + "&mac=" + mac;
  Serial1.print(postData);
  
  delay(2000);
  
  // Parse response
  String response = "";
  while (Serial1.available()) {
    response += (char)Serial1.read();
  }
  
  if (response.indexOf("SUCCESS") != -1) {
    currentUser.isNewUser = true;
    currentUser.isAuthenticated = true;
    strcpy(currentUser.userName, name.c_str());
    currentUser.age = age;
    strcpy(currentUser.phoneNumber, phone.c_str());
    strcpy(currentUser.macAddress, mac.c_str());
    
    displayMessage("Registration", "Success!");
    delay(2000);
  }
}

// ==================== SCHEDULE FUNCTIONS ====================
void fetchUserSchedule(String userID) {
  // Fetch schedules from API
  String request = "GET /smart-medi-box/robot_api/index.php/api/schedule/get?user_id=" + userID + " HTTP/1.1\r\n";
  Serial1.print(request);
  Serial1.print(String("Host: ") + SERVER_HOST + "\r\n");
  Serial1.print("Connection: close\r\n\r\n");
  
  delay(2000);
  
  String response = "";
  while (Serial1.available()) {
    response += (char)Serial1.read();
  }
  
  // Parse JSON response with schedules (simplified)
  parseScheduleResponse(response);
  
  if (debugMode) {
    Serial.print("Loaded ");
    Serial.print(scheduleCount);
    Serial.println(" schedules");
  }
}

void parseScheduleResponse(String response) {
  // Simplified schedule parsing from JSON
  // Expected format: SCHEDULE|TYPE|HOUR|MINUTE|NOTES
  // Multiple schedules separated by newlines
  
  scheduleCount = 0;
  
  int startIdx = response.indexOf("SCHEDULE|");
  while (startIdx != -1 && scheduleCount < 10) {
    int endIdx = response.indexOf("\n", startIdx);
    if (endIdx == -1) endIdx = response.length();
    
    String scheduleStr = response.substring(startIdx, endIdx);
    
    // Parse schedule
    int del1 = scheduleStr.indexOf('|');
    int del2 = scheduleStr.indexOf('|', del1 + 1);
    int del3 = scheduleStr.indexOf('|', del2 + 1);
    int del4 = scheduleStr.indexOf('|', del3 + 1);
    
    if (del4 != -1) {
      String type = scheduleStr.substring(del1 + 1, del2);
      int hour = scheduleStr.substring(del2 + 1, del3).toInt();
      int minute = scheduleStr.substring(del3 + 1, del4).toInt();
      String notes = scheduleStr.substring(del4 + 1);
      
      schedules[scheduleCount].scheduleID = scheduleCount;
      strcpy(schedules[scheduleCount].type, type.c_str());
      schedules[scheduleCount].hour = hour;
      schedules[scheduleCount].minute = minute;
      schedules[scheduleCount].isActive = true;
      schedules[scheduleCount].isCompleted = false;
      strcpy(schedules[scheduleCount].notes, notes.c_str());
      
      scheduleCount++;
    }
    
    startIdx = response.indexOf("SCHEDULE|", endIdx);
  }
}

void checkScheduledAlarms() {
  DateTime now = rtc.now();
  
  for (int i = 0; i < scheduleCount; i++) {
    if (!schedules[i].isActive || schedules[i].isCompleted) continue;
    
    if (schedules[i].hour == now.hour() && schedules[i].minute == now.minute()) {
      // Trigger alarm for this schedule
      triggerAlarm(i);
    }
  }
}

void triggerAlarm(int scheduleIdx) {
  sysState.alarmActive = true;
  sysState.currentScheduleID = scheduleIdx;
  sysState.alarmStartTime = millis();
  
  // Unlock solenoid
  unlockSolenoid();
  
  // Start buzzer
  digitalWrite(BUZZER_PIN, HIGH);
  
  // Display message
  displayMessage("ALARM!", schedules[scheduleIdx].type);
  
  // Send SMS notification
  sendSMSNotification(currentUser.phoneNumber, schedules[scheduleIdx].type);
  
  if (DEBUG_MODE) {
    Serial.print("Alarm triggered: ");
    Serial.println(schedules[scheduleIdx].type);
  }
}

void handleActiveAlarm(unsigned long currentTime) {
  // Check if door has been opened
  if (!sysState.doorOpen) {
    // Door still closed, keep alarm running
    digitalWrite(BUZZER_PIN, HIGH);
    
    // Send SMS reminder every 5 minutes
    if (currentTime - lastAlarmReminder >= ALARM_INTERVAL) {
      sendSMSReminder(currentUser.phoneNumber, schedules[sysState.currentScheduleID].type);
      lastAlarmReminder = currentTime;
    }
  } else {
    // Door opened, stop alarm
    stopAlarm();
  }
}

void stopAlarm() {
  digitalWrite(BUZZER_PIN, LOW);
  sysState.alarmActive = false;
  
  // Lock solenoid again
  lockSolenoid();
  
  // Mark schedule as completed
  if (sysState.currentScheduleID >= 0 && sysState.currentScheduleID < scheduleCount) {
    schedules[sysState.currentScheduleID].isCompleted = true;
    
    // Send completion to API
    String request = "POST /smart-medi-box/robot_api/index.php/api/schedule/complete?schedule_id=" + String(schedules[sysState.currentScheduleID].scheduleID);
    request += "&user_id=" + String(currentUser.userID) + " HTTP/1.1\r\n";
    Serial1.print(request);
    Serial1.print(String("Host: ") + SERVER_HOST + "\r\n");
    Serial1.print("Connection: close\r\n\r\n");
  }
  
  displayMessage("Medicine Taken", "Thank You!");
  delay(2000);
}

// ==================== DOOR SENSOR FUNCTIONS ====================
void checkDoorSensor() {
  int doorState = digitalRead(DOOR_PIN);
  boolean doorNowOpen = (doorState == LOW);
  
  // Detect forced open when locked
  if (doorNowOpen && sysState.solenoidLocked && !sysState.alarmActive) {
    // Unauthorized access detected
    triggerUnauthorizedAccessAlarm();
  }
  
  sysState.doorOpen = doorNowOpen;
}

void triggerUnauthorizedAccessAlarm() {
  sysState.alarmActive = true;
  sysState.doorForcedOpen = true;
  digitalWrite(BUZZER_PIN, HIGH);
  
  displayMessage("ALERT!", "Unauthorized");
  
  // Send alert SMS
  sendSMSAlert(currentUser.phoneNumber, "Unauthorized door access");
  
  if (DEBUG_MODE) {
    Serial.println("Unauthorized access detected!");
  }
}

// ==================== SOLENOID CONTROL ====================
void unlockSolenoid() {
  digitalWrite(SOLENOID_PIN, HIGH);
  sysState.solenoidLocked = false;
  
  if (debugMode) Serial.println("Solenoid UNLOCKED");
}

void lockSolenoid() {
  digitalWrite(SOLENOID_PIN, LOW);
  sysState.solenoidLocked = true;
  
  if (debugMode) Serial.println("Solenoid LOCKED");
}

void handleRFIDOverride() {
  // RFID detected - unlock without triggering alarm
  unlockSolenoid();
  displayMessage("RFID Access", "Door Unlocked");
  delay(3000);
}

// ==================== TEMPERATURE FUNCTIONS ====================
void readTemperatureSensors() {
  // Read DHT22
  sysState.currentHumidity = dht.readHumidity();
  float dhtTemp = dht.readTemperature();
  
  // Read DS18B20
  sensors.requestTemperatures();
  float ds18b20Temp = sensors.getTempCByIndex(0);
  
  // Use average or DS18B20 for internal temperature
  sysState.currentTemp = ds18b20Temp;
  
  if (DEBUG_MODE) {
    Serial.print("Temp: ");
    Serial.print(sysState.currentTemp);
    Serial.print("C, Humidity: ");
    Serial.print(sysState.currentHumidity);
    Serial.println("%");
  }
}

void monitorTemperature() {
  if (sysState.currentTemp > TARGET_TEMP + TEMP_HYSTERESIS) {
    // Turn on cooling (Peltier)
    analogWrite(COOLING_PIN, BUZZER_LEVEL);
  } else if (sysState.currentTemp < TARGET_TEMP - TEMP_HYSTERESIS) {
    // Turn off cooling
    analogWrite(COOLING_PIN, 0);
  }
}

// ==================== GSM/SMS FUNCTIONS ====================
void initGSM() {
  // Initialize GSM module
  Serial1.print("AT\r\n");
  delay(1000);
  Serial1.print("AT+CMGF=1\r\n");  // Set SMS format to text
  delay(1000);
  
  displayMessage("GSM Ready", "");
}

void sendSMSNotification(String phoneNumber, String scheduleType) {
  String message = "Take your " + scheduleType + " medication now!";
  sendSMS(phoneNumber, message);
}

void sendSMSReminder(String phoneNumber, String scheduleType) {
  String message = "Reminder: " + scheduleType + " medicine is still waiting!";
  sendSMS(phoneNumber, message);
}

void sendSMSAlert(String phoneNumber, String alertMessage) {
  sendSMS(phoneNumber, "ALERT: " + alertMessage);
}

void sendSMS(String phoneNumber, String message) {
  // Format phone number with country code if needed
  String formattedPhone = phoneNumber;
  if (!formattedPhone.startsWith("+")) {
    formattedPhone = "+94" + formattedPhone;  // Sri Lanka country code
  }
  
  Serial1.print("AT+CMGS=\"" + formattedPhone + "\"\r\n");
  delay(500);
  Serial1.print(message);
  Serial1.print("\x1A");  // Ctrl+Z to send
  delay(1000);
  
  if (DEBUG_MODE) {
    Serial.print("SMS sent to ");
    Serial.print(formattedPhone);
    Serial.print(": ");
    Serial.println(message);
  }
}

// ==================== DISPLAY FUNCTIONS ====================
void displayMessage(String line1, String line2) {
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_ncenB14_tr);
  u8g2.drawStr(0, 20, line1.c_str());
  u8g2.setFont(u8g2_font_ncenB08_tr);
  u8g2.drawStr(0, 40, line2.c_str());
  u8g2.sendBuffer();
}

void updateDisplay() {
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_ncenB08_tr);
  
  if (currentUser.isAuthenticated) {
    // Show user and status
    u8g2.drawStr(0, 10, "User:");
    u8g2.drawStr(40, 10, currentUser.userID);
    
    // Show current time
    DateTime now = rtc.now();
    char timeStr[16];
    sprintf(timeStr, "%02d:%02d:%02d", now.hour(), now.minute(), now.second());
    u8g2.drawStr(0, 25, "Time:");
    u8g2.drawStr(40, 25, timeStr);
    
    // Show temperature
    char tempStr[20];
    sprintf(tempStr, "Temp: %.1fC", sysState.currentTemp);
    u8g2.drawStr(0, 40, tempStr);
    
    // Show door status
    char doorStr[20];
    sprintf(doorStr, "Door: %s", sysState.doorOpen ? "OPEN" : "CLOSED");
    u8g2.drawStr(0, 55, doorStr);
    
    // Show next schedule
    if (scheduleCount > 0) {
      char schedStr[30];
      sprintf(schedStr, "Next: %02d:%02d %s", 
              schedules[0].hour, schedules[0].minute, schedules[0].type);
      u8g2.drawStr(0, 63, schedStr);
    }
  } else {
    // Show waiting for authentication
    u8g2.drawStr(10, 30, "Scan QR Code");
    u8g2.drawStr(10, 50, "to Authenticate");
  }
  
  u8g2.sendBuffer();
}

// ==================== HELPER FUNCTIONS ====================
void initSystemState() {
  sysState.doorOpen = (digitalRead(DOOR_PIN) == LOW);
  sysState.alarmActive = false;
  sysState.solenoidLocked = true;
  sysState.currentTemp = 25.0;
  sysState.currentHumidity = 50.0;
  sysState.doorForcedOpen = false;
  sysState.alarmStartTime = 0;
  sysState.currentScheduleID = -1;
}

void printDebugInfo() {
  Serial.println("\n=== DEBUG INFO ===");
  Serial.print("User: ");
  Serial.println(currentUser.userID);
  Serial.print("Auth: ");
  Serial.println(currentUser.isAuthenticated ? "YES" : "NO");
  Serial.print("Schedules: ");
  Serial.println(scheduleCount);
  Serial.print("Alarm Active: ");
  Serial.println(sysState.alarmActive ? "YES" : "NO");
  Serial.print("Door: ");
  Serial.println(sysState.doorOpen ? "OPEN" : "CLOSED");
  Serial.print("Temp: ");
  Serial.println(sysState.currentTemp);
  Serial.println("==================\n");
}
