// ============================================================================
//   EV EDUCATIONAL KIT — UNIFIED MASTER ENGINE  (IoT Edition)
//   Hardware : TFT LCD 320x240 (TFT_eSPI) + XPT2046 + FastLED + ESP32 WiFi
//   IoT      : WiFi → HTTP POST → Laragon/MySQL via PHP API
// ============================================================================

#include <TFT_eSPI.h>
#include <SPI.h>
#include <FastLED.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>

TFT_eSPI tft = TFT_eSPI();

// ── WiFi & HiveMQ Cloud MQTT Configuration ─────────────────────────────────────
#include <WiFi.h>
#include <WebServer.h>
#include <AutoConnect.h>

WebServer Server;
AutoConnect Portal(Server);

const char* MQTT_HOST   = "07b25c25a5d447b5a39a2edc150c0689.s1.eu.hivemq.cloud";     // ← HiveMQ Broker Host
const int   MQTT_PORT   = 8883;                                      // Secure TLS Port
const char* MQTT_USER   = "C2DKit";                                  // Broker Username
const char* MQTT_PASS   = "weare*P1G13*";                            // Broker Password


// ── UI Theme Colors ───────────────────────────────────────────────────────────
#define CLR_BG          TFT_BLACK
#define CLR_NEON_GREEN  0x07E0
#define CLR_GREEN_DIM   0x03E0
#define CLR_ACTIVE_CYAN 0x07FF
#define CLR_DARK_GRID   0x10A2
#define CLR_TEXT_MUTED  0xBDF7
#define CLR_ORANGE_RED  0xD2A0
#define CLR_WHITE       TFT_WHITE
#define BUZZER_PIN      25

#define SCREEN_W  320
#define SCREEN_H  240

// ── LED Strip ─────────────────────────────────────────────────────────────────
#define LED_PIN_SHARED  27
#define NUM_LEDS_TOTAL  50
#define LED_TYPE        WS2812B
#define COLOR_ORDER     GRB

CRGB leds[NUM_LEDS_TOTAL];

#define NUM_LEDS_REGEN  4
CRGB regenLeds[NUM_LEDS_REGEN];

// Website green #22c55e = RGB(34,197,94)
#define TFT_KIT_GREEN  0x262B              // RGB565 uint16_t — for TFT drawing

void ledsAllClear() {
  fill_solid(leds, NUM_LEDS_TOTAL, CRGB::Black);
  fill_solid(regenLeds, NUM_LEDS_REGEN, CRGB::Black);
  FastLED.show();
}

// Light LEDs one-by-one in a direction (used by module quiz flows — do not change)
void ledsRange(int from1, int to1, CRGB colour) {
  int step = (to1 >= from1) ? 1 : -1;
  for (int i = from1 - 1; i != to1 - 1 + step; i += step) {
    if (i >= 0 && i < NUM_LEDS_TOTAL) {
      leds[i] = colour;
      FastLED.show();
      delay(200);
    }
  }
}

// ── Startup LED Sweep ─────────────────────────────────────────────────────────
void ledStartupSweep() {
  const int TAIL = 5;           // comet tail length
  const int SWEEP_MS = 18;      // ms per LED step
  uint16_t tx, ty;              // touch coordinates

  // Generate a random color base for the forward sweep (Red, Green, or Blue)
  int randColor = random(3);
  uint8_t rBase = (randColor == 0) ? 255 : 0;
  uint8_t gBase = (randColor == 1) ? 255 : 0;
  uint8_t bBase = (randColor == 2) ? 255 : 0;

  // Forward sweep: comet head moves 1 → NUM_LEDS_TOTAL
  for (int head = 0; head < NUM_LEDS_TOTAL + TAIL; head++) {
    if (tft.getTouch(&tx, &ty)) {
      noTone(BUZZER_PIN);
      ledsAllClear();
      return;
    }

    fill_solid(leds, NUM_LEDS_TOTAL, CRGB::Black);
    for (int t = 0; t < TAIL; t++) {
      int pos = head - t;
      if (pos >= 0 && pos < NUM_LEDS_TOTAL) {
        uint8_t bright = 255 >> t;          
        leds[pos] = CRGB(
          (rBase * bright) >> 8,
          (gBase * bright) >> 8,
          (bBase * bright) >> 8
        );
      }
    }
    FastLED.show();
    delay(SWEEP_MS);
  }

  // Generate a new random color for the reverse sweep
  randColor = random(3);
  rBase = (randColor == 0) ? 255 : 0;
  gBase = (randColor == 1) ? 255 : 0;
  bBase = (randColor == 2) ? 255 : 0;

  // Reverse wipe
  for (int head = NUM_LEDS_TOTAL - 1; head >= -TAIL; head--) {
    if (tft.getTouch(&tx, &ty)) {
      noTone(BUZZER_PIN);
      ledsAllClear();
      return;
    }

    fill_solid(leds, NUM_LEDS_TOTAL, CRGB::Black);
    for (int t = 0; t < TAIL; t++) {
      int pos = head + t;
      if (pos >= 0 && pos < NUM_LEDS_TOTAL) {
        uint8_t bright = 255 >> t;
        leds[pos] = CRGB(
          (rBase * bright) >> 8,
          (gBase * bright) >> 8,
          (bBase * bright) >> 8
        );
      }
    }
    FastLED.show();
    delay(SWEEP_MS);
  }

  ledsAllClear();
}

