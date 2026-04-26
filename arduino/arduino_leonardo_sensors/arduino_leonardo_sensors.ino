/*
  ============================================================================
  SMART MEDI BOX - LEONARDO (FINAL FIXED + ORIGINAL ESP SEND SYSTEM)
  ============================================================================
*/

#include <Wire.h>
#include <RTClib.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Servo.h>
#include <SoftwareSerial.h>
#include <DFRobotDFPlayerMini.h>

// ================= PINS =================
#define DOOR_PIN     8
#define COOLING_PIN  13
#define STEP_PIN     4
#define SOLENOID_PIN 5
#define RFID_RST     6
#define BUZZER_PIN   7
#define RFID_SS      9
#define DIR_PIN      12
#define DHT_PIN      A0
#define DS18B20_PIN  A1
#define SERVO_PIN    A2
#define DF_RX        A3
#define DF_TX        A4

// ================= OBJECTS =================
RTC_DS3231 rtc;
DHT dht(DHT_PIN, DHT22);
OneWire oneWire(DS18B20_PIN);
DallasTemperature sensors(&oneWire);
DeviceAddress insideThermometer; // Array to hold device address
MFRC522 rfid(RFID_SS, RFID_RST);
Servo myServo;
SoftwareSerial dfSerial(DF_RX, DF_TX);
DFRobotDFPlayerMini myDFPlayer;

// ================= GLOBALS =================
float tempC = 0;
int hum = 0;
float targetTemp = 25.0;  // Default target temperature
bool targetTempSet = false;

bool doorOpen = false;
bool lastDoorState = false;

// State tracking flags
bool alarmActive = false;
bool solenoidUnlocked = false;
bool waitingForDoorOpen = false;
bool doorWasOpened = false;
bool medTakenSent = false;  // CRITICAL: Prevents multiple MED_TAKEN sends
bool manualTriggerActive = false; // Flag for dashboard manual triggers (dispense-now)
bool rfidTriggerActive = false;   // Flag for RFID manual triggers

unsigned long alarmStartTime = 0;
unsigned long lastUpdate = 0;

