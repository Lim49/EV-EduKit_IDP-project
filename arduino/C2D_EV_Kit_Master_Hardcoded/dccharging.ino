// ── Module 2: DC Fast Charging — LED Flow + IoT Posting ───────────────────────
// Row 1 (grid path): node_grid, node_substation, node_station, node_battery
// Row 2 (solar path): node_solar, node_dcdc, node_bess → feeds node_station

void skipDCStep() {
  if      (currentStep == 1) { ledsRange(9,  1,  CRGB::Red); postNodeState(1,1,0,0,0, 0,0,0,1,0, 0); }
  else if (currentStep == 2) { ledsRange(36, 32, CRGB::Green); postNodeState(1,1,0,0,1, 1,1,0,1,0, 0); }
  else if (currentStep == 3) { ledsRange(46, 44, CRGB::Green); postNodeState(1,1,0,0,1, 1,1,1,1,0, 0); }
  else if (currentStep == 4) { ledsRange(43, 42, CRGB::Green); postNodeState(1,1,0,0,1, 1,1,1,1,1, 0); }
  else if (currentStep == 5) { ledsRange(41, 37, CRGB::Green); postNodeState(1,1,1,0,1, 1,1,1,1,1, 0); }
  else if (currentStep == 6) { ledsRange(36, 32, CRGB::Green); postNodeState(1,1,1,1,1, 1,1,1,1,1, 0); }
}

void displayModule02(int startX, int startY) {
  tft.setTextColor(CLR_TEXT_MUTED); tft.setTextSize(1); tft.setTextDatum(TL_DATUM);
  int gap = 24;
  tft.drawString(" 1. Grid to DC Charger Processing",  startX, startY);
  tft.drawString(" 2. Solar & Battery Storage Systems", startX, startY + gap);
  tft.drawString(" 3. Direct EV Charging Bypass Logic", startX, startY + (gap * 2));
}

void runDCChargingFlow() {
  if (currentStep == 0) {
    ledsAllClear();
    // Step 0: Tower → Substation path (LEDs 14-10 = grid area)
    ledsRange(14, 10, CRGB::Red);
    postNodeState(1,1,0,0,0, 0,0,0,0,0, 0);  // tower and substation lit
    drawStartScreen("DC FAST CHARGING MODE", "Power Flow: Tower -> Substation");
    currentStep = 1;
    tft.fillScreen(CLR_BG);
  }

  if      (currentStep == 1) drawQuestionLayout(1,
    "What power supply does TNB provide to stations?",
    "A. Single-phase, 230V",
    "B. Three-phase, 400V",
    "C. DC Current, 12V");
  else if (currentStep == 2) drawQuestionLayout(2,
    "Where is AC converted to DC in fast charging?",
    "A. Inside the charging station kiosk",
    "B. Inside the vehicle",
    "C. Along the power cables");
  else if (currentStep == 3) drawQuestionLayout(3,
    "Solar panels produce:",
    "A. AC electricity",
    "B. Mechanical energy",
    "C. DC electricity");
  else if (currentStep == 4) drawQuestionLayout(4,
    "Why do we need a DC-DC Converter?",
    "A. To convert AC power into DC power",
    "B. To regulate solar voltage for the battery",
    "C. To convert electricity into chemical fluid");
  else if (currentStep == 5) drawQuestionLayout(5,
    "What does a BESS do at a station?",
    "A. It reduces the peak power demand",
    "B. It cools down the EV charging cable",
    "C. It changes the chemical reaction");
  else if (currentStep == 6) drawQuestionLayout(6,
    "Does DC fast charging use the vehicle's OBC?",
    "A. Yes",
    "B. No",
    "C. Sometimes");
  else if (currentStep == 7) {
    ledsAllClear();
    // Celebration pulse — only Module 2 path LEDs flash green (indices 0 to 13 and 31 to 45)
    for (int p = 0; p < 3; p++) {
      for (int i = 0; i <= 13; i++) {
        if (i >= 0 && i < NUM_LEDS_TOTAL) leds[i] = CRGB::Green;
      }
      for (int i = 31; i <= 45; i++) {
        if (i >= 0 && i < NUM_LEDS_TOTAL) leds[i] = CRGB::Green;
      }
      FastLED.show();
      delay(200);
      for (int i = 0; i <= 13; i++) {
        if (i >= 0 && i < NUM_LEDS_TOTAL) leds[i] = CRGB::Black;
      }
      for (int i = 31; i <= 45; i++) {
        if (i >= 0 && i < NUM_LEDS_TOTAL) leds[i] = CRGB::Black;
      }
      FastLED.show();
      delay(150);
    }
    postNodeState(0,0,0,0,0, 0,0,0,0,0, 0);
    postQuizResult(2, quizScore, quizTotal);
    drawMissionComplete("DC Fast Charge Pro!", "Energy bypassed the OBC successfully!");
  }
}