// ── State Machine ─────────────────────────────────────────────────────────────
enum AppState {
  STATE_WELCOME,
  STATE_MOD_SELECT,
  STATE_PREVIEW,
  STATE_QUIZ_PLAY,
  STATE_FEEDBACK,
  STATE_COMPLETE
};
AppState currentState = STATE_WELCOME;

int selectedModule         = 1;
int currentStep            = 0;
bool lastAnswerWasCorrect  = false;
bool module3ScreenDrawn    = false;

// ── Quiz Score Tracking ───────────────────────────────────────────────────────
int quizScore = 0;
int quizTotal = 0;

// ── Quiz Attempt Tracking ─────────────────────────────────────────────────────
char userAnswers[10];      // User's choice (first attempt) for each question
bool questionCorrect[10];  // True if first attempt was correct
bool firstAttempt[10];     // True if user hasn't attempted this question yet

// ── Blink Engine ─────────────────────────────────────────────────────────────
unsigned long lastBlink  = 0;
bool          blinkState = true;
const int     BLINK_MS   = 500;

// ── Touch Debounce ────────────────────────────────────────────────────────────
unsigned long lastTouchTime = 0;
const int     TOUCH_DELAY   = 350;

// ── WiFi / IoT State ─────────────────────────────────────────────────────────
String kitMac    = "";
int    kitUserId = 0;
bool   wifiReady = false;
bool   mqttInitialized = false;

WiFiClientSecure wifiSecureClient;
PubSubClient mqttClient(wifiSecureClient);

// ── Forward Declarations ─────────────────────────────────────────────────────
void drawFirstScreen();
void drawModuleSelectionScreen();
void drawPerfectButton(int x, int y, String num, String title, String sub);
void drawPreQuizBaseFrame(int module, String moduleTitle);
void drawStartScreen(String title, String subtitle);
void drawQuestionLayout(int qNum, String question, String a, String b, String c);
void toggleQuizPrompt(bool showText);
void drawMissionComplete(String rank, String subtext);
void playIntroSong();
void drawFrameDecorations();
void drawWifiStatus();
void postKitAnnounce();
void mqttCallback(char* topic, byte* payload, unsigned int length);
void mqttReconnect();
void publishHandshakeRequest();

void displayModule01(int startX, int startY);
void displayModule02(int startX, int startY);
void displayModule03(int startX, int startY);
void runACChargingFlow();
void runDCChargingFlow();
void runDrivingRegenFlow();
void evaluateACQuestion(char choice);
void evaluateDCQuestion(char choice);
void evaluateRegenQuestion(char choice);
void updateRegenLeds();
void skipACStep();
void skipDCStep();

// ── WiFi & MQTT Helpers ───────────────────────────────────────────────────────
void initializeMqtt() {
  if (mqttInitialized) return;
  wifiSecureClient.setInsecure(); // Skip certificate validation for HiveMQ Cloud TLS
  mqttClient.setServer(MQTT_HOST, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  mqttClient.setBufferSize(512);
  mqttInitialized = true;
}

void wifiConnect() {
  AutoConnectConfig config;
  config.apid = "C2D_EV_Kit_AP";
  config.psk  = "12345678";
  config.title = "C2D EV Kit Portal";
  config.autoReconnect = true;
  config.beginTimeout = 15000;  // Try saved SSID for 15 seconds
  config.portalTimeout = 30000; // Let portal stay active for 30 seconds if connection fails
  Portal.config(config);

  if (Portal.begin()) {
    wifiReady = true;
    kitMac = WiFi.macAddress();
  } else {
    wifiReady = false;
  }
}

// MQTT callback to handle responses (handshake / link update)
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String body = "";
  for (unsigned int i = 0; i < length; i++) {
    body += (char)payload[i];
  }

  if (String(topic) == "evkit/response/" + kitMac) {
    int idx = body.indexOf("\"user_id\":");
    if (idx >= 0) {
      String numStr = body.substring(idx + 10);
      int commaIdx = numStr.indexOf(",");
      int braceIdx = numStr.indexOf("}");
      int endIdx = (commaIdx < 0) ? braceIdx : (braceIdx < 0 ? commaIdx : min(commaIdx, braceIdx));
      if (endIdx >= 0) {
        numStr = numStr.substring(0, endIdx);
      }
      int prevUserId = kitUserId;
      kitUserId = numStr.toInt();
      if (kitUserId != prevUserId) {
        drawWifiStatus();
        if (currentState == STATE_WELCOME) {
          drawFirstScreen();
        }
      }
    }
  }
}

unsigned long lastMqttRetry = 0;
void mqttReconnect() {
  if (!wifiReady || WiFi.status() != WL_CONNECTED) return;
  // Non-blocking reconnect attempt every 5 seconds
  if (millis() - lastMqttRetry > 5000) {
    lastMqttRetry = millis();
    
    String clientId = "EVKitClient_" + kitMac;
    clientId.replace(":", "");
    
    if (mqttClient.connect(clientId.c_str(), MQTT_USER, MQTT_PASS)) {
      String responseTopic = "evkit/response/" + kitMac;
      mqttClient.subscribe(responseTopic.c_str());
      postKitAnnounce();
      postNodeState(0,0,0,0,0, 0,0,0,0,0, 0); // Clear database status on startup/reconnect
    }
  }
}

