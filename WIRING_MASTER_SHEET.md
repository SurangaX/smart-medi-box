# Smart Medi Box - Wiring Guide (Professional Edition)

**Version: 2.1 (Corrected & Optimized)**
**Status: Ready for Build ✅**

---

## 1. SYSTEM OVERVIEW

The Smart Medi Box system is powered by a 12V DC power supply, which is divided into two main rails:

### 12V Rail (Direct)
- Solenoid Lock
- (Optional) Cooling System

### 5V-6V Rail (via buck converter)
- Servo Motor

### 5V Rail (via voltage regulator)
- Arduino Leonardo
- SIM800L EVB
- Sensors (DHT22, DS18B20, RTC)
- LCD Display
- RFID Module (via 3.3V pin)

**⚠️ CRITICAL RULE:**
All components must share a common ground (GND).

---

## 2. POWER SYSTEM DESIGN

### Input Supply
- 12V / 5A DC Power Supply (recommended)

### Voltage Regulation
**Recommended:** Buck Converter (LM2596)
**Alternative:** LM7805 (with heatsink)

### Capacitors (Essential)
- Input: 100µF electrolytic
- Output: 10µF electrolytic + 100nF ceramic
- SIM800L: 1000µF near VCC

### Important Note
Linear regulators (LM7805) generate heat:
- Power Loss = (12V − 5V) × Current
- Can exceed 4W (very hot)

**⬆ Use buck converter for efficiency.** ✔

---

## 3. MICROCONTROLLER (Arduino Leonardo)

### Power
- VCC → 5V
- GND → Common Ground

---

## 4. PIN CONFIGURATION

### Digital Pins

| Pin | Function |
|-----|----------|
| 0 | SIM800L RX |
| 1 | SIM800L TX |
| 2 | Door Switch |
| 3 | Cooling (MOSFET Gate) |
| 4 | Stepper STEP |
| 5 | Solenoid (MOSFET Gate) |
| 6 | RFID RST |
| 7 | Buzzer |
| 8 | LCD Reset |
| 9 | RFID CS |
| 10 | LCD RS |
| 11 | LCD RW |
| 12 | Stepper DIR |
| 13 | LCD E |

### Analog Pins

| Pin | Function |
|-----|----------|
| A0 | DHT22 Data |
| A1 | DS18B20 Data |
| A2 | Servo Signal (MG995) |

---

## 4.1 HARDWARE COMPATIBILITY CHECK

- SIM800L EVB: Requires stable 5V with high peak current. Keep 1000uF cap close to VCC/GND.
- RC522 RFID: Must use 3.3V logic. Do not connect VCC to 5V. Keep SPI lines short.
- ST7920 LCD: Uses ST7920 serial (not standard SPI). Verify PSB to GND for serial mode.
- DHT22 + DS18B20: Use 4.7K pull-up on data. Keep data lines away from motor wiring.
- Servo MG995: Use 5V-6V only. Do not power from 12V rail.
- Solenoid: Must use flyback diode across coil. MOSFET required.
- Shared GND: All modules must share a common ground reference.
- Door switch on D2 conflicts with I2C SDA. If RTC is used, move the door switch to a free pin.

---

## 4.2 POWER WIRING DIAGRAM (ASCII)

```
12V DC IN
   |
   +--------------------> Servo (5V-6V supply via buck)
   |
   +--------------------> Solenoid (12V) -> MOSFET -> GND
   |
   +--> Buck Converter (LM2596) -> 5V RAIL
	   |
	   +--> Arduino Leonardo 5V
	   +--> SIM800L EVB 5V (1000uF cap near VCC/GND)
	   +--> LCD 5V
	   +--> Sensors 5V
	   +--> Stepper Driver (5V logic + separate motor supply 6V–12V)
	   +--> RC522 3.3V (from Arduino 3.3V pin)

ALL GROUNDS TIED TO COMMON GND
```

---

