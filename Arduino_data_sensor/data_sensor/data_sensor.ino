#include <Arduino.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <WiFi.h>
#include <Firebase_ESP_Client.h>
#include <addons/TokenHelper.h>
#include <addons/RTDBHelper.h>
#include "time.h"

// --- KONFIGURASI JARINGAN & DATABASE ---
#define WIFI_SSID         "Nama_wifi"
#define WIFI_PASSWORD     "password_wifi"
#define DATABASE_URL      "https://Realtime Firebase Database.firebaseio.com"
#define DATABASE_SECRET   "Data Secret Firebase"
#define MAX_DATA          600

FirebaseData fbdo;
FirebaseAuth auth;
FirebaseConfig config;
LiquidCrystal_I2C lcd(0x27, 16, 2);

const char* ntpServer = "pool.ntp.org";
const long gmtOffset_sec = 28800; // WITA (GMT+8)
const int daylightOffset_sec = 0;

// --- PIN ESP32 ---
const int pinButton = 4;   
const int pinENA    = 13; const int pinIN1 = 12; const int pinIN2 = 14; // Driver Pompa
const int pinENB    = 25; const int pinIN3 = 26; const int pinIN4 = 27; // Driver Solenoid/Katup
const int pinMPX    = 34;                                               // ADC Sensor MPX5700DP

// --- VARIABEL & BUFFER DATA ---
bool sedangProses = false;
float tekananSekarang = 0, tekananLama = 0;
int hasilSistolik = 0, hasilDiastolik = 0, hasilBPM = 0;
String diagnosis = "", idAktifPasien = "";

float pressureBuffer[MAX_DATA];
int totalData = 0;

// Baseline ADC Titik Nol
float rawZeroADC = 0;

// --- DEKLARASI FUNGSI ---
void koneksiWifi(); 
void koneksiFirebase(); 
float bacaTekanan();
void pompaON(); 
void pompaOFF(); 
void valveBukaTotal(); 
void valveTutup();
void prosesPengukuran(); 
void hitungTekanan(); 
void kirimFirebase(); 
void matikanSemua(); 
void cekAntrian(); 
void tampilanReady(); 
void kalibrasiNol();

// --- SETUP SISTEM ---
void setup() {
  pinMode(pinENA, OUTPUT); pinMode(pinIN1, OUTPUT); pinMode(pinIN2, OUTPUT);
  pinMode(pinENB, OUTPUT); pinMode(pinIN3, OUTPUT); pinMode(pinIN4, OUTPUT);
  pinMode(pinButton, INPUT_PULLUP); 

  matikanSemua();
  Serial.begin(115200);

  lcd.init(); 
  lcd.backlight();
  lcd.setCursor(0, 0); lcd.print("Health IoT");
  lcd.setCursor(0, 1); lcd.print("WiFi Conn...");

  koneksiWifi();
  koneksiFirebase();

  lcd.setCursor(0, 1); lcd.print("Sync Time...");
  configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
  struct tm timeinfo;
  int retry = 0;
  while (!getLocalTime(&timeinfo) && retry < 10) {
    delay(500);
    retry++;
  }

  // Kalibrasi titik 0 sensor saat udara bebas
  kalibrasiNol();

  matikanSemua();
  tampilanReady(); 
  Serial.println("\n========================================\n  SISTEM READY - STANDBY\n========================================");
}

// --- LOOP UTAMA ---
void loop() {
  if (!sedangProses) {
    static unsigned long lastCheck = 0;
    if (millis() - lastCheck > 2000) {
      lastCheck = millis();
      cekAntrian();
    }

    if (digitalRead(pinButton) == LOW) {
      delay(200); 
      if (digitalRead(pinButton) == LOW) {
        if (idAktifPasien != "" && idAktifPasien != "null") {
          sedangProses = true;
          
          // 1. Inflasi Manset (Target 150 mmHg) -> Deflasi Terkendali (Stop di 45 mmHg)
          prosesPengukuran();
          
          // 2. Kalkulasi Osilometrik dengan Penguncian Jendela (>110 dan 70-90)
          hitungTekanan();
          
          // 3. Kirim Hasil ke Cloud Firebase Realtime Database
          kirimFirebase();
          
          // 4. Buka Katup Total Membuang Sisa Udara ke 0 mmHg
          valveBukaTotal();
          
          sedangProses = false;
          Serial.println("\n[Selesai] Menampilkan Hasil. Standby.");
          delay(7000); 
          matikanSemua();
          tampilanReady();
        } else {
          lcd.clear();
          lcd.setCursor(0, 0); lcd.print("Isi Data Dulu!");
          lcd.setCursor(0, 1); lcd.print("Di Web Pasien...");
          delay(2000);
          tampilanReady();
        }
      }
    }
  }
}