void publishHandshakeRequest() {
  if (mqttClient.connected() && kitUserId <= 0) {
    String payload = "{\"mac\":\"" + kitMac + "\"}";
    mqttClient.publish("evkit/request", payload.c_str());
  }
}

// Post: announce kit to network
void postKitAnnounce() {
  if (mqttClient.connected()) {
    String body = "{\"mac\":\"" + kitMac + "\"}";
    mqttClient.publish("evkit/announce", body.c_str());
  }
}

// Post: module started
void postModuleStart(int modNum) {
  if (mqttClient.connected()) {
    String body = "{\"mac\":\"" + kitMac + "\",\"module_number\":" + String(modNum) + "}";
    mqttClient.publish("evkit/module-start", body.c_str());
  }
}

// Post: quiz finished
void postQuizResult(int modNum, int score, int total) {
  if (mqttClient.connected()) {
    float pct = total > 0 ? (float)score / total * 100.0f : 0.0f;
    
    String breakdownStr = "[";
    for (int i = 0; i < total; i++) {
      breakdownStr += "{\"q_num\":" + String(i + 1) + ",";
      breakdownStr += "\"choice\":\"" + String(userAnswers[i]) + "\",";
      breakdownStr += "\"correct\":" + String(questionCorrect[i] ? "true" : "false") + "}";
      if (i < total - 1) breakdownStr += ",";
    }
    breakdownStr += "]";

    String body = "{\"mac\":\"" + kitMac + "\","
                  "\"module_number\":" + String(modNum) + ","
                  "\"score\":" + String(score) + ","
                  "\"total_questions\":" + String(total) + ","
                  "\"percentage\":" + String(pct, 1) + ","
                  "\"breakdown\":" + breakdownStr + "}";
    mqttClient.publish("evkit/quiz-submit", body.c_str());
  }
}

// Post: current LED node states (called after each correct answer)
void postNodeState(int g, int sub, int hm, int obc, int bat,
                   int sol, int ddc, int bss, int sta, int chg, int pot) {
  if (mqttClient.connected()) {
    int qScore = 0;
    int qTotal = 0;
    for (int i = 0; i < 10; i++) {
      if (!firstAttempt[i]) {
        qTotal++;
        if (questionCorrect[i]) {
          qScore++;
        }
      }
    }
    String body = "{\"mac\":\"" + kitMac + "\","
      "\"module_number\":" + String(selectedModule) + ","
      "\"nodes\":{"
        "\"grid\":" + String(g) + ","
        "\"substation\":" + String(sub) + ","
        "\"home\":" + String(hm) + ","
        "\"obc\":" + String(obc) + ","
        "\"battery\":" + String(bat) + ","
        "\"solar\":" + String(sol) + ","
        "\"dcdc\":" + String(ddc) + ","
        "\"bess\":" + String(bss) + ","
        "\"station\":" + String(sta) + ","
        "\"charger\":" + String(chg) +
      "},"
      "\"pot\":" + String(pot) + ","
      "\"quiz_score\":" + String(qScore) + ","
      "\"quiz_total\":" + String(qTotal) + "}";
    mqttClient.publish("evkit/update-nodes", body.c_str());
  }
}

// ── Buzzer ────────────────────────────────────────────────────────────────────
void beep(int freq, int duration) {
  tone(BUZZER_PIN, freq, duration);
}

// ── Setup ─────────────────────────────────────────────────────────────────────
void setup() {
  pinMode(BUZZER_PIN, OUTPUT);

  // Initialize random number generator using unconnected analog pin for better randomness
  randomSeed(analogRead(34));

  tft.init();
  tft.setRotation(1);
  tft.fillScreen(CLR_BG);

  FastLED.addLeds<LED_TYPE, LED_PIN_SHARED, COLOR_ORDER>(leds, NUM_LEDS_TOTAL)
         .setCorrection(TypicalLEDStrip);
  FastLED.addLeds<WS2812B, 17, GRB>(regenLeds, NUM_LEDS_REGEN)
         .setCorrection(TypicalLEDStrip);
  FastLED.setBrightness(180);
  ledsAllClear();

  // Show connecting screen
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1); tft.setTextDatum(MC_DATUM);
  tft.drawString("Connecting to WiFi...", SCREEN_W / 2, SCREEN_H / 2);
  wifiConnect();

  // Initialize quiz tracking arrays
  for (int i = 0; i < 10; i++) {
    firstAttempt[i] = true;
  }

  // Initialize MQTT if WiFi connected
  if (wifiReady) {
    initializeMqtt();
    mqttReconnect();
  }

  // ── LED startup sweep (confirms all LEDs work before welcome screen) ──
  ledStartupSweep();

  drawFirstScreen();
}