// ================= SETUP =================
void setup() {
  Serial.begin(115200);
  delay(2000);

  Serial1.begin(115200);

  pinMode(DOOR_PIN, INPUT_PULLUP);
  pinMode(SOLENOID_PIN, OUTPUT);
  pinMode(COOLING_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(STEP_PIN, OUTPUT);
  pinMode(DIR_PIN, OUTPUT);

  // Ensure all outputs start LOW
  digitalWrite(SOLENOID_PIN, LOW);
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(COOLING_PIN, LOW);

  dht.begin();
  
  // Initialize DS18B20
  sensors.begin();
  if (!sensors.getAddress(insideThermometer, 0)) {
    Serial.println(F("Unable to find address for DS18B20 Device 0"));
  }
  sensors.setResolution(insideThermometer, 9); // Set resolution to 9 bit for speed/accuracy

  Wire.begin();
  rtc.begin();

  SPI.begin();
  rfid.PCD_Init();

  myServo.attach(SERVO_PIN);
  myServo.write(0);

  dfSerial.begin(9600);
  if (myDFPlayer.begin(dfSerial)) {
    myDFPlayer.volume(20);
  }

  Serial.println(F("SYSTEM READY"));
}

// ================= STABLE DOOR =================
bool readDoor() {
  return (digitalRead(DOOR_PIN) == HIGH);
}

// ================= SEND (UNCHANGED LOGIC) =================
void readAndSendData() {
  // Read humidity from DHT22
  float h = dht.readHumidity();
  
  // Read temperature from DS18B20
  sensors.requestTemperatures();
  float t = sensors.getTempC(insideThermometer);

  // Validation
  if (!isnan(h)) {
    hum = (int)h;
  }
  
  if (t != DEVICE_DISCONNECTED_C) {
    tempC = t;
  } else {
    Serial.println(F("Error: Could not read DS18B20 temperature"));
  }

  doorOpen = readDoor();

  // YOUR ORIGINAL SEND FORMAT (UNCHANGED)
  Serial1.print(F("{\"t\":"));
  Serial1.print(tempC, 1);
  Serial1.print(F(",\"h\":"));
  Serial1.print(hum);
  Serial1.print(F(",\"d\":"));
  Serial1.print(doorOpen);
  Serial1.print(F(",\"a\":"));
  if (alarmActive) Serial1.print(1);
  else if (manualTriggerActive) Serial1.print(2);
  else if (rfidTriggerActive) Serial1.print(3);
  else Serial1.print(0);
  Serial1.print(F(",\"l\":"));
  Serial1.print(digitalRead(SOLENOID_PIN));
  
  // Add RTC Time Fallback for ESP32
  DateTime now = rtc.now();
  char timeBuf[20];
  sprintf(timeBuf, "%04d-%02d-%02d %02d:%02d", 
          now.year(), now.month(), now.day(), 
          now.hour(), now.minute());
  Serial1.print(F(",\"time\":\""));
  Serial1.print(timeBuf);
  Serial1.print(F("\"}"));
  Serial1.println();
}

// ================= ALARM FUNCTIONS =================
void startAlarm() {
  Serial.println("DEBUG: startAlarm() called");
  
  // Complete reset of all states before starting new alarm
  resetAllStates();
  
  // Now set new alarm
  alarmActive = true;
  solenoidUnlocked = false; // Will be set to true by SOL:UNLOCK command later
  waitingForDoorOpen = true;
  doorWasOpened = false;
  medTakenSent = false;
  
  alarmStartTime = millis();
  
  // NOTE: Solenoid unlock moved to SOL:UNLOCK command handler for staggered sequence
  Serial.println("DEBUG: Alarm started (Buzzer only)");
}

void stopAlarm() {
  Serial.println("DEBUG: stopAlarm() called");
  resetAllStates();
}

void resetAllStates() {
  alarmActive = false;
  solenoidUnlocked = false;
  manualTriggerActive = false;
  rfidTriggerActive = false;
  waitingForDoorOpen = false;
  doorWasOpened = false;
  
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(SOLENOID_PIN, LOW);
  
  // myDFPlayer.stop();
  
  Serial.println("DEBUG: All states reset");
}

// ================= RFID =================
void checkRFID() {
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial())
    return;

  Serial.println("DEBUG: RFID detected");

  // If alarm is active, treat RFID as medicine taken
  if (alarmActive) {
    if (!medTakenSent) {
      Serial1.println(F("MED_TAKEN"));
      medTakenSent = true;
      Serial.println("DEBUG: RFID MED_TAKEN sent");
    }
    stopAlarm();
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
    return;
  }

  // Manual RFID unlock (no alarm)
  digitalWrite(SOLENOID_PIN, HIGH);
  solenoidUnlocked = true;
  rfidTriggerActive = true;
  waitingForDoorOpen = true;
  doorWasOpened = false;
  medTakenSent = false;
  
  // Provide short beep for feedback
  digitalWrite(BUZZER_PIN, HIGH);
  delay(200);
  digitalWrite(BUZZER_PIN, LOW);
  
  Serial.println("DEBUG: Manual RFID unlock with beep");
  readAndSendData();

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
}

// ================= DOOR MONITORING =================
void monitorDoor() {
  doorOpen = readDoor();
  
  // Detect door state changes
  if (doorOpen != lastDoorState) {
    Serial.print("DEBUG: Door changed to: ");
    Serial.println(doorOpen ? "OPEN" : "CLOSED");
    
    // Door opened
    if (doorOpen) {
      if (solenoidUnlocked && waitingForDoorOpen) {
        doorWasOpened = true;
        waitingForDoorOpen = false;
        digitalWrite(BUZZER_PIN, LOW);  // Stop buzzer when door opens
        Serial.println("DEBUG: Door opened after unlock");
      }
    }
    // Door closed
    else {
      if (solenoidUnlocked && doorWasOpened) {
        // Medicine taken - close everything
        Serial.println("DEBUG: Door closed");
        
        // Send MED_TAKEN if door was opened and closed after an alarm OR manual dashboard trigger
        // This avoids sending MED_TAKEN for generic unlocks (like RFID/DEBUG) that aren't tied to medicine
        if ((alarmActive || manualTriggerActive) && !medTakenSent) {
          Serial1.println(F("MED_TAKEN"));
          medTakenSent = true;
          Serial.println("DEBUG: MED_TAKEN sent");
        }
        
        // Complete reset
        resetAllStates();
        readAndSendData();
      }
    }
    
    // Send updated data immediately on door change
    readAndSendData();
    lastDoorState = doorOpen;
  }
}