## 4.3 ARDUINO LEONARDO FULL PINOUT (REFERENCE)

### Digital Pins

| Pin | Function | Notes |
|-----|----------|-------|
| 0 | RX | UART RX (Serial1) |
| 1 | TX | UART TX (Serial1) |
| 2 | SDA | I2C SDA |
| 3 | SCL | I2C SCL |
| 4 | GPIO | Digital I/O |
| 5 | PWM | Digital I/O, PWM |
| 6 | PWM | Digital I/O, PWM |
| 7 | GPIO | Digital I/O |
| 8 | GPIO | Digital I/O |
| 9 | PWM | Digital I/O, PWM |
| 10 | PWM | Digital I/O, PWM |
| 11 | PWM | Digital I/O, PWM |
| 12 | GPIO | Digital I/O |
| 13 | LED | Digital I/O, onboard LED |

### Analog Pins

| Pin | Function | Notes |
|-----|----------|-------|
| A0 | Analog In | ADC0 |
| A1 | Analog In | ADC1 |
| A2 | Analog In | ADC2 |
| A3 | Analog In | ADC3 |
| A4 | Analog In | ADC4 |
| A5 | Analog In | ADC5 |

### Power Pins

| Pin | Function |
|-----|----------|
| VIN | External input (7V-12V) |
| 5V | Regulated 5V output |
| 3.3V | 3.3V output (for RC522) |
| GND | Ground |
| RESET | Reset input |

### SPI Pins

- **IMPORTANT (Arduino Leonardo):** Use ICSP header for SPI (MOSI, MISO, SCK).
- D11–D13 are not used for SPI on Leonardo. Use ICSP for RC522.

### USB Serial

- USB serial is separate from pins 0/1 on Leonardo.

---

## 5. SIM800L EVB (GSM Module)

### Connections (4 wires only)

| Pin | Connection |
|-----|-----------|
| VCC | 5V |
| GND | GND |
| TX | Arduino Pin 0 (RX) |
| RX | Arduino Pin 1 (TX) |

**Note:** TX/RX must be crossed (TX -> RX, RX -> TX).

### Requirements
- SIM card (correct orientation)
- Antenna connected
- 1000µF capacitor for stability

**⚠️ Important:**
SIM800L draws up to 2A peak current. Ensure:
- Thick power wires
- Short connection to power source
- 1000µF–2200µF capacitor near module
- Do NOT power from Arduino 5V pin

---

## 6. LCD DISPLAY (12864B V2.3 – ST7920)

### LCD Type & Mode
- **Model:** ST7920 128×64
- **Mode:** Serial (SPI-like mode) when PSB = GND
- **⚠️ IMPORTANT:** PSB must be GND for serial mode. If PSB = 5V, it switches to parallel mode and U8g2 wiring/constructor must change.

### Connections

| LCD Pin | Function | Arduino Pin |
|---------|----------|-------------|
| PSB | Mode Select | GND (Serial mode) |
| VCC | Power | 5V |
| GND | Ground | GND |
| RS | Register Select | Pin 10 |
| RW | Read/Write | Pin 11 |
| E | Enable | Pin 13 |
| RST | Reset | Pin 8 |

### Backlight
- **BLA** (Backlight Anode, +) → 5V
- **BLK** (Backlight Cathode, −) → GND

### Library & Constructor
**Library:** U8g2

**U8g2 Constructor (Working - Serial/SPI Mode):**

```cpp
#include <U8g2lib.h>
U8G2_ST7920_128X64_F_SW_SPI u8g2(U8G2_R0, 13, 11, 10, 8);
// Parameters: (rotation, clock=Pin13(E), data=Pin11(RW), cs=Pin10(RS), reset=Pin8(RST))
```

**⚠️ Mode Selection Notes:**
- Uses ST7920 serial protocol (not standard SPI)
- PSB = GND → Serial mode (this config)
- PSB = 5V → Parallel mode (requires different constructor & wiring)

