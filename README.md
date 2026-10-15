# 🩺 Rancang Bangun Alat Pengukur Tekanan Darah Menggunakan Sensor MPX5700DP Berbasis Internet of Things

## 📌 Deskripsi Proyek

Proyek ini merupakan implementasi tugas akhir yang bertujuan untuk merancang dan membangun alat pengukur tekanan darah otomatis menggunakan sensor **MPX5700DP** berbasis Internet of Things (IoT).

Sistem menggunakan ESP32 sebagai mikrokontroler utama untuk mengendalikan proses pengukuran tekanan darah dengan metode osilometrik. Hasil pengukuran berupa tekanan sistolik, tekanan diastolik, dan denyut nadi ditampilkan melalui LCD I2C 16×2 serta dikirimkan secara real-time ke Firebase Realtime Database untuk ditampilkan pada website mandiri.

Pengembangan sistem ini diharapkan dapat memudahkan pemantauan tekanan darah dan pengelolaan riwayat hasil pengukuran secara digital.

## 🎯 Tujuan Proyek

- Merancang dan membangun alat pengukur tekanan darah otomatis menggunakan sensor MPX5700DP.
- Mengimplementasikan ESP32 sebagai pengendali utama sistem.
- Menerapkan metode osilometrik untuk pengolahan hasil pengukuran tekanan darah.
- Mengintegrasikan perangkat dengan Firebase Realtime Database.
- Mengembangkan website mandiri untuk menampilkan hasil dan riwayat pengukuran.
- Menguji hasil pengukuran prototipe dengan membandingkannya terhadap tensimeter digital.

## 🛠️ Komponen Perangkat Keras

| No. | Komponen | Fungsi |
|---|---|---|
| 1 | ESP32 | Mengendalikan sistem dan komunikasi Wi-Fi |
| 2 | Sensor MPX5700DP | Membaca tekanan pada manset |
| 3 | Pompa udara DC 6 V | Mengisi udara ke dalam manset |
| 4 | Solenoid valve 6 V | Mengatur pelepasan udara dari manset |
| 5 | Motor driver L298N | Mengendalikan pompa dan solenoid sesuai rangkaian |
| 6 | LCD I2C 16×2 | Menampilkan hasil pengukuran |
| 7 | Push button | Memulai proses pengukuran |
| 8 | Manset tekanan darah | Memberikan tekanan pada lengan |
| 9 | PCB | Menghubungkan jalur rangkaian elektronik |
| 10 | Adaptor 5 V 2 A | Sumber daya sistem |

## 💻 Teknologi yang Digunakan

- **Arduino IDE** — pemrograman mikrokontroler ESP32.
- **C/C++** — pengembangan firmware perangkat.
- **Firebase Realtime Database** — penyimpanan dan sinkronisasi data pengukuran.
- **PHP** — pengembangan sisi server website.
- **JavaScript** — pengolahan data dan integrasi Firebase.
- **Chart.js** — visualisasi grafik riwayat pengukuran.
- **HTML dan CSS** — struktur dan tampilan website.
- **Visual Studio Code** — pengembangan website mandiri.

## ⚙️ Cara Kerja Sistem

1. ESP32 melakukan inisialisasi sensor, LCD, motor driver, pompa, solenoid valve, dan koneksi Wi-Fi.
2. Pengguna memasukkan data pasien melalui website.
3. Manset dipasang pada lengan, kemudian pengguna menekan push button untuk memulai pengukuran.
4. Pompa mengisi udara ke dalam manset hingga mencapai tekanan target yang ditentukan dalam program.
5. Solenoid valve mengatur pelepasan tekanan secara bertahap.
6. Sensor MPX5700DP membaca perubahan tekanan dan osilasi yang digunakan dalam proses perhitungan tekanan sistolik, diastolik, serta denyut nadi.
7. Hasil pengukuran ditampilkan pada LCD I2C 16×2.
8. Data pengukuran dikirimkan ke Firebase Realtime Database melalui koneksi Wi-Fi.
9. Website menampilkan hasil pengukuran dan riwayat data yang tersimpan.

## ☁️ Integrasi Internet of Things