// ── Main Loop ─────────────────────────────────────────────────────────────────
void loop() {
  Portal.handleClient();

  // Periodically update WiFi status dot on connection status change (every 1.5 seconds)
  static unsigned long lastStatusCheck = 0;
  static int lastWifiState = -1;
  static int lastMqttState = -1;
  static int lastUserId = -1;

  int currentWifi = (WiFi.status() == WL_CONNECTED) ? 1 : 0;
  wifiReady = (currentWifi == 1);
  if (wifiReady && kitMac == "") {
    kitMac = WiFi.macAddress();
  }

  if (millis() - lastStatusCheck > 1500) {
    lastStatusCheck = millis();
    int currentMqtt = (mqttClient.connected()) ? 1 : 0;
    int currentUserId = kitUserId;

    if (currentWifi != lastWifiState || currentMqtt != lastMqttState || currentUserId != lastUserId) {
      lastWifiState = currentWifi;
      lastMqttState = currentMqtt;
      lastUserId = currentUserId;
      drawWifiStatus();
    }
  }

  // MQTT background loop and reconnection / handshake
  static unsigned long lastHandshake = 0;
  if (wifiReady && WiFi.status() == WL_CONNECTED) {
    initializeMqtt(); // Ensure initialized if connected later
    if (!mqttClient.connected()) {
      mqttReconnect();
    } else {
      mqttClient.loop();
      
      // Request linked user_id periodically if not paired yet, every 8 seconds
      if (kitUserId <= 0 && millis() - lastHandshake > 8000) {
        lastHandshake = millis();
        publishHandshakeRequest();
      }

      // Publish keep-alive announcement every 10 seconds if connected
      static unsigned long lastAnnounce = 0;
      if (kitUserId > 0 && millis() - lastAnnounce > 10000) {
        lastAnnounce = millis();
        postKitAnnounce();
      }
    }
  }

  uint16_t tx = 0, ty = 0;
  bool isTouched = tft.getTouch(&tx, &ty);

  // --- BLINK ENGINE ACTIVE ACROSS STATES ---
  if (currentState == STATE_WELCOME || currentState == STATE_PREVIEW || currentState == STATE_COMPLETE) {
    if (millis() - lastBlink >= BLINK_MS) {
      blinkState = !blinkState;
      toggleQuizPrompt(blinkState);
      lastBlink = millis();
    }
  }

  if (currentState == STATE_FEEDBACK && lastAnswerWasCorrect) {
    if (millis() - lastBlink >= BLINK_MS) {
      blinkState = !blinkState;

      if (blinkState) {
        tft.setTextColor(CLR_BG);
        tft.setTextSize(1);
        tft.setTextDatum(MC_DATUM);
        tft.drawRoundRect(60, SCREEN_H-30, 200, 22, 3, CLR_BG);
        tft.drawString("TAP ANYWHERE TO CONTINUE", SCREEN_W/2, SCREEN_H-19);
      } else {
        tft.fillRoundRect(58, SCREEN_H-32, 204, 26, 3, CLR_NEON_GREEN);
      }

      lastBlink = millis();
    }
  }

  if (currentState == STATE_FEEDBACK && !lastAnswerWasCorrect) {
    if (millis() - lastBlink >= BLINK_MS) {
      blinkState = !blinkState;

      if (blinkState) {
        tft.setTextColor(CLR_WHITE);
        tft.setTextSize(1);
        tft.setTextDatum(MC_DATUM);
        tft.drawRoundRect(60, SCREEN_H-30, 200, 22, 3, CLR_WHITE);
        tft.drawString("TAP ANYWHERE TO RETRY", SCREEN_W/2, SCREEN_H-19);
      } else {
        tft.fillRoundRect(58, SCREEN_H-32, 204, 26, 3, CLR_ORANGE_RED);
      }

      lastBlink = millis();
    }
  }

  // Module 3: continuous regen LED + dashboard update
  if (selectedModule == 3 && currentState == STATE_QUIZ_PLAY) {
    updateRegenLeds();
  }

  // Touch Router
  if (isTouched && (millis() - lastTouchTime > TOUCH_DELAY)) {
    lastTouchTime = millis();
    beep(1000, 80);

    if (currentState == STATE_WELCOME) {
      for (int i = 0; i < 2; i++) {
        tft.fillScreen(CLR_NEON_GREEN); delay(45);
        tft.fillScreen(CLR_BG); delay(35);
      }
      currentState = STATE_MOD_SELECT;
      drawModuleSelectionScreen();
    }

    else if (currentState == STATE_MOD_SELECT) {
      #define BOX_W   280
      #define BOX_H   48
      #define START_X 20

      int touchedMod = 0;
      if (tx >= 19 && tx <= 290) {
        if      (ty >= 120 && ty <= 170) touchedMod = 1;
        else if (ty >= 75  && ty <= 119) touchedMod = 2;
        else if (ty >= 30  && ty <= 74)  touchedMod = 3;
      }

      if (touchedMod > 0) {
        selectedModule = touchedMod;
        // Reset score for new module
        quizScore = 0;
        module3ScreenDrawn = false;
        quizTotal = (selectedModule == 1) ? 3 : (selectedModule == 2) ? 6 : (selectedModule == 3) ? 5 : 0;
        for (int i = 0; i < 10; i++) {
          userAnswers[i] = ' ';
          questionCorrect[i] = false;
          firstAttempt[i] = true;
        }

        int targetY = (selectedModule == 1) ? 62 : ((selectedModule == 2) ? 118 : 174);
        tft.drawRoundRect(START_X, targetY, BOX_W, BOX_H, 4, CLR_ACTIVE_CYAN);
        delay(150);

        // Clear all nodes before new module
        postNodeState(0,0,0,0,0, 0,0,0,0,0, 0);
        postModuleStart(selectedModule);

        currentState = STATE_PREVIEW;
        currentStep  = 0;

        if (selectedModule == 1) {
          drawPreQuizBaseFrame(1, "AC CHARGING MODE");
          displayModule01(28, 92);
        } else if (selectedModule == 2) {
          drawPreQuizBaseFrame(2, "DC FAST CHARGING");
          displayModule02(28, 92);
        } else if (selectedModule == 3) {
          drawPreQuizBaseFrame(3, "DRIVING & REGENERATIVE BRAKING");
          displayModule03(28, 92);
        }
      }
    }

    else if (currentState == STATE_PREVIEW) {
      if (tx >= 0 && tx <= 100 && ty >= 0 && ty <= 10) {
        currentState = STATE_MOD_SELECT;
        drawModuleSelectionScreen();
        return;
      }
      currentState = STATE_QUIZ_PLAY;
      tft.fillScreen(CLR_BG);
      if      (selectedModule == 1) runACChargingFlow();
      else if (selectedModule == 2) runDCChargingFlow();
      else if (selectedModule == 3) runDrivingRegenFlow();
    }

    else if (currentState == STATE_QUIZ_PLAY) {
      if (selectedModule == 3 && currentStep == 0) {
        currentStep = 1;
        tft.fillScreen(CLR_BG);
        runDrivingRegenFlow();
        return;
      }
      char selectedChoice = ' ';
      if      (ty >= 95  && ty <= 127) selectedChoice = 'A';
      else if (ty >= 70  && ty <= 90)  selectedChoice = 'B';
      else if (ty >= 20  && ty <= 40)  selectedChoice = 'C';

      // SKIP button
      if (tx >= 260 && tx <= 320 && ty >= 0 && ty <= 25) {
        if      (selectedModule == 1) skipACStep();
        else if (selectedModule == 2) skipDCStep();
        else if (selectedModule == 3) skipRegenStep();
        
        int idx = currentStep - 1;
        if (idx >= 0 && idx < 10) {
          if (firstAttempt[idx]) {
            userAnswers[idx] = '-';
            questionCorrect[idx] = false;
            firstAttempt[idx] = false;
          }
        }
        
        currentStep++;
        tft.fillScreen(CLR_BG);
        if      (selectedModule == 1) runACChargingFlow();
        else if (selectedModule == 2) runDCChargingFlow();
        else if (selectedModule == 3) runDrivingRegenFlow();
      }

      if (selectedChoice != ' ') {
        if      (selectedModule == 1) evaluateACQuestion(selectedChoice);
        else if (selectedModule == 2) evaluateDCQuestion(selectedChoice);
        else if (selectedModule == 3) evaluateRegenQuestion(selectedChoice);
      }
    }

    else if (currentState == STATE_FEEDBACK) {
      currentState = STATE_QUIZ_PLAY;
      tft.fillScreen(CLR_BG);
      if      (selectedModule == 1) runACChargingFlow();
      else if (selectedModule == 2) runDCChargingFlow();
      else if (selectedModule == 3) runDrivingRegenFlow();
    }

    else if (currentState == STATE_COMPLETE) {
      currentStep = 0;
      ledsAllClear();
      postNodeState(0,0,0,0,0, 0,0,0,0,0, 0);
      currentState = STATE_MOD_SELECT;
      drawModuleSelectionScreen();
    }
  }
}

