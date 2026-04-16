/*
 * ============================================================================
 * SMART MEDI BOX - Arduino Leonardo Sensor & Actuator Controller
 * ============================================================================
 * 
 * Features:
 * - Door Lock Control (Solenoid) - GPIO 3
 * - Door Sensor (Magnetic Switch) - GPIO 2
 * - RFID Reader - Serial RX/TX
 * - Temperature Sensor (DS18B20) - GPIO 4
 * - Humidity Sensor (DHT22) - GPIO 5
 * - Buzzer Control - GPIO 6
 * - Status LED - GPIO 13
 * 
 * Communication:
 * - Serial (USB) with ESP32 Gateway
 * - I2C with Peltier Controller
 * 
 * ============================================================================
 */

#include <Wire.h>
#include <DallasTemperature.h>
#include <OneWire.h>
#include <DHT.h>
#include <SoftwareSerial.h>

// ============================================================================
// Pin Definitions
// ============================================================================

#define SOLENOID_PIN 3        // Relay to control solenoid lock
#define DOOR_SENSOR_PIN 2     // Door contact sensor (normally closed)
#define BUZZER_PIN 6          // Buzzer for alarms
#define TEMP_SENSOR_PIN 4     // DS18B20 temperature sensor
#define DHT_PIN 5             // DHT22 humidity sensor
#define LED_PIN 13            // Status LED
#define RFID_RX_PIN 8         // Software serial RX for RFID
#define RFID_TX_PIN 9         // Software serial TX for RFID (not used)

#define DHT_TYPE DHT22

// ============================================================================
// Sensor Objects
// ============================================================================

OneWire oneWire(TEMP_SENSOR_PIN);
DallasTemperature tempSensor(&oneWire);
DHT dht(DHT_PIN, DHT_TYPE);
SoftwareSerial rfidSerial(RFID_RX_PIN, RFID_TX_PIN);

// ============================================================================
// Global State Variables
// ============================================================================

bool solenoidLocked = false;
bool doorOpen = false;
bool alarmActive = false;
bool rfidEnabled = false;

float currentTemp = 0.0;
float currentHumidity = 0.0;
float targetTemp = 4.0;

unsigned long lastTempRead = 0;
unsigned long lastRFIDCheck = 0;
unsigned long lastDoorCheck = 0;
unsigned long lastBuzzerToggle = 0;

const unsigned long TEMP_READ_INTERVAL = 30000;    // Read temp every 30 seconds
const unsigned long RFID_CHECK_INTERVAL = 1000;    // Check RFID every 1 second
const unsigned long DOOR_CHECK_INTERVAL = 100;     // Check door sensor every 100ms
const unsigned long BUZZER_TOGGLE_INTERVAL = 500;  // Buzz pattern 500ms on/off

char rfidBuffer[20];
int rfidIndex = 0;

// ============================================================================
// Setup & Initialization
// ============================================================================

void setup() {
  // Initialize pins
  pinMode(SOLENOID_PIN, OUTPUT);
  pinMode(DOOR_SENSOR_PIN, INPUT_PULLUP);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_PIN, OUTPUT);
  
  // Initialize high (solenoid starts locked)
  digitalWrite(SOLENOID_PIN, HIGH);
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(LED_PIN, LOW);
  
  solenoidLocked = true;
  
  // Initialize serial communication
  Serial.begin(9600);    // USB Serial to ESP32
  rfidSerial.begin(9600); // Software serial for RFID
  
  // Initialize sensors
  tempSensor.begin();
  dht.begin();
  
  Serial.println("Smart Medi Box - Leonardo Controller Started");
  Serial.println("Waiting for commands from ESP32 Gateway...");
  Serial.println("Status: READY");
  
  // Light up LED to indicate ready state
  digitalWrite(LED_PIN, HIGH);
  delay(100);
  digitalWrite(LED_PIN, LOW);
}

// ============================================================================
// Main Loop
// ============================================================================

void loop() {
  // Read temperature periodically
  if (millis() - lastTempRead > TEMP_READ_INTERVAL) {
    readTemperature();
    lastTempRead = millis();
  }
  
  // Check RFID reader
  if (millis() - lastRFIDCheck > RFID_CHECK_INTERVAL) {
    checkRFID();
    lastRFIDCheck = millis();
  }
  
  // Check door sensor
  if (millis() - lastDoorCheck > DOOR_CHECK_INTERVAL) {
    checkDoorSensor();
    lastDoorCheck = millis();
  }
  
  // Handle buzzer pattern
  if (alarmActive) {
    buzzPattern();
  }
  
  // Check for commands from ESP32
  if (Serial.available()) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    processCommand(command);
  }
  
  delay(10);
}

// ============================================================================
// Temperature Monitoring & Control
// ============================================================================

void readTemperature() {
  tempSensor.requestTemperatures();
  currentTemp = tempSensor.getTempCByIndex(0);
  currentHumidity = dht.readHumidity();
  
  Serial.print("TEMP:");
  Serial.print(currentTemp);
  Serial.print(",HUMIDITY:");
  Serial.println(currentHumidity);
  
  // Temperature control logic
  if (currentTemp > targetTemp + 0.5) {
    // Too warm, activate cooling
    activateCooling();
  } else if (currentTemp < targetTemp - 0.5) {
    // Too cold, deactivate cooling
    deactivateCooling();
  }
}

void activateCooling() {
  // Send command to Peltier controller via I2C
  Wire.beginTransmission(0x10); // Peltier controller address
  Wire.write("COOL:ON");
  Wire.endTransmission();
}

