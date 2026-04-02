# Smart Medi Box - Wiring Guide (Professional Edition)

**Version: 2.1 (Corrected & Optimized)**
**Status: Ready for Build ✅**

---

## 1. SYSTEM OVERVIEW

The Smart Medi Box system is powered by a 12V DC power supply, which is divided into two main rails:

### 12V Rail (Direct)
- Servo Motor
- Solenoid Lock
- (Optional) Cooling System

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
- 12V / 3A DC Power Supply

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
| 2 | I2C SDA (RTC) |
| 3 | I2C SCL (RTC) |
| 4 | Stepper STEP |
| 5 | Servo Signal |
| 6 | Solenoid (MOSFET Gate) |
| 7 | Buzzer |
| 8 | LCD Reset |
| 9 | RFID CS |
| 10 | LCD CS |
| 11 | MOSI (SPI shared) |
| 12 | MISO / Stepper DIR |
| 13 | SCK (SPI shared) |

### Analog Pins

| Pin | Function |
|-----|----------|
| A0 | DHT22 Data |
| A1 | DS18B20 Data |

---

## 5. SIM800L EVB (GSM Module)

### Connections (4 wires only)

| Pin | Connection |
|-----|-----------|
| VCC | 5V |
| GND | GND |
| TX | Arduino Pin 0 (RX) |
| RX | Arduino Pin 1 (TX) |

### Requirements
- SIM card (correct orientation)
- Antenna connected
- 1000µF capacitor for stability

---

## 6. LCD DISPLAY (12864B V2.3 – ST7920)

### Mode
Serial (SPI-like mode)

### Connections

| LCD Pin | Function | Arduino Pin |
|---------|----------|-------------|
| PSB | Ground | GND |
| VCC | Power | 5V |
| GND | Ground | GND |
| RS | Register Select | Pin 10 |
| RW | Read/Write | Pin 11 |
| E | Enable | Pin 13 |
| RST | Reset | Pin 8 |

**⚠️ Not standard SPI — uses ST7920 protocol.**

---

## 7. SENSORS

### DHT22 (Temperature & Humidity)
- VCC → 5V
- GND → GND
- DATA → A0
- 4.7K pull-up resistor required

### DS18B20 (Temperature)
- VCC → 5V
- GND → GND
- DATA → A1
- 4.7K pull-up resistor required

### RTC DS3231 (I2C)
- VCC → 5V
- GND → GND
- SDA → Pin 2
- SCL → Pin 3

---

## 8. RFID MODULE (RC522)

### Connections

| RC522 Pin | Connection |
|-----------|-----------|
| VCC | 3.3V ⚠️ (NOT 5V) |
| GND | GND |
| MOSI | Pin 11 |
| MISO | Pin 12 |
| SCK | Pin 13 |
| CS | Pin 9 |

**⚠️ IMPORTANT: Use 3.3V for RFID (RC522)**

---

## 9. MOTORS AND ACTUATORS

### Stepper Motor (via Driver)
- STEP → Pin 4
- DIR → Pin 12
- Power → 5V
- GND → GND

### Servo Motor (MG995)
- Signal → Pin 5
- VCC → 5V–6V (NOT 12V) ⚠️
- GND → GND

**✔ Use external 5V supply if needed.**

### Solenoid Lock (12V)
- Positive → 12V
- Negative → MOSFET Drain

### MOSFET (N-Channel)
- Gate → Pin 6 (via 1K resistor)
- Drain → Solenoid
- Source → GND

### Protection
- Diode (1N4007) across solenoid

### Buzzer
- Controlled via BC337 transistor
- Base → Pin 7 (1K resistor)
- Collector → Buzzer
- Emitter → GND

---

## 10. POWER BUDGET

### 5V Rail
- Approx: 350mA typical
- Peak: ~600mA

### 12V Rail
- Servo + Solenoid: up to ~1.8A

### SIM800L
- Peak bursts: ~2A

**✔ Power supply: 12V / 3A minimum**

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
