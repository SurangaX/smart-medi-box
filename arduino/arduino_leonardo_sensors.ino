/*
  ============================================================================
  SMART MEDI BOX - Arduino Leonardo (SENSOR CONTROLLER ONLY)
  ============================================================================
  
  This sketch runs ONLY on Arduino Leonardo
  Handles: Sensors, RFID, Solenoid, Buzzer, Display, Temperature Control
  Communicates with ESP32 via Serial (Software Serial)
  
  Memory Usage: ~12KB (fits comfortably in Leonardo's 28KB)
  
  Serial Communication Protocol (to ESP32):
  {
    "action": "data",
    "device_id": "...",
    "temp": 25.5,
    "humidity": 60,
    "door_open": false,
    "alarm_active": false
  }
  
  ============================================================================
*/

// ==================== LIBRARIES ====================
#include <Wire.h>
#include <RTClib.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <SPI.h>
#include <Servo.h>
#include <U8g2lib.h>
#include <MFRC522.h>
#include <Stepper.h>

// ==================== PIN DEFINITIONS ====================
// Sensors
#define DHT_PIN A0
#define DS18B20_PIN A1
#define DOOR_PIN 2

// Actuators
#define SOLENOID_PIN 5
#define COOLING_PIN 3
#define BUZZER_PIN 7
#define SERVO_PIN A2

// Display (LCD - SPI)
#define LCD_CS 10
#define LCD_RES 8

// RFID (SPI)
#define RFID_SS 4

// Stepper Motor (Medicine Dispenser)
#define STEPPER_IN1 6
#define STEPPER_IN2 11
#define STEPPER_IN3 12
#define STEPPER_IN4 13

// ==================== CONFIGURATION ====================
const float TARGET_TEMP = 4.0;
const float TEMP_HYSTERESIS = 0.5;
const boolean DEBUG_MODE = true;

// ==================== OBJECTS ====================
RTC_DS3231 rtc;
DHT dht(DHT_PIN, DHT22);
OneWire oneWire(DS18B20_PIN);
DallasTemperature sensors(&oneWire);
Servo servo;
U8G2_ST7920_128X64_F_HW_SPI u8g2(U8G2_R0, LCD_CS, LCD_RES);
MFRC522 rfid(RFID_SS, 10);
Stepper stepper(2048, STEPPER_IN1, STEPPER_IN3, STEPPER_IN2, STEPPER_IN4);

// ==================== GLOBALS ====================
float currentTemp = 25.0;
float currentHumidity = 50.0;
boolean doorOpen = false;
boolean alarmActive = false;
boolean solenoidLocked = true;

// Timing
unsigned long lastTempCheck = 0;
unsigned long lastRFIDCheck = 0;
unsigned long lastDisplayUpdate = 0;
unsigned long lastDataSend = 0;

// ==================== SETUP ====================
void setup() {
  Serial.begin(9600);   // Communication with ESP32
  
  delay(1000);
  
  // Initialize pins
  pinMode(DOOR_PIN, INPUT_PULLUP);
  pinMode(SOLENOID_PIN, OUTPUT);
  pinMode(COOLING_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  
  digitalWrite(SOLENOID_PIN, LOW);
  digitalWrite(COOLING_PIN, LOW);
  digitalWrite(BUZZER_PIN, LOW);
  
  // Initialize RTC
  if (!rtc.begin()) {
    debugPrint("RTC Error");
    while (1);
  }
  
  // Initialize sensors
  dht.begin();
  sensors.begin();
  servo.attach(SERVO_PIN);
  servo.write(90);
  
  // Initialize display
  u8g2.begin();
  displayStartup();
  
  // Initialize RFID
  SPI.begin();
  rfid.PCD_Init();
  
  // Initialize stepper
  stepper.setSpeed(10);
  
  debugPrint("Leonardo Initialization Complete");
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long now = millis();
  
  // Temperature monitoring
  if (now - lastTempCheck >= 10000) {
    readTemperature();
    monitorTemperature();
    lastTempCheck = now;
  }
  
  // RFID card detection
  if (now - lastRFIDCheck >= 500) {
    checkRFIDCard();
    lastRFIDCheck = now;
  }
  
  // Door sensor
  checkDoorSensor();
  
  // Update display
  if (now - lastDisplayUpdate >= 2000) {
    updateDisplay();
    lastDisplayUpdate = now;
  }
  
  // Send sensor data to ESP32
  if (now - lastDataSend >= 5000) {
    sendDataToESP32();
    lastDataSend = now;
  }
  
  // Listen for commands from ESP32
  if (Serial.available()) {
    processCommand();
  }
  
  delay(50);
}

// ==================== SENSOR FUNCTIONS ====================
void readTemperature() {
  currentHumidity = dht.readHumidity();
  sensors.requestTemperatures();
  currentTemp = sensors.getTempCByIndex(0);
  
  if (DEBUG_MODE) {
    Serial.print("[SENSOR] Temp: ");
    Serial.print(currentTemp);
    Serial.print("°C, Humidity: ");
    Serial.print(currentHumidity);
    Serial.println("%");
  }
}

void monitorTemperature() {
  if (currentTemp > TARGET_TEMP + TEMP_HYSTERESIS) {
    analogWrite(COOLING_PIN, 180);
  } else if (currentTemp < TARGET_TEMP - TEMP_HYSTERESIS) {
    analogWrite(COOLING_PIN, 0);
  }
}

// ==================== DOOR FUNCTIONS ====================
void checkDoorSensor() {
  boolean newState = (digitalRead(DOOR_PIN) == LOW);
  
  if (newState != doorOpen) {
    doorOpen = newState;
    debugPrint("Door: " + String(doorOpen ? "OPEN" : "CLOSED"));
  }
}

// ==================== ALARM FUNCTIONS ====================
void triggerAlarm() {
  alarmActive = true;
  unlockSolenoid();
  digitalWrite(BUZZER_PIN, HIGH);
  debugPrint("Alarm triggered");
}

void stopAlarm() {
  alarmActive = false;
  digitalWrite(BUZZER_PIN, LOW);
  lockSolenoid();
  debugPrint("Alarm stopped");
}

// ==================== SOLENOID FUNCTIONS ====================
void unlockSolenoid() {
  digitalWrite(SOLENOID_PIN, HIGH);
  solenoidLocked = false;
}

void lockSolenoid() {
  digitalWrite(SOLENOID_PIN, LOW);
  solenoidLocked = true;
}

// ==================== RFID FUNCTIONS ====================
void checkRFIDCard() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) return;
  
  debugPrint("RFID detected");
  
  // If alarm is active, dispense medicine
  if (alarmActive) {
    dispenseMedicine();
  }
  
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
}