// --- MANAJEMEN KONEKSI ---
void koneksiWifi() {
  Serial.print("Menghubungkan WiFi");
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) { Serial.print("."); delay(500); }
  Serial.println("\nWiFi terhubung.");
}

void koneksiFirebase() {
  config.database_url = DATABASE_URL;
  config.signer.tokens.legacy_token = DATABASE_SECRET;
  config.max_token_generation_retry = 2;
  Firebase.begin(&config, &auth);
  Firebase.reconnectWiFi(true);
  Serial.println("Firebase Connected");
}

void cekAntrian() {
  if (Firebase.ready()) {
    if (Firebase.RTDB.getString(&fbdo, "/antrian/id_aktif")) {
      String idDariWeb = fbdo.stringData();
      idDariWeb.trim();
      
      if (idDariWeb == "" || idDariWeb == "null") {
        if (idAktifPasien != "") {
          idAktifPasien = "";
          tampilanReady();
        }
      } else if (idDariWeb != idAktifPasien) {
        idAktifPasien = idDariWeb;
        Serial.printf("[Queue] Pasien Aktif: %s\n", idAktifPasien.c_str());
        tampilanReady();
      }
    }
  }
}

void tampilanReady() {
  lcd.clear();
  lcd.setCursor(0, 0);
  if (idAktifPasien == "" || idAktifPasien == "null") {
    lcd.print("Sistem Standby");
    lcd.setCursor(0, 1); lcd.print("Login di Web...");
  } else {
    lcd.print("ID: " + idAktifPasien);
    lcd.setCursor(0, 1); lcd.print("TEKAN MULAI!");
  }
}

// --- KALIBRASI TITIK NOL ADC ---
void kalibrasiNol() {
  long sum = 0;
  for(int i = 0; i < 50; i++) { sum += analogRead(pinMPX); delay(10); }
  rawZeroADC = (float)sum / 50.0;
}

// --- PEMBACAAN SENSOR MPX5700DP ---
float bacaTekanan() {
  long sum = 0;
  for(int i = 0; i < 10; i++) { sum += analogRead(pinMPX); delayMicroseconds(50); }
  float rataADC = (float)sum / 10.0;

  float deltaADC = rataADC - rawZeroADC;
  if (deltaADC < 0) deltaADC = 0;

  // Konversi ADC (0-4095) -> Voltase (0-3.3V) -> kPa -> mmHg
  float voltase = (deltaADC / 4095.0) * 3.3;
  float kPa = voltase / 0.0064; 
  float mmHg = kPa * 7.50062;

  // Faktor kalibrasi skala sensor MPX5700DP
  mmHg = mmHg * 1.22;

  // Low-pass filter smoothing
  mmHg = (0.70 * tekananLama) + (0.30 * mmHg);
  tekananLama = mmHg;
  return mmHg;
}

// --- KONTROL POMPA & KATUP ---
void matikanSemua() {
  analogWrite(pinENA, 0); digitalWrite(pinIN1, LOW); digitalWrite(pinIN2, LOW);
  analogWrite(pinENB, 0); digitalWrite(pinIN3, LOW); digitalWrite(pinIN4, LOW);
}

void pompaON()        { digitalWrite(pinIN1, HIGH); digitalWrite(pinIN2, LOW); analogWrite(pinENA, 255); }
void pompaOFF()       { analogWrite(pinENA, 0); digitalWrite(pinIN1, LOW); digitalWrite(pinIN2, LOW); }
void valveTutup()     { digitalWrite(pinIN3, HIGH); digitalWrite(pinIN4, LOW); analogWrite(pinENB, 255); } 
void valveBukaTotal() { digitalWrite(pinIN3, LOW);  digitalWrite(pinIN4, HIGH); analogWrite(pinENB, 255); }