// ── Layout Implementations ────────────────────────────────────────────────────
// ── 3-State Status Indicator ─────────────────────────────────────────────────
// 🔴 Red    = no WiFi or MQTT disconnected
// 🟠 Orange = WiFi + MQTT connected but kit not paired to any user
// 🟢 Green  = WiFi + MQTT connected AND paired to a user account
void drawWifiStatus() {
  int dotX = SCREEN_W - 12;
  int dotY = 12;
  // Clear previous dot area
  tft.fillCircle(dotX, dotY, 5, CLR_BG);

  if (!wifiReady || WiFi.status() != WL_CONNECTED || !mqttClient.connected()) {
    // RED: offline
    tft.fillCircle(dotX, dotY, 4, CLR_ORANGE_RED);
  } else if (kitUserId <= 0) {
    // ORANGE: online but not linked
    tft.fillCircle(dotX, dotY, 4, 0xFD20);  // RGB565 orange
  } else {
    // GREEN: online + linked
    tft.fillCircle(dotX, dotY, 4, CLR_NEON_GREEN);
  }
}

void drawFirstScreen() {
  tft.fillScreen(CLR_BG);
  drawFrameDecorations();

  tft.drawRoundRect(SCREEN_W / 2 - 55, 16, 110, 18, 4, CLR_GREEN_DIM);
  tft.setTextColor(CLR_NEON_GREEN); tft.setTextSize(1); tft.setTextDatum(TC_DATUM);
  tft.drawString("WELCOME TO THE", SCREEN_W / 2, 21);

  tft.setTextSize(5); tft.setTextDatum(MC_DATUM);
  tft.drawString("C2D", SCREEN_W / 2, 72);
  tft.setTextSize(2); tft.setTextColor(CLR_GREEN_DIM);
  tft.drawString("EV LEARNING KIT", SCREEN_W / 2, 110);

  tft.fillRect(SCREEN_W/2 - 2, 138, 4, 4, CLR_NEON_GREEN);
  tft.fillRect(SCREEN_W/2 - 16, 138, 4, 4, CLR_GREEN_DIM);
  tft.fillRect(SCREEN_W/2 + 12, 138, 4, 4, CLR_GREEN_DIM);

  tft.setTextColor(CLR_NEON_GREEN); tft.setTextSize(1); tft.setTextDatum(TC_DATUM);
  tft.drawString("EDUCATIONAL QUIZ SYSTEM", SCREEN_W / 2, 152);

  // Show MAC + pairing status
  if (wifiReady && kitMac != "") {
    tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1); tft.setTextDatum(TC_DATUM);
    tft.drawString("KIT: " + kitMac, SCREEN_W / 2, 168);
    if (kitUserId > 0) {
      tft.setTextColor(CLR_NEON_GREEN);
      tft.drawString("\x07 LINKED — ready to use!", SCREEN_W / 2, 180);
    } else {
      tft.setTextColor(0xFD20);  // orange
      tft.drawString("! NOT PAIRED — please wait", SCREEN_W / 2, 180);
    }
  } else if (!wifiReady) {
    tft.setTextColor(CLR_ORANGE_RED); tft.setTextSize(1); tft.setTextDatum(TC_DATUM);
    tft.drawString("! No WiFi — quiz still works offline", SCREEN_W / 2, 175);
  }

  drawWifiStatus();

  blinkState = true;
  toggleQuizPrompt(true);
  
  // Start the updated Intro Song logic
  playIntroSong(); 
}

