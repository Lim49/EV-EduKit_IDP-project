# EV EduKit — IDP Project

An interactive EV (Electric Vehicle) Educational Kit built with an ESP32 microcontroller, TFT touchscreen, LED strips, and a PHP/MySQL web dashboard.

---

## 📁 Project Structure

```
EVKit/
├── arduino/
│   ├── C2D_EV_Kit_Master/          ← Cloud WiFi version (AutoConnect)
│   └── C2D_EV_Kit_Master_Hardcoded/ ← Hardcoded WiFi version
├── api/                             ← PHP API endpoints (called by ESP32)
├── css/                             ← Stylesheet
├── image/                           ← Image assets
└── *.php / *.html                   ← Web dashboard pages
```

---

## 🔌 Arduino Firmware

There are **two versions** of the Arduino firmware, both located in the `arduino/` folder:

### 1. `C2D_EV_Kit_Master/` — Cloud WiFi (AutoConnect)

> **Use this version for flexible deployment across different networks.**

- Uses the [AutoConnect](https://hieromon.github.io/AutoConnect/) library to create a **captive portal** on first boot.
- The ESP32 broadcasts a WiFi access point named **`C2D_EV_Kit_AP`** (password: `12345678`).
- Connect to that AP from your phone/laptop, and a portal page will appear where you can **select and save your WiFi network**.
- Credentials are saved to flash memory — on future boots, the ESP32 connects automatically without needing the portal again.
- **No code changes needed** before flashing.

**Required library:** `AutoConnect` (install via Arduino Library Manager)

---

### 2. `C2D_EV_Kit_Master_Hardcoded/` — Hardcoded WiFi

> **Use this version when deploying on a known, fixed network.**

- Uses standard `WiFi.begin(ssid, password)` — **no captive portal, no extra libraries**.
- You must **edit the WiFi credentials directly in the `.ino` file** before flashing:

```cpp
// 🔧 Edit these two lines before flashing:
const char* WIFI_SSID = "YourWiFiName";
const char* WIFI_PASS = "YourWiFiPassword";
```

- If the WiFi connection fails (e.g. wrong password), the kit will display an error and continue running in **offline mode** (quiz still works, IoT sync disabled).
- Simpler and lighter — no AutoConnect dependency needed.

**Required library:** None (uses built-in ESP32 WiFi)

---

### Comparison

| Feature                        | Cloud WiFi (AutoConnect) | Hardcoded WiFi |
|-------------------------------|:------------------------:|:--------------:|
| WiFi credentials in code       | ❌ No                   | ✅ Yes         |
| Captive portal setup           | ✅ Yes                  | ❌ No          |
| Works across different networks| ✅ Easy                 | ⚠️ Reflash needed |
| Extra library required         | ✅ AutoConnect          | ❌ None        |
| Offline fallback if no WiFi    | ✅ Yes                  | ✅ Yes         |

---

## 🌐 Web Dashboard

The PHP web dashboard runs on a local server (Laragon + MySQL).

### Key Pages

| File | Description |
|------|-------------|
| `login.html` | User login page |
| `Home.php` | Main home page (logged-in) |
| `dashboard.php` | Learning progress dashboard |
| `module1.php` | Module 1: AC Home Charging |
| `module2.php` | Module 2: DC Fast Charging |
| `module3.php` | Module 3: Driving & Regenerative Braking |
| `pair-kit.php` | Kit pairing page |

### API Endpoints (`api/`)

| File | Called By | Description |
|------|-----------|-------------|
| `heartbeat.php` | Browser (JS) | Keep session alive |
| `kit-link.php` | Browser (JS) | Link kit to user account |
| `kit-announce.php` | ESP32 hardware | Kit announces itself on boot |
| `get-user-by-kit.php` | ESP32 hardware | Fetch user linked to this kit |
| `module-start.php` | ESP32 hardware | Notify server a module started |
| `quiz-submit.php` | ESP32 hardware | Submit quiz results |
| `update-nodes.php` | ESP32 hardware | Update LED node states on dashboard |

---

## 🛠️ Hardware

- **MCU:** ESP32
- **Display:** TFT LCD 320×240 (TFT_eSPI + XPT2046 touch)
- **LEDs:** WS2812B LED strip (FastLED)
- **Buzzer:** Passive buzzer on GPIO 25
- **IoT Broker:** HiveMQ Cloud (MQTT over TLS, port 8883)

---

## ⚙️ Setup

1. Flash the appropriate Arduino firmware (see above)
2. Set up Laragon with PHP & MySQL
3. Import the database schema
4. Visit `login.html` in your browser
5. Pair your ESP32 kit via `pair-kit.php`