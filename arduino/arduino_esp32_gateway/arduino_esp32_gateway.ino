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
  String user = "Unpaired";
  int alarm = 0;
} box;

void setup() {
  Serial.begin(115200);
  LeoSerial.begin(9600, SERIAL_8N1, 16, 17); // RX=16, TX=17
  
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
    String json = LeoSerial.readStringUntil('\n');
    DynamicJsonDocument doc(200);
    if (!deserializeJson(doc, json)) {
      box.temp = doc["t"];
      box.hum = doc["h"];
      box.door = doc["d"];
      box.alarm = doc["a"];
    }
  }

  // 2. Render LCD UI
  static unsigned long lastUI = 0;
  if (millis() - lastUI > 1000) {
    renderUI();
    lastUI = millis();
  }

  // 3. Heartbeat & Sync to Server
  static unsigned long lastSync = 0;
  if (millis() - lastSync > 60000) {
    LeoSerial.println("req_data"); // Request fresh data from Leonardo
    syncToServer();
    lastSync = millis();
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
    u8g2.drawStr(25, 62, "ALARM ACTIVE!");
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
  if (code > 0) {
    DynamicJsonDocument doc(512);
    deserializeJson(doc, http.getString());
    if (doc.containsKey("user_name")) box.user = doc["user_name"].as<String>();
  }
  http.end();
}

void syncToServer() {
  HTTPClient http;
  http.begin(String(API_BASE) + "/device/update-status");
  http.addHeader("Content-Type", "application/json");
  DynamicJsonDocument doc(200);
  doc["mac_address"] = WiFi.macAddress();
  doc["temperature"] = box.temp;
  doc["humidity"] = box.hum;
  doc["door_open"] = box.door;
  String payload;
  serializeJson(doc, payload);
  http.POST(payload);
  http.end();
}