void drawFrameDecorations() {
  int padding = 8;
  int length  = 15;
  tft.drawFastHLine(padding, padding, length, CLR_GREEN_DIM);
  tft.drawFastVLine(padding, padding, length, CLR_GREEN_DIM);
  tft.drawFastHLine(SCREEN_W - padding - length, padding, length, CLR_GREEN_DIM);
  tft.drawFastVLine(SCREEN_W - padding, padding, length, CLR_GREEN_DIM);
  tft.drawFastHLine(padding, SCREEN_H - padding, length, CLR_GREEN_DIM);
  tft.drawFastVLine(padding, SCREEN_H - padding - length, length, CLR_GREEN_DIM);
  tft.drawFastHLine(SCREEN_W - padding - length, SCREEN_H - padding, length, CLR_GREEN_DIM);
  tft.drawFastVLine(SCREEN_W - padding, SCREEN_H - padding - length, length, CLR_GREEN_DIM);
  tft.drawFastHLine(padding, SCREEN_H / 2, 4, CLR_DARK_GRID);
  tft.drawFastHLine(SCREEN_W - padding - 4, SCREEN_H / 2, 4, CLR_DARK_GRID);
}

void drawModuleSelectionScreen() {
  tft.fillScreen(CLR_BG);
  tft.setTextColor(CLR_ACTIVE_CYAN); tft.setTextSize(2); tft.setTextDatum(TC_DATUM);
  tft.drawString("Hello, Explorer!", SCREEN_W / 2, 8);
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1);
  tft.drawString("What do you want to learn today?", SCREEN_W / 2, 30);
  tft.drawFastHLine(30, 48, SCREEN_W - 60, CLR_GREEN_DIM);

  drawPerfectButton(20, 62,  "01", "HOME AC CHARGING",            "SLOW GRID TO HOME CHARGING");
  drawPerfectButton(20, 118, "02", "DC FAST CHARGING",            "RAPID PUBLIC STATION CHARGING");
  drawPerfectButton(20, 174, "03", "DRIVING & REGENERATIVE BRAKING","FROM MOTION TO REGENERATION");

  drawWifiStatus();
}

void drawPerfectButton(int x, int y, String num, String title, String sub) {
  tft.fillRoundRect(x, y, 280, 48, 4, CLR_DARK_GRID);
  tft.drawRoundRect(x, y, 280, 48, 4, CLR_GREEN_DIM);
  tft.setTextColor(CLR_ACTIVE_CYAN); tft.setTextSize(1); tft.setTextDatum(ML_DATUM);
  tft.drawString("MOD " + num + " >", x + 12, y + 24);
  tft.setTextColor(CLR_NEON_GREEN); tft.setTextDatum(TL_DATUM);
  tft.drawString(title.c_str(), x + 65, y + 10);
  tft.setTextColor(CLR_TEXT_MUTED);
  tft.drawString(sub.c_str(), x + 65, y + 26);
}

