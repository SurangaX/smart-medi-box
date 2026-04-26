/*
  ============================================================================
  SMART MEDI BOX - ESP32 (IOT LOGIC & TIME PROVIDER)
  ============================================================================
*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <U8g2lib.h>
#include "time.h"

// WiFi Credentials
const char* WIFI_SSID = "Lord Of the Pings";
const char* WIFI_PASS = "SurangaX";
const char* API_BASE = "https://smart-medi-box.onrender.com/api";

// NTP Settings
const char* ntpServer = "pool.ntp.org";
const long  gmtOffset_sec = 19800; // Sri Lanka (GMT+5:30)
const int   daylightOffset_sec = 0;

// LCD ST7920 (SPI Mode: PSB=GND)
// E=18 (SCLK), RW=23 (SID), RS=5 (CS), RST=NONE
U8G2_ST7920_128X64_F_SW_SPI u8g2(U8G2_R0, 18, 23, 5, U8X8_PIN_NONE);

// Communication with Leonardo (TX2=17, RX2=16)
HardwareSerial LeoSerial(2);

struct {
  float temp = 0;
  int hum = 0;
  bool door = false;
  bool lock = false; // true = UNLOCKED
  String user = "Unpaired";
  String alert = "System Online";
  int alarm = 0;
  String sched_name = "Medicine";
  String sched_time = "Now";
  float target_temp = 25.0; // Default target
} box;

void setup() {
  Serial.begin(115200);
  LeoSerial.begin(115200, SERIAL_8N1, 16, 17); // Fast Communication
  
  // Initialize LCD
  u8g2.begin();
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_6x10_tf);
  u8g2.drawStr(0, 30, "BOOTING SYSTEM...");
  u8g2.sendBuffer();

  WiFi.begin(WIFI_SSID, WIFI_PASS);
  while (WiFi.status() != WL_CONNECTED) { delay(500); }
  
  configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
  fetchUserInfo();
}

void loop() {
  // 1. Receive sensor data from Leonardo
  if (LeoSerial.available()) {
    String line = LeoSerial.readStringUntil('\n');
    line.trim();

    if (line.length() > 0) {
       if (line == "MED_TAKEN") {
         Serial.println(">>> MED_TAKEN detected. Notifying server...");
         box.alert = "System Online";
         box.alarm = 0; // Clear local alarm state immediately
         renderUI();
         notifyMedicineTaken();
       } else if (line.startsWith("{")) {
         DynamicJsonDocument doc(200);
         if (!deserializeJson(doc, line)) {
           box.temp = doc["t"];
           box.hum = doc["h"];
           box.door = doc["d"];
           box.alarm = doc["a"];
           box.lock = doc["l"];
           // REAL-TIME: Force refresh UI when state packet arrives
           renderUI(); 
         }
       }
    }
  }

  // 2. Render LCD UI
  static unsigned long lastUI = 0;
  if (millis() - lastUI > 1000) {
    renderUI();
    lastUI = millis();
  }

  // 3. Heartbeat & Sync to Server (More frequent for real-time dash)
  static unsigned long lastSync = 0;
  if (millis() - lastSync > 10000) { // Sync every 10 seconds
    LeoSerial.println("req_data"); 
    syncToServer();
    
    if (box.user == "Unpaired" || box.user == "") {
      fetchUserInfo();
    }
    
    lastSync = millis();
  }

  // 4. Poll for commands from server
  static unsigned long lastCmd = 0;
  if (millis() - lastCmd > 5000) { 
    fetchCommands();
    lastCmd = millis();
  }
}

void renderUI() {
  u8g2.clearBuffer();
  
  if (box.alarm) {
    // --- ALARM ACTIVE UI (4 ROWS ALL AT ONCE) ---
    u8g2.setFont(u8g2_font_7x14_tf); 
    
    // Row 1: Schedule Name
    String row1 = box.sched_name;
    int x1 = (128 - u8g2.getStrWidth(row1.c_str())) / 2;
    u8g2.drawStr(x1 > 0 ? x1 : 0, 14, row1.c_str());
    
    // Row 2: Schedule Time
    String row2 = "Time: " + box.sched_time;
    int x2 = (128 - u8g2.getStrWidth(row2.c_str())) / 2;
    u8g2.drawStr(x2 > 0 ? x2 : 0, 30, row2.c_str());
    
    // Row 3: Shortened user name
    String shortUser = box.user;
    int spaceIdx = shortUser.indexOf(' ');
    if (spaceIdx != -1) shortUser = shortUser.substring(0, spaceIdx);
    if (shortUser.length() > 10) shortUser = shortUser.substring(0, 10);
    String row3 = "User: " + shortUser;
    int x3 = (128 - u8g2.getStrWidth(row3.c_str())) / 2;
    u8g2.drawStr(x3 > 0 ? x3 : 0, 46, row3.c_str());
    
    // Row 4: Status / Door State
    String row4 = "DOOR UNLOCKED";
    int x4 = (128 - u8g2.getStrWidth(row4.c_str())) / 2;
    u8g2.drawStr(x4 > 0 ? x4 : 0, 62, row4.c_str());

  } else {
    // --- NORMAL UI ---
    struct tm timeinfo;
    u8g2.setFont(u8g2_font_6x10_tf);

    if (getLocalTime(&timeinfo)) {
      char dateStr[12];
      char timeStr[6];
      strftime(dateStr, 12, "%Y-%m-%d", &timeinfo);
      strftime(timeStr, 6, "%H:%M", &timeinfo);
      
      u8g2.setCursor(0, 10);
      u8g2.print(dateStr);
      u8g2.print(" | ");
      u8g2.print(timeStr);
    } else {
      u8g2.setCursor(0, 10);
      u8g2.print("Time Sync Error");
    }

    u8g2.setCursor(0, 22);
    u8g2.print("User: ");
    u8g2.print(box.user);

    u8g2.setCursor(0, 35);
    u8g2.print("T: "); u8g2.print(box.temp, 1);
    u8g2.print("C / "); u8g2.print(box.target_temp, 1); u8g2.print("C");

    u8g2.setCursor(0, 48);
    u8g2.print(box.door ? "Door: OPEN" : "Door: CLOSED");

    if (box.lock) {
      u8g2.drawStr(80, 48, "| UNLOCKED");
    }

    u8g2.drawStr(0, 62, "Status: Online");
  }

  u8g2.sendBuffer();
}


void fetchUserInfo() {
  HTTPClient http;
  http.begin(String(API_BASE) + "/device/register");
  http.addHeader("Content-Type", "application/json");
  String mac = WiFi.macAddress();
  String payload = "{\"mac_address\":\"" + mac + "\"}";
  int code = http.POST(payload);
  
  if (code == 200 || code == 201) {
    DynamicJsonDocument doc(512);
    deserializeJson(doc, http.getString());
    
    if (doc["status"] == "SUCCESS") {
      if (doc.containsKey("user_name")) {
        box.user = doc["user_name"].as<String>();
      }
      if (doc.containsKey("target_temp")) {
        box.target_temp = doc["target_temp"].as<float>();
        Serial.println("Initial Target Temp: " + String(box.target_temp));
      }
    } else {
      box.user = "Unpaired";
    }
  }
  http.end();
}

void syncToServer() {
  HTTPClient http;
  http.begin(String(API_BASE) + "/device/update-status");
  http.addHeader("Content-Type", "application/json");
  DynamicJsonDocument doc(256);
  doc["mac_address"] = WiFi.macAddress();
  doc["temperature"] = box.temp;
  doc["humidity"] = box.hum;
  doc["door_open"] = box.door;
  doc["lock_open"] = box.lock;
  doc["alarm_active"] = (box.alarm > 0);
  
  String payload;
  serializeJson(doc, payload);
  http.POST(payload);
  http.end();
}

void notifyMedicineTaken() {
  HTTPClient http;
  String url = String(API_BASE) + "/device/med-taken";
  Serial.print("HTTP POST: "); Serial.println(url);
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  String payload = "{\"mac_address\":\"" + WiFi.macAddress() + "\"}";
  int code = http.POST(payload);
  
  Serial.print("HTTP Result: "); Serial.println(code);
  if (code > 0) {
    Serial.println("Response: " + http.getString());
  }
  http.end();
}

void fetchCommands() {
  HTTPClient http;
  String url = String(API_BASE) + "/device/check-commands?mac_address=" + WiFi.macAddress();
  http.begin(url);
  int code = http.GET();
  
  if (code == 200) {
    DynamicJsonDocument doc(2048);
    String response = http.getString();
    deserializeJson(doc, response);
    
    if (doc["status"] == "SUCCESS") {
      JsonArray cmds = doc["commands"].as<JsonArray>();
      for (JsonObject cmd : cmds) {
        String commandStr = cmd["command"].as<String>();
        int cmdId = cmd["id"].as<int>();
        
        // Handle ALARM_DATA for display
        if (commandStr.startsWith("ALARM_DATA|")) {
           int firstPipe = commandStr.indexOf('|');
           int secondPipe = commandStr.indexOf('|', firstPipe + 1);
           if (firstPipe != -1 && secondPipe != -1) {
             box.sched_name = commandStr.substring(firstPipe + 1, secondPipe);
             box.sched_time = commandStr.substring(secondPipe + 1);
             box.alarm = 1; // FORCE alarm state on for UI immediate update
             Serial.println("Received Sched: " + box.sched_name + " @ " + box.sched_time);
             renderUI(); // Immediate refresh
           }
        }
        
        // Handle TEMP_SET for display
        if (commandStr.startsWith("TEMP_SET|")) {
           box.target_temp = commandStr.substring(9).toFloat();
           Serial.println("Target Temp Updated: " + String(box.target_temp));
           renderUI();
        }

        if (commandStr == "SOL:UNLOCK") {
           box.sched_name = "MANUAL TRIGGER";
           box.sched_time = "NOW";
           box.alarm = 1; // Reuse alarm UI for clear visual indication
           renderUI();
        }

        if (commandStr.startsWith("DISPLAY:")) {
           box.sched_name = "DISPENSING...";
           box.sched_time = commandStr.substring(8);
           box.alarm = 1;
           renderUI();
        }

        LeoSerial.println(commandStr);
        Serial.print("Forwarded cmd: "); Serial.println(commandStr);
        
        delay(50); // Faster processing
        markCommandComplete(cmdId);
      }
    }
  }
  http.end();
}

void markCommandComplete(int cmdId) {
  HTTPClient http;
  http.begin(String(API_BASE) + "/device/complete-command");
  http.addHeader("Content-Type", "application/json");
  String payload = "{\"command_id\":" + String(cmdId) + "}";
  http.POST(payload);
  http.end();
}
