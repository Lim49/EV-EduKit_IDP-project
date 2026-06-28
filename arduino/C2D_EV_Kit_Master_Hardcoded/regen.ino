#define LED_PIN_REGEN   17
#define REGEN_LED_TYPE  WS2812B
#define REGEN_CLR_ORDER GRB

#define POT_PIN     34
#define ENA         13
#define IN1         14
#define IN2         12

#define PWM_FREQ    4000
#define PWM_RES     8
#define MIN_SPEED   150
#define MAX_SPEED   255

bool motorIsRunning = false;

// Threshold to decide pedal on vs off
#define POT_THRESHOLD  5   // potValue below this = pedal released

int lastPotState = -1;  // -1=unknown, 0=released, 1=pressed
int lastPotValue  = 0;
bool potSeeded = false;

  int rectY = SCREEN_H - 30;

void skipRegenStep() {
  postNodeState(0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0);
}

void setupRegenLeds() {
  fill_solid(regenLeds, NUM_LEDS_REGEN, CRGB::Black);
  FastLED.show();
  potSeeded = false;
  lastPotValue = 0;
  pinMode(IN1, OUTPUT);
  pinMode(IN2, OUTPUT);
  digitalWrite(IN1, HIGH);
  digitalWrite(IN2, LOW);
  ledcAttach(ENA, PWM_FREQ, PWM_RES);
}

// Pedal pressed: 1→2 red, then 3→4 green
void regenLedsRegen() {
  fill_solid(regenLeds, NUM_LEDS_REGEN, CRGB::Black);
  FastLED.show();
  // LED 1 red
  regenLeds[0] = CRGB::Red;   FastLED.show(); delay(200);
  // LED 2 red
  regenLeds[1] = CRGB::Red;   FastLED.show(); delay(200);
  // LED 3 green
  regenLeds[2] = CRGB::Green; FastLED.show(); delay(200);
  // LED 4 green
  regenLeds[3] = CRGB::Green; FastLED.show(); delay(200);
}

// Pedal released: 4→3 green, then 2→1 red
void regenLedsAccelerate() {
  fill_solid(regenLeds, NUM_LEDS_REGEN, CRGB::Black);
  FastLED.show();
  // LED 4 green
  regenLeds[3] = CRGB::Green; FastLED.show(); delay(200);
  // LED 3 green
  regenLeds[2] = CRGB::Green; FastLED.show(); delay(200);
  // LED 2 red
  regenLeds[1] = CRGB::Red;   FastLED.show(); delay(200);
  // LED 1 red
  regenLeds[0] = CRGB::Red;   FastLED.show(); delay(200);
}

void updateRegenLeds() {
  // Read potentiometer (averaged)
  long sum = 0;
  for (int i = 0; i < 10; i++) { sum += analogRead(POT_PIN); delay(2); }
  int potValue = sum / 10;

  // First run — seed lastPotValue so we don't false-trigger on startup
  if (!potSeeded) {
    lastPotValue = potValue;
    potSeeded = true;
    return;
  }

  // Motor speed
  int motorSpeed = 0;
  if (potValue > 5) {
    motorSpeed = map(potValue, 5, 4095, MIN_SPEED, MAX_SPEED);
    if (!motorIsRunning) {
      ledcWrite(ENA, 255);
      delay(40);
      motorIsRunning = true;
    }
    ledcWrite(ENA, motorSpeed);
  } else {
    ledcWrite(ENA, 0);
    motorIsRunning = false;
  }

  // Detect state change only — avoid re-triggering the animation every loop
  if (potValue > lastPotValue + 150) {      // turning left = increasing
    regenLedsAccelerate();                  // 1→2→3→4
    lastPotValue = potValue;
  } else if (potValue < lastPotValue - 150) { // turning right = decreasing
    regenLedsRegen();                        // 4→3→2→1
    lastPotValue = potValue;
  }

  // Real-time potentiometer reporting via MQTT (throttled on change or 1s intervals)
  static int lastSentPotValue = -999;
  static unsigned long lastSentTime = 0;
  if (abs(potValue - lastSentPotValue) > 40 || (millis() - lastSentTime > 1000)) {
    postNodeState(0, 0, 0, 0, 1, 0, 0, 0, 0, 0, potValue); // Battery node active during driving/regen
    lastSentPotValue = potValue;
    lastSentTime = millis();
  }
}