---

## 7. SENSORS

### DHT22 (Temperature & Humidity)
**Required Libraries:**
- DHT sensor library
- OneWire
- DallasTemperature

**Wiring:**
- VCC → 5V
- GND → GND
- DATA → A0
- **4.7K pull-up resistor required** between DATA and 5V (or 3.3V)

### DS18B20 (Temperature)
**Wiring:**
- VCC → 5V
- GND → GND
- DATA → A1
- **4.7K pull-up resistor required** between DATA and 5V (or 3.3V)

### RTC DS3231 (I2C)
**Required Library:** RTClib by Adafruit

**DS3231 Wiring on Leonardo:**
- Use the **SDA/SCL labeled pins** (near AREF), NOT D2/D3
- DS3231 SDA → SDA header pin
- DS3231 SCL → SCL header pin
- VCC → 5V
- GND → GND

**⚠️ Important Note:** On Leonardo, use the dedicated SDA/SCL header pins (near AREF), not the D2/D3 digital pins. This avoids conflicts with the door switch on D2.

### RTC Time Set (Compile Time)

```cpp
#include <Wire.h>
#include <RTClib.h>

RTC_DS3231 rtc;

void setup() {
	Serial.begin(9600);
	delay(1000);

	if (!rtc.begin()) {
		Serial.println("Couldn't find DS3231 (check SDA/SCL pins).");
		while (1) {}
	}

	Serial.println("RTC will be set to COMPILE TIME after countdown.");
	for (int i = 10; i >= 1; i--) {
		Serial.print("Setting in ");
		Serial.println(i);
		delay(1000);
	}

	rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
	Serial.println("Done. RTC set.");
}

void loop() {}
```

---

## 8. RFID MODULE (RC522)

### Connections (ICSP Header)

**RC522 Pin → Arduino Pin/Header:**
- RC522 MOSI → ICSP MOSI
- RC522 MISO → ICSP MISO
- RC522 SCK → ICSP SCK
- RC522 VCC → **3.3V** (NOT 5V)
- RC522 GND → GND
- RC522 SDA/SS (CS) → Pin 9
- RC522 RST → Pin 6

**⚠️ CRITICAL NOTES:**
- RC522 operates at 3.3V logic level. Do NOT connect VCC to 5V.
- Use ICSP header for SPI (not digital pins D11-D13 on Leonardo).
- ICSP pinout diagram is attached in the build pack.
- Consider using a logic-level converter or resistor dividers for MOSI, SCK, and CS lines if power supply is not stable.

---

## 9. MOTORS AND ACTUATORS

### Stepper Motor (EasyDriver HW-135)
- STEP → Pin 4
- DIR → Pin 12
- VCC → 5V (logic)
- M+ → 6V–12V (motor supply)
- GND → GND

### Servo Motor (MG995)
- Signal → A2 (digital pin 20)
- VCC → 5V–6V (NOT 12V) ⚠️
- GND → GND

**✔ Use external 5V supply if needed.**

### Solenoid Lock (12V)
- Positive → 12V
- Negative → MOSFET Drain

### MOSFET (N-Channel)
- Gate → Pin 5 (via 1K resistor)
- Drain → Solenoid
- Source → GND

### Protection
- Diode (1N4007) across solenoid

### Buzzer (TMB12A05 + BC337 Driver)
**Buzzer Type:** Active electromagnetic buzzer

**Wiring:**
- Arduino D7 → 1K resistor → BC337 BASE
- BC337 EMITTER → GND
- BC337 COLLECTOR → Buzzer (-)
- Buzzer (+) → +5V

**Protection Diode (Recommended):**
- Use 1N4148 or 1N4007 across the buzzer coil
- Diode cathode (stripe) → +5V (buzzer +)
- Diode anode → Buzzer (-) / BC337 collector

**Common Ground:**
- Arduino GND must be connected to the buzzer supply GND