void deactivateCooling() {
  // Send command to turn off Peltier device
  Wire.beginTransmission(0x10);
  Wire.write("COOL:OFF");
  Wire.endTransmission();
}

// ============================================================================
// Door Sensor Monitoring
// ============================================================================

void checkDoorSensor() {
  bool doorOpenNow = digitalRead(DOOR_SENSOR_PIN) == LOW; // LOW = door open
  
  if (doorOpenNow != doorOpen) {
    doorOpen = doorOpenNow;
    
    if (doorOpen) {
      Serial.println("DOOR:OPENED");
      onDoorOpened();
    } else {
      Serial.println("DOOR:CLOSED");
      onDoorClosed();
    }
  }
}

void onDoorOpened() {
  // If alarm is active and solenoid is unlocked, stop alarm
  if (alarmActive && !solenoidLocked) {
    stopAlarm();
    Serial.println("ALARM:DISMISSED");
    
    // Notify ESP32 that door was opened
    Serial.println("EVENT:DOOR_OPENED_DURING_ALARM");
  }
}

void onDoorClosed() {
  // Door was closed
  Serial.println("DOOR:LOCKED");
}

// ============================================================================
// RFID Reader
// ============================================================================

void checkRFID() {
  if (rfidSerial.available()) {
    char c = rfidSerial.read();
    
    // Process RFID data
    if (c == '\r' || c == '\n') {
      if (rfidIndex > 0) {
        rfidBuffer[rfidIndex] = '\0';
        processRFIDTag(rfidBuffer);
        rfidIndex = 0;
      }
    } else {
      if (rfidIndex < 19) {
        rfidBuffer[rfidIndex++] = c;
      }
    }
  }
}

void processRFIDTag(char* tag) {
  Serial.print("RFID_TAG:");
  Serial.println(tag);
  
  // Send RFID tag to ESP32 for verification
  Serial.print("RFID_VERIFY:");
  Serial.println(tag);
  
  // This will be verified by backend
  // If verified, solenoid will be unlocked without triggering alarm
}

// ============================================================================
// Solenoid Lock Control
// ============================================================================

void unlockSolenoid() {
  digitalWrite(SOLENOID_PIN, LOW);  // LOW = unlock
  solenoidLocked = false;
  Serial.println("SOLENOID:UNLOCKED");
  digitalWrite(LED_PIN, HIGH);
}

void lockSolenoid() {
  digitalWrite(SOLENOID_PIN, HIGH); // HIGH = lock
  solenoidLocked = true;
  Serial.println("SOLENOID:LOCKED");
  digitalWrite(LED_PIN, LOW);
}

// ============================================================================
// Buzzer & Alarm Control
// ============================================================================

void startAlarm() {
  alarmActive = true;
  Serial.println("ALARM:STARTED");
}

void stopAlarm() {
  alarmActive = false;
  digitalWrite(BUZZER_PIN, LOW);
  Serial.println("ALARM:STOPPED");
}

void buzzPattern() {
  // Buzzer pattern: 500ms on, 500ms off
  if (millis() - lastBuzzerToggle > BUZZER_TOGGLE_INTERVAL) {
    int buzzerState = digitalRead(BUZZER_PIN);
    digitalWrite(BUZZER_PIN, !buzzerState);
    lastBuzzerToggle = millis();
  }
}

// ============================================================================
// Command Processing
// ============================================================================

void processCommand(String command) {
  Serial.println("CMD:" + command);
  
  if (command == "BUZZ:ON") {
    startAlarm();
    unlockSolenoid();
  } else if (command == "BUZZ:OFF") {
    stopAlarm();
  } else if (command == "SOL:UNLOCK") {
    unlockSolenoid();
  } else if (command == "SOL:LOCK") {
    lockSolenoid();
  } else if (command.startsWith("TEMP:")) {
    // Set target temperature
    String tempStr = command.substring(5);
    targetTemp = tempStr.toFloat();
    Serial.print("TARGET_TEMP:");
    Serial.println(targetTemp);
  } else if (command == "STATUS") {
    reportStatus();
  } else if (command == "RFID:ENABLE") {
    rfidEnabled = true;
    Serial.println("RFID:ENABLED");
  } else if (command == "RFID:DISABLE") {
    rfidEnabled = false;
    Serial.println("RFID:DISABLED");
  } else if (command == "RESET") {
    resetSystem();
  } else {
    Serial.println("UNKNOWN_COMMAND");
  }
}

void reportStatus() {
  Serial.print("STATUS:");
  Serial.print("SOLENOID=");
  Serial.print(solenoidLocked ? "LOCKED" : "UNLOCKED");
  Serial.print(",DOOR=");
  Serial.print(doorOpen ? "OPEN" : "CLOSED");
  Serial.print(",ALARM=");
  Serial.print(alarmActive ? "ACTIVE" : "INACTIVE");
  Serial.print(",TEMP=");
  Serial.print(currentTemp);
  Serial.print(",TARGET=");
  Serial.println(targetTemp);
}

void resetSystem() {
  stopAlarm();
  lockSolenoid();
  digitalWrite(BUZZER_PIN, LOW);
  Serial.println("SYSTEM:RESET");
}

// ============================================================================
// Utility Functions
// ============================================================================

void blink(int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(LED_PIN, HIGH);
    delay(100);
    digitalWrite(LED_PIN, LOW);
    delay(100);
  }
}

void emergencyStop() {
  // Safety function: stop all operations
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(SOLENOID_PIN, HIGH); // Lock solenoid
  solenoidLocked = true;
  alarmActive = false;
  Serial.println("EMERGENCY_STOP");
}