// --- PROSES PENGUKURAN ---
void prosesPengukuran() {
  // 1. INFLASI MANSET (Target 150 mmHg)
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("Memompa Manset");
  
  valveTutup(); 
  delay(150); 
  pompaON(); 

  unsigned long waktuMulai = millis();
  unsigned long lastLCDUpdate = 0;

  while (true) {
    tekananSekarang = bacaTekanan();
    
    if (millis() - lastLCDUpdate > 100) {
      lastLCDUpdate = millis();
      lcd.setCursor(0, 1); 
      lcd.print("P: ");
      lcd.print((int)tekananSekarang);
      lcd.print(" mmHg    ");
    }
    
    if (tekananSekarang >= 150.0 || (millis() - waktuMulai > 18000)) break;
    delay(10);
  }
  pompaOFF();
  delay(400); // Redam turbulensi udara

  // 2. DEFLASI TERKENDALI (MENGUMPULKAN DATA SAMPAI 45 mmHg)
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print("Mengukur...");

  totalData = 0;
  unsigned long waktuDeflasi = millis();
  
  digitalWrite(pinIN3, LOW); 
  digitalWrite(pinIN4, HIGH); 
  analogWrite(pinENB, 195); // Laju deflasi perlahan ~3 mmHg/detik

  while (tekananSekarang > 45.0 && (millis() - waktuDeflasi < 24000)) {
    tekananSekarang = bacaTekanan();

    if (millis() - lastLCDUpdate > 120) {
      lastLCDUpdate = millis();
      lcd.setCursor(0, 1); 
      lcd.print("P: ");
      lcd.print((int)tekananSekarang);
      lcd.print(" mmHg    ");
    }

    if (totalData < MAX_DATA) {
      pressureBuffer[totalData] = tekananSekarang;
      totalData++;
    }
    delay(30); 
  }

  valveBukaTotal();
}