void displayModule03(int startX, int startY) {
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1); tft.setTextDatum(TL_DATUM);
  int gap = 24; 

  tft.drawString(" 1. High-Voltage Battery to Motor Flow", startX, startY);
  tft.drawString(" 2. EV Driving Operation Dynamics", startX, startY + gap);
  tft.drawString(" 3. Regenerative Braking Energy Recovery", startX, startY + (gap * 2));
}

extern bool module3ScreenDrawn;

void runDrivingRegenFlow() {
  if (currentStep == 0) {
    if (!module3ScreenDrawn) {
      setupRegenLeds();
      drawStartScreen("DRIVING & REGEN MODE", "Turn Pot to Drive & Test Regen");
      tft.drawString("TAP ANYWHERE TO START THE QUIZ", SCREEN_W / 2, rectY + 11);
      module3ScreenDrawn = true;
    }
    return;
  }
  
  if (currentStep == 1) {
    drawQuestionLayout(1, "When accelerator is pressed, energy flows:", "A. Battery -> Inverter -> Motor", "B. Motor -> Battery -> Inverter", "C. Motor -> Charger -> Battery");
  } else if (currentStep == 2) {
    drawQuestionLayout(2, "What happens when accelerator pedal is released?", "A. Regenerative braking begins", "B. Charging begins", "C. Battery disconnects");
  } else if (currentStep == 3) {
    drawQuestionLayout(3, "During regenerative braking, the motor acts as:", "A. Transformer", "B. Generator", "C. Battery");
  } else if (currentStep == 4) {
    drawQuestionLayout(4, "Does regen braking completely replace friction?", "A. No", "B. Yes", "C. Sometimes");
  } else if (currentStep == 5) {
    drawQuestionLayout(5, "Which EV component stores recovered energy?", "A. Battery Pack", "B. Motor", "C. Inverter");
  } else if (currentStep == 6) {
    fill_solid(regenLeds, NUM_LEDS_REGEN, CRGB::Black);
    FastLED.show();
    postQuizResult(3, quizScore, quizTotal);
    drawMissionComplete("EV Energy Master!", "You Have Mastered EV Energy Flow!");
  }
}

void evaluateRegenQuestion(char choice) {
  bool correct = false; String track = "";
  if (currentStep == 1) { if (choice == 'A') correct = true; track = "Inverter converts DC to AC"; }
  else if (currentStep == 2) { if (choice == 'A') correct = true; track = "This is the single - pedal driving mode"; }
  else if (currentStep == 3) { if (choice == 'B') correct = true; track = "Generator converts vehicle momentum into electricity"; }
  else if (currentStep == 4) { if (choice == 'A') correct = true; track = "Friction brake are necessary emergency stops"; }
  else if (currentStep == 5) { if (choice == 'A') correct = true; track = "Most expensive component in EV!"; }

  lastAnswerWasCorrect = correct; currentState = STATE_FEEDBACK;
  
  bool isFirstAttempt = false;
  int idx = currentStep - 1;
  if (idx >= 0 && idx < 10) {
    if (firstAttempt[idx]) {
      isFirstAttempt = true;
      userAnswers[idx] = choice;
      questionCorrect[idx] = correct;
      firstAttempt[idx] = false;
    }
  }

  if (correct) {
    if (isFirstAttempt) {
      quizScore++;
    }
    currentStep++;
    beep(1200, 100); delay(120);
    beep(1800, 150);
    tft.fillScreen(CLR_NEON_GREEN); tft.setTextColor(CLR_BG); tft.setTextSize(2); tft.setTextDatum(MC_DATUM);
    tft.drawString("CORRECT!", SCREEN_W/2, 70); tft.setTextSize(1); tft.drawString(track.c_str(), SCREEN_W/2, 145);
  } else {
    beep(600, 100); delay(110);
    beep(300, 250);
    tft.fillScreen(CLR_ORANGE_RED); tft.setTextColor(CLR_WHITE); tft.setTextSize(2); tft.setTextDatum(MC_DATUM);
    tft.drawString("TRY AGAIN, EXPLORER!", SCREEN_W/2, 60); tft.setTextSize(1); tft.drawString("Think it through, Explorer!", SCREEN_W/2, 120);
  }
}