**Operation:**
- This is an ACTIVE buzzer: it makes sound with DC voltage
- Use `digitalWrite(D7, HIGH)` to turn ON
- Use `digitalWrite(D7, LOW)` to turn OFF

### Cooling System (Pin D3) – MOSFET Low-Side Switch

**Supported Loads:**
- DC fan (2-wire)
- Peltier / TEC module (thermoelectric cooler)
- Any DC load within MOSFET + power supply limits

**Wiring (LOW-SIDE MOSFET SWITCH):**
- Arduino D3 → 1K resistor → MOSFET GATE
- MOSFET SOURCE → GND (power supply negative)
- MOSFET DRAIN → Load (-) (fan- or TEC-)
- Load (+) → +V supply (12V typical for fan/TEC)

**CRITICAL REQUIREMENTS:**

1. **Common Ground Required:**
   - Arduino GND must be connected to the load power supply GND

2. **Gate Pulldown (Recommended):**
   - Add 100K (or 10K) resistor from MOSFET GATE to GND
   - Prevents floating gate (random ON or slow spinning when "OFF")

3. **Flyback Diode:**
   - **REQUIRED for inductive loads** (DC fan, motor, relay coil)
   - Diode cathode (stripe) → +V, diode anode → MOSFET drain/load(-)
   - **NOT used for TEC/Peltier** (not inductive)

4. **Peltier (TEC) WARNING:**
   - TEC modules draw **HIGH current** (often 3A to 10A+)
   - Do NOT power a TEC from Arduino 5V pin
   - Use a separate PSU sized for the TEC
   - Use a logic-level MOSFET with low Rds(on) and heatsink, or a proper driver module
   - Consider adding a fuse

5. **PWM Control:**
   - D3 supports PWM for speed/power control
   - For fans: PWM adjusts speed
   - For TEC: PWM can work but may reduce efficiency and cause thermal cycling
   - For stable temperature control, consider a thermostat/PID approach

### Door Switch (Pin 2)
```cpp
const int DOOR_PIN = 2;

void setup() {
	Serial.begin(9600);
	pinMode(DOOR_PIN, INPUT_PULLUP);
}

void loop() {
	int state = digitalRead(DOOR_PIN);
	if (state == LOW) {
		Serial.println("Door CLOSED (switch closed to GND)");
	} else {
		Serial.println("Door OPEN (switch open)");
	}
	delay(100);
}
```

---

## 10. POWER BUDGET

### 5V Rail
- Approx: 350mA typical
- Peak: ~600mA

### 12V Rail
- Servo + Solenoid: up to ~1.8A

### SIM800L
- Peak bursts: ~2A

**✔ Power supply: 12V / 5A recommended (to handle SIM800L + servo peaks)**

---

### Protection (Recommended)
- Add inline fuse (2A–5A) on 12V input
- Add reverse polarity protection diode

---

## 11. KEY DESIGN RULES

✔ All grounds must be connected together
✔ Use 3.3V for RFID (RC522)
✔ Servo must use 5–6V only (NOT 12V)
✔ Add capacitors for stability
✔ Avoid pin conflicts
✔ Use short wires for communication lines
✔ Linear regulators generate excessive heat (use buck converter)

---

## 12. FIRST POWER-ON PROCEDURE

1. Verify 5V and 12V using multimeter
2. Power Arduino only → test code
3. Connect SIM800L → test AT command
4. Add sensors one by one
5. Test actuators last

---

## 13. TROUBLESHOOTING ESSENTIALS

### No GSM Response
- [ ] Check TX/RX wiring
- [ ] Check power stability
- [ ] Verify baud rate (115200)

### Sensor Failure
- [ ] Check pull-up resistors
- [ ] Verify pin connections

### Motor Issues
- [ ] Check driver wiring
- [ ] Verify control signals

### System Resets
- [ ] Add bulk capacitor (1000µF)
- [ ] Improve grounding