void dispenseMedicine() {
  rotateDispenserMotor();
  delay(500);
  stopAlarm();
}

void rotateDispenserMotor() {
  stepper.step(256);
  delay(300);
  stepper.step(-256);
}

// ==================== DISPLAY FUNCTIONS ====================
void displayStartup() {
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_5x7_tf);
  u8g2.setCursor(0, 10);
  u8g2.print("MediBox Leonardo");
  u8g2.setCursor(0, 20);
  u8g2.print("Waiting for ESP32...");
  u8g2.sendBuffer();
}

void updateDisplay() {
  u8g2.clearBuffer();
  u8g2.setFont(u8g2_font_5x7_tf);
  
  // Line 1: Time
  u8g2.setCursor(0, 8);
  DateTime now = rtc.now();
  if (now.hour() < 10) u8g2.print("0");
  u8g2.print(now.hour());
  u8g2.print(":");
  if (now.minute() < 10) u8g2.print("0");
  u8g2.print(now.minute());
  
  // Line 2: Status
  u8g2.setCursor(0, 16);
  u8g2.print("T:");
  u8g2.print(currentTemp, 1);
  u8g2.print("C H:");
  u8g2.print(currentHumidity, 0);
  u8g2.print("%");
  
  // Line 3: Door
  u8g2.setCursor(0, 24);
  u8g2.print("Door:");
  u8g2.print(doorOpen ? "OPEN" : "CLOSED");
  
  // Line 4: Alarm
  u8g2.setCursor(0, 32);
  if (alarmActive) {
    u8g2.print("ALARM ACTIVE!");
  } else {
    u8g2.print("Ready");
  }
  
  u8g2.sendBuffer();
}

// ==================== COMMUNICATION FUNCTIONS ====================
void sendDataToESP32() {
  // Send sensor data to ESP32 in JSON format
  Serial.print("{\"action\":\"data\",\"temp\":");
  Serial.print(currentTemp, 2);
  Serial.print(",\"humidity\":");
  Serial.print(currentHumidity, 2);
  Serial.print(",\"door_open\":");
  Serial.print(doorOpen ? "true" : "false");
  Serial.print(",\"alarm_active\":");
  Serial.print(alarmActive ? "true" : "false");
  Serial.println("}");
}

void processCommand() {
  // Read command from ESP32
  String command = Serial.readStringUntil('\n');
  command.trim();
  
  debugPrint("Command: " + command);
  
  if (command.indexOf("trigger_alarm") != -1) {
    triggerAlarm();
  } else if (command.indexOf("stop_alarm") != -1) {
    stopAlarm();
  } else if (command.indexOf("dispense") != -1) {
    dispenseMedicine();
  } else if (command.indexOf("buzzer_on") != -1) {
    digitalWrite(BUZZER_PIN, HIGH);
  } else if (command.indexOf("buzzer_off") != -1) {
    digitalWrite(BUZZER_PIN, LOW);
  } else if (command.indexOf("lock") != -1) {
    lockSolenoid();
  } else if (command.indexOf("unlock") != -1) {
    unlockSolenoid();
  }
}

// ==================== UTILITY FUNCTIONS ====================
void debugPrint(String msg) {
  if (DEBUG_MODE) {
    Serial.println("[DEBUG] " + msg);
  }
}

// ==================== END OF LEONARDO SKETCH ====================
