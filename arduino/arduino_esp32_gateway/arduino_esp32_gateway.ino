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
       // Debug received lines
       if (line == "MED_TAKEN") {
         Serial.println(">>> MED_TAKEN detected. Notifying server...");
         box.alert = "System Online";
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
  struct tm timeinfo;
  u8g2.clearBuffer();
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
  u8g2.print("C  H: "); u8g2.print(box.hum); u8g2.print("%");

  u8g2.setCursor(0, 48);
  u8g2.print(box.door ? "Door: OPEN" : "Door: CLOSED");

  if (box.alarm) {
    u8g2.drawFrame(0, 50, 128, 14);
    u8g2.drawStr(5, 62, box.alert.c_str());
  } else {
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
    
    if (doc["status"] == "SUCCESS" && doc.containsKey("user_name")) {
      box.user = doc["user_name"].as<String>();
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