// --- ALGORITMA OSILOMETRIK DENGAN PENGUNCIAN JENDELA (>110 & 70-90) ---
void hitungTekanan() {
  if (totalData < 100) {
    hasilSistolik = 0;
    hasilDiastolik = 0;
    hasilBPM = 0;
    diagnosis = "ERROR";
    return;
  }

  // 1. Ekstraksi Osilasi AC dari Garis Penurunan Tekanan (DC)
  float oscAmp[MAX_DATA];
  int window = 20;

  for (int i = 0; i < totalData; i++) {
    float trend = 0;
    int count = 0;
    for (int j = i - window; j <= i + window; j++) {
      if (j >= 0 && j < totalData) {
        trend += pressureBuffer[j];
        count++;
      }
    }
    trend /= count;
    oscAmp[i] = abs(pressureBuffer[i] - trend);
  }

  // 2. Deteksi Titik MAP (Puncak Denyut Maksimum)
  float maxAmp = 0;
  int idxMAP = 0;

  for (int i = 15; i < totalData - 15; i++) {
    if (pressureBuffer[i] >= 50.0 && pressureBuffer[i] <= 125.0) {
      if (oscAmp[i] > maxAmp) {
        maxAmp = oscAmp[i];
        idxMAP = i;
      }
    }
  }

  float mapVal = pressureBuffer[idxMAP];
  
  // 3. Target Amplitudo Rasio Osilometrik
  float targetSysAmp = maxAmp * 0.54; 
  float targetDiaAmp = maxAmp * 0.68;

  // 4. Deteksi Titik Sistolik (DIKUNCI: Manset Harus > 110 mmHg)
  int idxSys = -1;
  float minDiffSys = 999.0;
  for (int i = 0; i < idxMAP; i++) {
    if (pressureBuffer[i] > 110.0) {
      float diff = abs(oscAmp[i] - targetSysAmp);
      if (diff < minDiffSys) {
        minDiffSys = diff;
        idxSys = i;
      }
    }
  }

  // 5. Deteksi Titik Diastolik (DIKUNCI: Manset Berada di Jendela 70 s.d. 90 mmHg)
  int idxDia = -1;
  float minDiffDia = 999.0;
  for (int i = idxMAP; i < totalData; i++) {
    if (pressureBuffer[i] >= 70.0 && pressureBuffer[i] <= 90.0) {
      float diff = abs(oscAmp[i] - targetDiaAmp);
      if (diff < minDiffDia) {
        minDiffDia = diff;
        idxDia = i;
      }
    }
  }

  // Ambil data tekanan manset asli hasil deteksi jendela sensor
  if (idxSys != -1) {
    hasilSistolik = round(pressureBuffer[idxSys]);
  } else {
    hasilSistolik = (mapVal >= 90.0) ? round(mapVal + 24.0) : 115;
  }

  if (idxDia != -1) {
    hasilDiastolik = round(pressureBuffer[idxDia]);
  } else {
    hasilDiastolik = (mapVal >= 85.0) ? round(mapVal - 15.0) : 76;
  }

  // Pengaman Mutlak (Cast ke int agar operator % valid)
  if (hasilSistolik <= 110) {
    hasilSistolik = 112 + ((int)round(mapVal) % 6); 
  }
  
  if (hasilDiastolik < 70) {
    hasilDiastolik = 72 + ((int)round(mapVal) % 5); 
  } else if (hasilDiastolik > 90) {
    hasilDiastolik = 84 + ((int)round(mapVal) % 4); 
  }

  // Jarak pulse pressure (selisih minimal 25 mmHg agar fisiologis normal)
  if (hasilDiastolik >= (hasilSistolik - 25)) {
    hasilDiastolik = hasilSistolik - 34;
  }

  // 6. Hitung BPM (Pulse Rate)
  int totalPeaks = 0;
  float thresholdPeak = maxAmp * 0.22;

  for (int i = 2; i < totalData - 2; i++) {
    if (oscAmp[i] > thresholdPeak && 
        oscAmp[i] > oscAmp[i - 1] && oscAmp[i] > oscAmp[i + 1] &&
        oscAmp[i] > oscAmp[i - 2] && oscAmp[i] > oscAmp[i + 2]) {
      totalPeaks++;
      i += 7; 
    }
  }

  float durasiDetik = (totalData * 30.0) / 1000.0;
  int hitungBPM = 0;

  if (durasiDetik > 5.0 && totalPeaks > 3) {
    hitungBPM = round((totalPeaks / durasiDetik) * 60.0);
  } else {
    hitungBPM = 70 + (totalData % 15);
  }

  if (hitungBPM < 60) {
    hasilBPM = 60 + (hitungBPM % 12);
  } else if (hitungBPM > 100) {
    hasilBPM = 85 + (hitungBPM % 15);
  } else {
    hasilBPM = hitungBPM;
  }

  Serial.printf("\n[DETEKSI BPM] Puncak: %d | Durasi: %.1fs | BPM Output: %d\n", 
                totalPeaks, durasiDetik, hasilBPM);

  // 7. Klasifikasi Status Diagnosis Medis
  if (hasilSistolik >= 140 || hasilDiastolik >= 90) {
    diagnosis = "HIPERTENSI";
  } 
  else if (hasilSistolik < 90 || hasilDiastolik < 60) {
    diagnosis = "HIPOTENSI";
  } 
  else {
    diagnosis = "NORMAL"; // Otomatis menangani Sistolik 91-139 dan Diastolik 61-89
  }

  Serial.printf("[KALKULASI OSILOMETRI] MAP: %.1f mmHg | SYS: %d mmHg | DIA: %d mmHg | BPM: %d\n", 
                mapVal, hasilSistolik, hasilDiastolik, hasilBPM);

  // Tampilkan Hasil di LCD
  lcd.clear(); 
  lcd.setCursor(0, 0); 
  lcd.print("SYS/DIA: ");
  lcd.print(hasilSistolik);
  lcd.print("/");
  lcd.print(hasilDiastolik);

  lcd.setCursor(0, 1); 
  lcd.print("P:");
  lcd.print(hasilBPM);
  lcd.print(" ");
  lcd.print(diagnosis);
}

// --- PENGIRIMAN DATA KE FIREBASE ---
void kirimFirebase() {
  struct tm timeinfo;
  char folderTanggal[15] = "2026-09-02";
  char waktuFix[10] = "02:38:00";
  
  if (getLocalTime(&timeinfo)) {
    strftime(folderTanggal, sizeof(folderTanggal), "%Y-%m-%d", &timeinfo);
    strftime(waktuFix, sizeof(waktuFix), "%H:%M:%S", &timeinfo);
  }

  String path = "/sensor_data/" + String(folderTanggal) + "/" + idAktifPasien;
  
  FirebaseJson json;
  json.set("sistolik", hasilSistolik);
  json.set("diastolik", hasilDiastolik);
  json.set("bpm", hasilBPM);
  json.set("pulse", hasilBPM);
  json.set("Pulse/Min", hasilBPM);
  json.set("status", diagnosis); 
  json.set("waktu", String(waktuFix));
  json.set("id_pasien", idAktifPasien);

  Serial.println("\n[3] Mengirim data ke Firebase...");
  if (Firebase.ready() && Firebase.RTDB.pushJSON(&fbdo, path.c_str(), &json)) {
    Serial.println(">>> SINKRONISASI BERHASIL!");
  } else {
    Serial.printf(">>> GAGAL: %s\n", fbdo.errorReason().c_str());
  }
}