void drawPreQuizBaseFrame(int module, String moduleTitle) {
  tft.fillScreen(CLR_BG);
  tft.drawFastHLine(0, 42, SCREEN_W, CLR_GREEN_DIM);
  tft.setTextColor(CLR_ACTIVE_CYAN); tft.setTextSize(2); tft.setTextDatum(TC_DATUM);
  tft.drawString("Are You Ready, Explorer?", SCREEN_W / 2, 12);
  tft.drawRect(15, 54, SCREEN_W - 30, 134, CLR_GREEN_DIM);
  tft.fillRect(15, 54, 8, 2, CLR_NEON_GREEN); tft.fillRect(15, 54, 2, 8, CLR_NEON_GREEN);
  tft.fillRect(SCREEN_W-23, 54, 8, 2, CLR_NEON_GREEN); tft.fillRect(SCREEN_W-17, 54, 2, 8, CLR_NEON_GREEN);
  tft.fillRect(15, 186, 8, 2, CLR_NEON_GREEN); tft.fillRect(15, 180, 2, 8, CLR_NEON_GREEN);
  tft.fillRect(SCREEN_W-23, 186, 8, 2, CLR_NEON_GREEN); tft.fillRect(SCREEN_W-17, 180, 2, 8, CLR_NEON_GREEN);
  tft.setTextColor(CLR_NEON_GREEN); tft.setTextSize(1); tft.setTextDatum(TL_DATUM);
  tft.drawString(moduleTitle, 28, 66);
  tft.drawFastHLine(15, 79, SCREEN_W - 31, CLR_GREEN_DIM);
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1); tft.setTextDatum(ML_DATUM);
  tft.drawString("< BACK", 10, SCREEN_H - 12);
  blinkState = true;
  toggleQuizPrompt(true);
}

void drawStartScreen(String title, String subtitle) {
  tft.fillScreen(CLR_BG);
  tft.drawRect(10, 10, SCREEN_W - 20, SCREEN_H - 20, CLR_GREEN_DIM);
  tft.fillRect(10, 10, 8, 2, CLR_ACTIVE_CYAN); tft.fillRect(10, 10, 2, 8, CLR_ACTIVE_CYAN);
  tft.fillRect(SCREEN_W-18, 10, 8, 2, CLR_ACTIVE_CYAN); tft.fillRect(SCREEN_W-12, 10, 2, 8, CLR_ACTIVE_CYAN);
  tft.setTextColor(CLR_ACTIVE_CYAN); tft.setTextSize(2); tft.setTextDatum(MC_DATUM);
  tft.drawString(title, SCREEN_W / 2, (SCREEN_H / 2) - 20);
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1);
  tft.drawString(subtitle, SCREEN_W / 2, (SCREEN_H / 2) + 20);
  delay(2500);
}

void drawQuestionLayout(int qNum, String question, String a, String b, String c) {
  tft.fillScreen(CLR_BG);
  tft.fillRect(0, 0, SCREEN_W, 35, CLR_DARK_GRID);
  tft.drawFastHLine(0, 35, SCREEN_W, CLR_GREEN_DIM);
  tft.setTextColor(CLR_ACTIVE_CYAN); tft.setTextSize(2); tft.setTextDatum(ML_DATUM);
  tft.drawString(" Module " + String(selectedModule) + ": Question " + String(qNum), 12, 17);
  tft.setTextSize(1); tft.setTextDatum(TL_DATUM);
  tft.setTextColor(CLR_WHITE);
  tft.drawString(question, 15, 50);
  tft.fillRoundRect(15, 95, SCREEN_W - 30, 32, 4, CLR_DARK_GRID);
  tft.drawRoundRect(15, 95, SCREEN_W - 30, 32, 4, CLR_GREEN_DIM);
  tft.setTextColor(CLR_NEON_GREEN); tft.drawString(a, 25, 105);
  tft.fillRoundRect(15, 135, SCREEN_W - 30, 32, 4, CLR_DARK_GRID);
  tft.drawRoundRect(15, 135, SCREEN_W - 30, 32, 4, CLR_GREEN_DIM);
  tft.drawString(b, 25, 145);
  tft.fillRoundRect(15, 175, SCREEN_W - 30, 32, 4, CLR_DARK_GRID);
  tft.drawRoundRect(15, 175, SCREEN_W - 30, 32, 4, CLR_GREEN_DIM);
  tft.drawString(c, 25, 185);
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1); tft.setTextDatum(MR_DATUM);
  tft.drawString("SKIP >>", SCREEN_W - 15, SCREEN_H - 12);
}

void toggleQuizPrompt(bool showText) {
  int rectY = SCREEN_H - 30;
  int rectH = 22;

  int x = 60;
  int w = SCREEN_W - 120;

  tft.setTextSize(1);
  tft.setTextDatum(MC_DATUM);

  if (showText) {
    if (currentState == STATE_COMPLETE) {
      tft.drawRoundRect(x, rectY, w, rectH, 3, CLR_ACTIVE_CYAN);
      tft.drawRoundRect(x - 1, rectY - 1, w + 2, rectH + 2, 3, CLR_ACTIVE_CYAN);
      tft.setTextColor(CLR_ACTIVE_CYAN);
    } else {
      tft.drawRoundRect(x, rectY, w, rectH, 3, CLR_NEON_GREEN);
      tft.drawRoundRect(x - 1, rectY - 1, w + 2, rectH + 2, 3, CLR_GREEN_DIM);
      tft.setTextColor(CLR_NEON_GREEN);
    }

    if (currentState == STATE_WELCOME)
      tft.drawString("TAP ANYWHERE TO CONTINUE", SCREEN_W / 2, rectY + 11);
    else if (currentState == STATE_COMPLETE)
      tft.drawString("TAP TO TRY ANOTHER MODULE!", SCREEN_W / 2, rectY + 11);
    else
      tft.drawString("TAP ANYWHERE TO START QUIZ", SCREEN_W / 2, rectY + 11);
  }
  else {
    // CLEAR entire blink area (box + text)
    tft.fillRoundRect(x - 2, rectY - 2, w + 4, rectH + 4, 3, CLR_BG);
  }
}