// ================= COMMANDS =================
void checkIncomingCommands() {
  if (!Serial1.available()) return;

  String cmd = Serial1.readStringUntil('\n');
  cmd.trim();
  
  if (cmd.length() == 0) return;
  
  Serial.print("DEBUG: Received: '");
  Serial.print(cmd);
  Serial.println("'");

  if (cmd.indexOf("req_data") >= 0) {
    readAndSendData();
  }
  else if (cmd.indexOf("BUZZ:ON") >= 0 || cmd.indexOf("trigger_alarm") >= 0) {
    startAlarm();
  }
  else if (cmd.indexOf("BUZZ:OFF") >= 0 || cmd.indexOf("stop_alarm") >= 0) {
    stopAlarm();
  }
  else if (cmd.indexOf("SOL:UNLOCK") >= 0) {
    digitalWrite(SOLENOID_PIN, HIGH);
    solenoidUnlocked = true;
    waitingForDoorOpen = true;
    doorWasOpened = false;
    medTakenSent = false;
    
    if (!alarmActive) {
      manualTriggerActive = true;
      Serial.println("DEBUG: Manual SOL:UNLOCK");
    } else {
      Serial.println("DEBUG: Scheduled SOL:UNLOCK (during alarm)");
    }
    readAndSendData();
  }
  else if (cmd.indexOf("SOL:LOCK") >= 0) {
    resetAllStates();
  }
  else if (cmd.indexOf("TEMP_SET|") >= 0) {
    targetTemp = cmd.substring(9).toFloat();
    targetTempSet = true;
    Serial.print("DEBUG: Target Temp Set: ");
    Serial.println(targetTemp);
  }
  else if (cmd.startsWith("SET_TIME|")) {
    // Format: SET_TIME|YYYY|MM|DD|HH|MM|SS
    int p1 = cmd.indexOf('|');
    int p2 = cmd.indexOf('|', p1+1);
    int p3 = cmd.indexOf('|', p2+1);
    int p4 = cmd.indexOf('|', p3+1);
    int p5 = cmd.indexOf('|', p4+1);
    int p6 = cmd.indexOf('|', p5+1);
    
    if (p6 != -1) {
      int y = cmd.substring(p1+1, p2).toInt();
      int m = cmd.substring(p2+1, p3).toInt();
      int d = cmd.substring(p3+1, p4).toInt();
      int hh = cmd.substring(p4+1, p5).toInt();
      int mm = cmd.substring(p5+1, p6).toInt();
      int ss = cmd.substring(p6+1).toInt();
      
      rtc.adjust(DateTime(y, m, d, hh, mm, ss));
      Serial.print(F("DEBUG: RTC Time Set: "));
      Serial.println(cmd.substring(p1+1));
    }
  }
}

// ================= LOOP =================
void loop() {
  unsigned long now = millis();

  // Monitor door state changes
  monitorDoor();

  // Alarm timeout logic
  if (alarmActive && (now - alarmStartTime > 60000)) {
    Serial.println("DEBUG: Alarm timeout");
    stopAlarm();
  }

  // Buzzer logic when alarm active
  if (alarmActive && waitingForDoorOpen) {
    // Beep until door opens
    bool beep = (now / 500) % 2;
    digitalWrite(BUZZER_PIN, beep);
  }
  else if (!alarmActive) {
    digitalWrite(BUZZER_PIN, LOW);
  }

  // Check RFID
  checkRFID();

  // Check ESP commands
  checkIncomingCommands();

  // Cooling logic based on target temperature with +-0.5 breath point
  if (targetTempSet) {
    if (tempC >= targetTemp + 0.5) {
      digitalWrite(COOLING_PIN, HIGH);
    } 
    else if (tempC <= targetTemp - 0.5) {
      digitalWrite(COOLING_PIN, LOW);
    }
  }

  // Periodic data send
  if (now - lastUpdate > 60000) {
    readAndSendData();
    lastUpdate = now;
  }
}