void evaluateDCQuestion(char choice) {
  bool correct = false;
  String track = "";

  if      (currentStep == 1) { if (choice == 'B') correct = true; track = "Flow: Substation -> DC Station"; }
  else if (currentStep == 2) { if (choice == 'A') correct = true; track = "Flow: DC Charger -> Battery"; }
  else if (currentStep == 3) { if (choice == 'C') correct = true; track = "Flow: Solar Panel -> DC-DC"; }
  else if (currentStep == 4) { if (choice == 'B') correct = true; track = "Flow: DC-DC -> BESS"; }
  else if (currentStep == 5) { if (choice == 'A') correct = true; track = "Flow: Solar -> BESS -> Station"; }
  else if (currentStep == 6) { if (choice == 'B') correct = true; track = "Flow: DC Charger -> Battery (bypass)"; }

  lastAnswerWasCorrect = correct;
  currentState = STATE_FEEDBACK;

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
    beep(1200, 100); delay(120); beep(1800, 150);

    tft.fillScreen(CLR_NEON_GREEN);
    tft.setTextColor(CLR_BG); tft.setTextSize(2); tft.setTextDatum(MC_DATUM);
    tft.drawString("CORRECT!", SCREEN_W/2, 65);
    tft.setTextSize(1);
    tft.drawString(track.c_str(), SCREEN_W/2, 145);

    // Light up cumulative LED segments + post node state to web
    if (track == "Flow: Substation -> DC Station") {
      ledsRange(9, 1, CRGB::Red);
      postNodeState(1,1,0,0,0, 0,0,0,1,0, 0);  // grid+sub+station (row 1)
    } else if (track == "Flow: DC Charger -> Battery") {
      ledsRange(36, 32, CRGB::Green);
      postNodeState(1,1,0,0,1, 1,1,0,1,0, 0);  // +battery+charger on row 1, also starts solar+dcdc on row 2
    } else if (track == "Flow: Solar Panel -> DC-DC") {
      ledsRange(46, 44, CRGB::Green);
      postNodeState(1,1,0,0,1, 1,1,1,1,0, 0);  // +bess
    } else if (track == "Flow: DC-DC -> BESS") {
      ledsRange(43, 42, CRGB::Green);
      postNodeState(1,1,0,0,1, 1,1,1,1,1, 0);  // +station_2
    } else if (track == "Flow: Solar -> BESS -> Station") {
      ledsRange(41, 37, CRGB::Green);
      postNodeState(1,1,1,0,1, 1,1,1,1,1, 0);  // +battery_2 (using hm = 1)
    } else if (track == "Flow: DC Charger -> Battery (bypass)") {
      ledsRange(36, 32, CRGB::Green);
      postNodeState(1,1,1,1,1, 1,1,1,1,1, 0);  // all lit
    }
  } else {
    beep(600, 100); delay(110); beep(300, 250);
    tft.fillScreen(CLR_ORANGE_RED);
    tft.setTextColor(CLR_WHITE); tft.setTextSize(2); tft.setTextDatum(MC_DATUM);
    tft.drawString("TRY AGAIN, EXPLORER!", SCREEN_W/2, 60);
    tft.setTextSize(1);
    tft.drawString("Think it through, Explorer!", SCREEN_W/2, 120);
    postDCCurrentState();
  }
}

void postDCCurrentState() {
  if      (currentStep == 1) postNodeState(1,1,0,0,0, 0,0,0,0,0, 0);
  else if (currentStep == 2) postNodeState(1,1,0,0,0, 0,0,0,1,0, 0);
  else if (currentStep == 3) postNodeState(1,1,0,0,1, 1,1,0,1,0, 0);
  else if (currentStep == 4) postNodeState(1,1,0,0,1, 1,1,1,1,0, 0);
  else if (currentStep == 5) postNodeState(1,1,0,0,1, 1,1,1,1,1, 0);
  else if (currentStep == 6) postNodeState(1,1,1,0,1, 1,1,1,1,1, 0);
}