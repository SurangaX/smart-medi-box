/*
  ============================================================================
  SMART MEDI BOX - Leonardo (STABLE SENSORS & HARDWARE SYNC)
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

// Pins from Working Notes
#define DOOR_PIN     8   // Moved from 2 to avoid I2C conflict
#define COOLING_PIN  13  // Moved from 3 to avoid I2C conflict
#define STEP_PIN     4
#define SOLENOID_PIN 5
#define RFID_RST     6
#define BUZZER_PIN   7
#define RFID_SS      9
#define DIR_PIN      12
#define DHT_PIN      A0
#define DS18B20_PIN  A1
#define SERVO_PIN    A2
#define DF_RX        A3 // Leonardo receives from DFPlayer TX
#define DF_TX        A4 // Leonardo sends to DFPlayer RX

// Objects
RTC_DS3231 rtc;
DHT dht(DHT_PIN, DHT22);
OneWire oneWire(DS18B20_PIN);
DallasTemperature sensors(&oneWire);
MFRC522 rfid(RFID_SS, RFID_RST);
Servo myServo;
SoftwareSerial dfSerial(DF_RX, DF_TX); // RX, TX
DFRobotDFPlayerMini myDFPlayer;

// Globals
float tempC = 0;
int hum = 0;
bool doorOpen = false;
bool alarmActive = false;
unsigned long alarmStartTime = 0;
unsigned long lastUpdate = 0;
bool rtcOk = false;

void setup() {
  // Serial for Debugging
  Serial.begin(115200);
  delay(2000); // Give time for Serial Monitor to open
  Serial.println(F("--- LEONARDO STARTUP ---"));
  
  // Serial1 for ESP32 Communication (Pins 0 & 1)
  Serial1.begin(9600);
  Serial.println(F("ESP32 Serial (Serial1) Init: OK"));
  
  // Hardware Pins
  pinMode(DOOR_PIN, INPUT_PULLUP);
  pinMode(SOLENOID_PIN, OUTPUT);
  pinMode(COOLING_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(STEP_PIN, OUTPUT);
  pinMode(DIR_PIN, OUTPUT);
  Serial.println(F("Digital Pins (8, 13 for Door/Cool) Init: OK"));

  // Initialize Sensors
  Serial.println(F("Starting DHT22 (Pin A0)..."));
  dht.begin();
  Serial.println(F("DHT22 Init: OK"));

  Serial.println(F("Starting DS18B20 (Pin A1)..."));
  sensors.begin();
  Serial.println(F("DS18B20 Init: OK"));

  delay(1000); 

  // RE-ENABLING I2C/RTC after Pin Conflict Fix
  Serial.println(F("Starting I2C (Wire)..."));
  Wire.begin();
  Wire.setClock(100000);
  Serial.println(F("I2C Init: OK. Testing RTC..."));
  if (rtc.begin()) {
    rtcOk = true;
    Serial.println(F("RTC Init: SUCCESS"));
  } else {
    Serial.println(F("RTC Init: NOT FOUND"));
  }
  
  // Initialize RFID
  SPI.begin();
  rfid.PCD_Init();
  Serial.println(F("RFID Init: OK"));

  // Initialize Servo
  myServo.attach(SERVO_PIN);
  myServo.write(0);
  Serial.println(F("Servo Init: OK (Pin A2)"));

  // Initialize DFPlayer
  dfSerial.begin(9600);
  if (myDFPlayer.begin(dfSerial)) {
    myDFPlayer.volume(20);
    Serial.println(F("DFPlayer Init: OK (Pins A3/A4)"));
  } else {
    Serial.println(F("DFPlayer Init: FAILED"));
  }
  Serial.println(F("--- SETUP COMPLETE ---"));
  Serial.println(F("Type 'force_read' for sensors or 'test_all' for hardware test."));
}

void loop() {
  unsigned long now = millis();
  
  // 1. Pulsing Alarm Logic (Beep effect)
  if (alarmActive) {
    // Auto-stop after 1 minute
    if (now - alarmStartTime > 60000) {
      Serial.println(F("Alarm timeout (1min)"));
      stopAlarm();
    } 
    // Stop if door is opened
    else if (doorOpen) {
      Serial.println(F("Door opened - Stopping alarm"));
      stopAlarm();
    }
    // Pulsing beep (500ms on, 500ms off)
    else {
      bool beepState = (now / 500) % 2;
      digitalWrite(BUZZER_PIN, beepState);
    }
  }

  // 2. Handle USB Serial Commands (Debug)
  if (Serial.available()) {
    String usbCmd = Serial.readStringUntil('\n');
    usbCmd.trim();
    if (usbCmd == "force_read") {
      Serial.println(F("Manual Trigger Received..."));
      readAndSendData();
    } else if (usbCmd == "test_all") {
      runHardwareTest();
    } else if (usbCmd == "test_rfid") {
      runRFIDTest();
    }
  }

  // 3. Send Data to ESP32 every 1 minute (Heartbeat) or when requested
  if (now - lastUpdate > 60000) {
    readAndSendData();
    lastUpdate = now;
  }

  // 4. Handle RFID
  checkRFID();

  // 5. Process Commands from ESP32
  checkIncomingCommands();
}

void readAndSendData() {
  Serial.println(F("Performing sensor read..."));
  // Read DHT22
  float h = dht.readHumidity();
  float t = dht.readTemperature();
  
  // Read DS18B20
  sensors.requestTemperatures();
  float t2 = sensors.getTempCByIndex(0);

  // Check if any reads failed
  if (isnan(h) || isnan(t)) {
    Serial.println(F("!! ERROR: DHT22 Read Failed !!"));
  } else {
    tempC = t;
    hum = (int)h;
  }

  doorOpen = (digitalRead(DOOR_PIN) == LOW);

  // Debug to USB Serial
  Serial.print(F("Sensors -> DHT_T:")); Serial.print(tempC);
  Serial.print(F(" DHT_H:")); Serial.print(hum);
  Serial.print(F(" DS18_T:")); Serial.print(t2);
  Serial.print(F(" Door:")); Serial.println(doorOpen ? "Open" : "Closed");

  // Send to ESP32 via Serial1
  Serial.println(F("Forwarding data to ESP32..."));
  Serial1.print(F("{\"t\":")); Serial1.print(tempC, 1);
  Serial1.print(F(",\"h\":")); Serial1.print(hum);
  Serial1.print(F(",\"d\":")); Serial1.print(doorOpen);
  Serial1.print(F(",\"a\":")); Serial1.print(alarmActive);
  Serial1.println('}');
}

void checkRFID() {
  if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
    if (alarmActive) { 
      stopAlarm();
      // Rotate stepper as feedback
      digitalWrite(DIR_PIN, HIGH);
      for(int i=0; i<200; i++) { 
        digitalWrite(STEP_PIN, HIGH); 
        delayMicroseconds(800); 
        digitalWrite(STEP_PIN, LOW); 
        delayMicroseconds(800); 
      }
    }
    rfid.PICC_HaltA(); 
    rfid.PCD_StopCrypto1();
  }
}

void checkIncomingCommands() {
  if (Serial1.available()) {
    String cmd = Serial1.readStringUntil('\n');
    Serial.print(F("ESP Cmd: ")); Serial.println(cmd);
    
    if (cmd.indexOf("req_data") >= 0) {
      readAndSendData();
    } else if (cmd.indexOf("trigger_alarm") >= 0 || cmd.indexOf("BUZZ:ON") >= 0) {
      startAlarm();
    } else if (cmd.indexOf("stop_alarm") >= 0 || cmd.indexOf("BUZZ:OFF") >= 0) {
      stopAlarm();
    } else if (cmd.indexOf("lock") >= 0 || cmd.indexOf("SOL:LOCK") >= 0) {
      digitalWrite(SOLENOID_PIN, LOW);
    } else if (cmd.indexOf("unlock") >= 0 || cmd.indexOf("SOL:UNLOCK") >= 0) {
      digitalWrite(SOLENOID_PIN, HIGH);
    } else if (cmd.indexOf("play:") >= 0) {
      int track = cmd.substring(5).toInt();
      myDFPlayer.play(track);
    } else if (cmd.indexOf("servo:") >= 0) {
      int pos = cmd.substring(6).toInt();
      myServo.write(pos);
    } else if (cmd.indexOf("ACT:CALIBRATE") >= 0) {
      Serial.println(F("Actuator calibration signal received (Future feature)"));
    }
  }
}

void startAlarm() {
  if (!alarmActive) {
    alarmActive = true;
    alarmStartTime = millis();
    digitalWrite(SOLENOID_PIN, HIGH); // Unlock solenoid on alarm
    myDFPlayer.play(1); 
    Serial.println(F("ALARM STARTED"));
  }
}

void stopAlarm() {
  alarmActive = false;
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(SOLENOID_PIN, LOW); // Relock
  myDFPlayer.stop();
  Serial.println(F("ALARM STOPPED"));
}

void runHardwareTest() {
  Serial.println(F("\n--- STARTING HARDWARE SELF-TEST ---"));
  
  Serial.println(F("1. Buzzer (1s)"));
  digitalWrite(BUZZER_PIN, HIGH); delay(1000); digitalWrite(BUZZER_PIN, LOW);
  
  Serial.println(F("2. Solenoid (1s)"));
  digitalWrite(SOLENOID_PIN, HIGH); delay(1000); digitalWrite(SOLENOID_PIN, LOW);
  
  Serial.println(F("3. Cooling Fan/TEC (2s)"));
  digitalWrite(COOLING_PIN, HIGH); delay(2000); digitalWrite(COOLING_PIN, LOW);
  
  Serial.println(F("4. Servo Move (0 -> 90 -> 0)"));
  myServo.write(90); delay(1000); myServo.write(0); delay(1000);
  
  Serial.println(F("5. Stepper Motor (200 steps)"));
  digitalWrite(DIR_PIN, HIGH);
  for(int i=0; i<200; i++) { 
    digitalWrite(STEP_PIN, HIGH); delayMicroseconds(1000); 
    digitalWrite(STEP_PIN, LOW); delayMicroseconds(1000); 
  }
  
  Serial.println(F("6. DFPlayer (Track 1)"));
  myDFPlayer.play(1); delay(3000); myDFPlayer.stop();
  
  Serial.println(F("7. RFID - Please scan a tag now..."));
  unsigned long start = millis();
  while (millis() - start < 5000) {
    if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
      Serial.println(F("   RFID Tag FOUND!"));
      rfid.PICC_HaltA(); rfid.PCD_StopCrypto1();
      break;
    }
  }
  
  Serial.println(F("8. Sensors Check"));
  readAndSendData();
  
  Serial.println(F("--- TEST COMPLETE ---\n"));
}

void runRFIDTest() {
  Serial.println(F("\n--- RFID MANUAL CHECK ---"));
  Serial.println(F("Scanning for 10 seconds... Please tap your tag."));
  
  unsigned long start = millis();
  bool found = false;
  
  while (millis() - start < 10000) {
    if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
      Serial.print(F("Found Tag! UID:"));
      for (byte i = 0; i < rfid.uid.size; i++) {
        Serial.print(rfid.uid.uidByte[i] < 0x10 ? " 0" : " ");
        Serial.print(rfid.uid.uidByte[i], HEX);
      }
      Serial.println();
      
      rfid.PICC_HaltA();
      rfid.PCD_StopCrypto1();
      found = true;
      break;
    }
  }
  
  if (!found) Serial.println(F("No tag detected. Check wiring (SS=9, RST=6, MOSI/MISO/SCK on ICSP)."));
  Serial.println(F("--- RFID TEST COMPLETE ---\n"));
}
