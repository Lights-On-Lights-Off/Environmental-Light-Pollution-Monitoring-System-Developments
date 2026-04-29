/**
 * ELPMS - ESP32 Light Sensor
 * BH1750 → ESP32 → ics-dev.io/hayag/api/iot_readings.php
 *
 * Wiring:
 * BH1750  ->  ESP32
 * VCC     ->  3.3V
 * GND     ->  GND
 * SDA     ->  GPIO 21
 * SCL     ->  GPIO 22
 * ADDR    ->  GND
 */

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <BH1750.h>

// ─── WiFi Configuration ───────────────────────────────────────────────────────
const char* ssid     = "Wifi name here";      // Must be 2.4GHz
const char* password = "Wifi password here";

// ─── Server Configuration ─────────────────────────────────────────────────────
const char* serverUrl = "https://ics-dev.io/hayag/api/iot_readings.php";

// ─── Device Configuration ─────────────────────────────────────────────────────
const char* deviceId   = "ESP32_ICS_LAB";
const int   buildingId = 6;               // ICS Laboratory
const char* iotToken   = "elpms_iot_2026";

// ─── Timing ───────────────────────────────────────────────────────────────────
const unsigned long READING_INTERVAL    = 5000;   // 5 seconds
const unsigned long WIFI_RETRY_INTERVAL = 10000;  // 10 seconds
const int           MAX_WIFI_RETRIES    = 5;

// ─── Pin ──────────────────────────────────────────────────────────────────────
#define STATUS_LED 2

// ─── Globals ──────────────────────────────────────────────────────────────────
BH1750 lightMeter;

unsigned long lastReadingTime   = 0;
unsigned long lastWifiRetryTime = 0;
int           wifiRetryCount    = 0;
bool          sensorReady       = false;

// =============================================================================
void setup() {
    Serial.begin(115200);
    Serial.println("\n=== ELPMS IoT Sensor ===");

    pinMode(STATUS_LED, OUTPUT);
    blinkLED(3, 200);

    Wire.begin(21, 22);

    if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
        sensorReady = true;
        Serial.println("BH1750 ready");
    } else {
        Serial.println("ERROR: BH1750 not found — check wiring");
    }

    connectWiFi();
}

// =============================================================================
void loop() {
    if (WiFi.status() != WL_CONNECTED) {
        handleWiFiDisconnect();
    }

    if (millis() - lastReadingTime >= READING_INTERVAL) {
        lastReadingTime = millis();

        if (!sensorReady) {
            Serial.println("Sensor not ready — skipping");
            // Retry sensor init
            if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
                sensorReady = true;
                Serial.println("Sensor recovered");
            }
            return;
        }

        float lux = lightMeter.readLightLevel();

        if (lux < 0 || isnan(lux) || isinf(lux) || lux > 100000) {
            Serial.print("Invalid reading: ");
            Serial.println(lux);
            return;
        }

        Serial.print("Lux: ");
        Serial.println(lux, 2);

        if (WiFi.status() == WL_CONNECTED) {
            sendReading(lux);
        } else {
            Serial.println("WiFi not connected — skipping send");
        }
    }

    delay(100);
}

// =============================================================================
void connectWiFi() {
    Serial.print("Connecting to WiFi: ");
    Serial.println(ssid);
    WiFi.begin(ssid, password);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        wifiRetryCount = 0;
        Serial.println("\nWiFi connected: " + WiFi.localIP().toString());
        digitalWrite(STATUS_LED, HIGH);
    } else {
        Serial.println("\nWiFi failed");
        digitalWrite(STATUS_LED, LOW);
    }
}

// =============================================================================
void handleWiFiDisconnect() {
    unsigned long now = millis();
    if (now - lastWifiRetryTime >= WIFI_RETRY_INTERVAL) {
        lastWifiRetryTime = now;
        wifiRetryCount++;
        if (wifiRetryCount <= MAX_WIFI_RETRIES) {
            Serial.println("WiFi retry: " + String(wifiRetryCount));
            connectWiFi();
        } else {
            Serial.println("Max retries reached — restarting");
            delay(3000);
            ESP.restart();
        }
    }
}

// =============================================================================
void sendReading(float lux) {
    WiFiClientSecure client;
    client.setInsecure();
    HTTPClient http;

    if (!http.begin(client, serverUrl)) {
        Serial.println("HTTP begin failed");
        return;
    }

    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("User-Agent", "ESP32-ELPMS/1.0");
    http.setTimeout(10000);

    String postData = "token="       + String(iotToken)   +
                      "&device_id="  + String(deviceId)   +
                      "&building_id="+ String(buildingId) +
                      "&lux="        + String(lux, 2);

    int code = http.POST(postData);

    Serial.print("HTTP: ");
    Serial.print(code);
    Serial.print(" | ");
    Serial.println(code == 200 ? "SUCCESS" : "FAILED");

    if (code == 200) {
        Serial.println(http.getString());
        blinkLED(2, 100);
    } else {
        blinkLED(5, 50);
    }

    http.end();
}

// =============================================================================
void blinkLED(int count, int delayMs) {
    for (int i = 0; i < count; i++) {
        digitalWrite(STATUS_LED, HIGH);
        delay(delayMs);
        digitalWrite(STATUS_LED, LOW);
        delay(delayMs);
    }
}
