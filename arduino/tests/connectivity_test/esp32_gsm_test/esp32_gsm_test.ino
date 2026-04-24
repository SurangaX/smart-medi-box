
/*
 * ESP32 + ST7920 LCD (128x64) Test Code
 * Library: U8g2 (Download from Library Manager)
 * Connection: SPI Mode
 */

#include <Arduino.h>
#include <U8g2lib.h>
#include <SPI.h>

// ST7920 SPI setup: 
// CLK = 18, DATA = 23, CS = 5
U8G2_ST7920_128X64_F_SW_SPI u8g2(U8G2_R0, /* clock=*/ 18, /* data=*/ 23, /* cs=*/ 5, /* reset=*/ 22);

void setup(void) {
  u8g2.begin();
}

void loop(void) {
  u8g2.clearBuffer();					// clear the internal memory
  u8g2.setFont(u8g2_font_ncenB08_tr);	// choose a suitable font
  u8g2.drawStr(0,10,"ESP32-32U Test");	// write something to the internal memory
  u8g2.drawFrame(0, 15, 128, 49);        // draw a simple frame
  u8g2.drawCircle(64, 40, 15);           // draw a circle in the middle
  u8g2.sendBuffer();					// transfer internal memory to the display
  delay(1000);  
}