Sistem menggunakan Firebase Realtime Database untuk menyimpan dan memperbarui data pengukuran. Data yang dikelola meliputi:

- Identitas dan informasi pasien.
- Tekanan darah sistolik (SYS).
- Tekanan darah diastolik (DIA).
- Denyut nadi (BPM).
- Waktu pengukuran.
- Status hasil pengukuran sesuai logika program.

Website mandiri menyediakan dashboard untuk menampilkan informasi pasien, hasil pengukuran, status koneksi Firebase, dan grafik riwayat pengukuran.

## 📊 Pengujian Sistem

Pengujian dilakukan untuk mengevaluasi fungsi perangkat keras, pengolahan hasil pengukuran, komunikasi ESP32 dengan Firebase, serta tampilan data pada LCD dan website.

Pengujian pengukuran dilakukan dengan membandingkan hasil prototipe dengan tensimeter digital. Parameter yang dibandingkan meliputi:

- Tekanan sistolik (mmHg).
- Tekanan diastolik (mmHg).
- Denyut nadi (BPM).

Persentase kesalahan pengukuran dihitung menggunakan rumus:

\[
\text{Error}(\%) =
\frac{|\text{Nilai Prototipe}-\text{Nilai Referensi}|}
{\text{Nilai Referensi}}\times100\%
\]

Hasil evaluasi digunakan untuk mengetahui perbedaan pengukuran antara prototipe dan alat pembanding.

## 📂 Struktur Repository

Struktur direktori berikut merupakan contoh yang dapat disesuaikan dengan berkas proyek yang diunggah.

```text
blood-pressure-monitor-iot/
├── firmware/
│   └── blood_pressure_monitor.ino
├── website/
│   ├── index.php
│   ├── assets/
│   ├── css/
│   └── js/
├── documentation/
│   ├── block-diagram.png
│   ├── circuit-diagram.png
│   └── prototype.jpg
└── README.md
```

## 🚀 Instalasi dan Konfigurasi

### 1. Firmware ESP32

1. Instal Arduino IDE.
2. Tambahkan dukungan board ESP32.
3. Instal library yang diperlukan, termasuk library LCD I2C dan Firebase yang sesuai dengan firmware.
4. Buka berkas program ESP32 menggunakan Arduino IDE.
5. Sesuaikan konfigurasi Wi-Fi dan Firebase.
6. Pilih board ESP32 yang sesuai, kemudian unggah program ke perangkat.

### 2. Website Mandiri

1. Siapkan lingkungan server PHP, misalnya XAMPP.
2. Salin berkas website ke direktori server lokal.
3. Konfigurasikan Firebase Web SDK menggunakan konfigurasi proyek Firebase milik sendiri.
4. Sesuaikan struktur database dengan format data yang digunakan firmware.
5. Jalankan website melalui server lokal atau server yang telah dikonfigurasi.

**Catatan:** Jangan mengunggah kata sandi Wi-Fi, private key, service account Firebase, atau kredensial rahasia lainnya ke repository publik.

## 🔮 Pengembangan Selanjutnya

- Penyempurnaan algoritma pengolahan tekanan sistolik dan diastolik.
- Pengujian dengan jumlah responden yang lebih banyak.
- Evaluasi pengukuran pada variasi posisi tubuh.
- Peningkatan keamanan akses dan pengelolaan data.
- Penyempurnaan tampilan dashboard dan visualisasi riwayat pengukuran.

## ⚠️ Disclaimer

Proyek ini merupakan prototipe penelitian untuk keperluan akademik dan pengembangan teknologi. Hasil pengukuran belum dapat dianggap sebagai pengganti alat medis yang tervalidasi atau sebagai dasar diagnosis klinis. Penggunaan untuk kebutuhan medis memerlukan evaluasi dan validasi lebih lanjut sesuai standar yang berlaku.

## 👨‍💻 Author

**Haerul Firman Saputra**

Mahasiswa Jurusan Teknologi Informasi  
Politeknik Negeri Samarinda

Topik penelitian: Sistem Pengukuran Tekanan Darah Berbasis Internet of Things.

---

*Dikembangkan sebagai bagian dari penelitian tugas akhir di bidang Internet of Things dan sistem tertanam.*