void drawMissionComplete(String rank, String subtext) {
  currentState = STATE_COMPLETE;
  tft.fillScreen(CLR_BG);
  tft.setTextColor(CLR_NEON_GREEN); tft.setTextSize(2); tft.setTextDatum(MC_DATUM);
  tft.drawString("MISSION COMPLETE!", SCREEN_W / 2, 65);
  tft.setTextColor(CLR_WHITE); tft.setTextSize(2);
  tft.drawString(rank, SCREEN_W / 2, 125);
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1);
  tft.drawString(subtext, SCREEN_W / 2, 175);
  tft.setTextColor(CLR_ACTIVE_CYAN); tft.setTextSize(1); tft.setTextDatum(MC_DATUM);
  tft.drawRoundRect(60, SCREEN_H-30, 200, 22, 3, CLR_ACTIVE_CYAN);
  tft.drawString("TAP TO TRY ANOTHER MODULE!", SCREEN_W/2, SCREEN_H-19);
  beep(1000, 80); delay(90);
  beep(1200, 80); delay(90);
  beep(1500, 80); delay(90);
  beep(1800, 80); delay(90);
  beep(2000, 150); delay(170);
  beep(2000, 150);
}

// ── Updated Intro Song logic ──────────────────────────────────────────────────
void playIntroSong() {

  int melody[] = {
    659, 494, 523, 587, 523, 494,
    440, 440, 523, 659, 587, 523,
    494, 523, 587, 659, 523, 440, 440,
    587, 698, 880, 784, 698,
    659, 523, 659, 587, 523,
    494, 494, 523, 587, 659,
    523, 440, 440, 0
  };

  int duration[] = {
    400, 200, 200, 400, 200, 200,
    400, 200, 200, 400, 200, 200,
    300, 200, 400, 400, 400, 400, 400,
    300, 200, 400, 300, 100,
    400, 200, 400, 300, 100,
    200, 200, 200, 400, 400,
    400, 400, 400, 400
  };

  const float speedFactor = 0.65;   // 🔥 faster than before (0.80 was slow)
  const int noteGap = 1;            // 🔥 almost zero gap (was 3)

  int notes = sizeof(melody) / sizeof(melody[0]);

  uint16_t tx, ty;

  for (int i = 0; i < notes; i++) {

    // 🚀 instant interrupt check
    if (tft.getTouch(&tx, &ty)) {
      noTone(BUZZER_PIN);
      ledsAllClear();
      return;
    }

    int noteTime = (int)(duration[i] * speedFactor);
    unsigned long startTime = millis();

    // LED on on every even note, off every odd note (alternating between Red, Green, and Blue)
    if (i % 2 == 0) {
      int blinkIndex = i / 2;
      CRGB color;
      if (blinkIndex % 3 == 0) {
        color = CRGB::Red;
      } else if (blinkIndex % 3 == 1) {
        color = CRGB::Green;
      } else {
        color = CRGB::Blue;
      }
      fill_solid(leds, NUM_LEDS_TOTAL, color);
      fill_solid(regenLeds, NUM_LEDS_REGEN, color);
    } else {
      fill_solid(leds, NUM_LEDS_TOTAL, CRGB::Black);
      fill_solid(regenLeds, NUM_LEDS_REGEN, CRGB::Black);
    }
    FastLED.show();

    // play note
    if (melody[i] == 0) {
      noTone(BUZZER_PIN);
    } else {
      tone(BUZZER_PIN, melody[i]);
    }

    // ⏱️ non-blocking wait
    while (millis() - startTime < noteTime) {

      if (tft.getTouch(&tx, &ty)) {
        noTone(BUZZER_PIN);
        ledsAllClear();
        return;
      }
      
      // Keeping MQTT connected during the song wait loop
      if (wifiReady && WiFi.status() == WL_CONNECTED && mqttClient.connected()) {
        mqttClient.loop();
      }

      if (millis() - lastBlink >= BLINK_MS) {
        blinkState = !blinkState;
        toggleQuizPrompt(blinkState);
        lastBlink = millis();
      }

      delay(1); // tiny yield only (prevents watchdog issues)
    }

    // stop note ONLY when needed
    noTone(BUZZER_PIN);
    fill_solid(leds, NUM_LEDS_TOTAL, CRGB::Black);
    fill_solid(regenLeds, NUM_LEDS_REGEN, CRGB::Black);
    FastLED.show();
    delay(noteGap);
  }

  noTone(BUZZER_PIN);
  ledsAllClear();
}