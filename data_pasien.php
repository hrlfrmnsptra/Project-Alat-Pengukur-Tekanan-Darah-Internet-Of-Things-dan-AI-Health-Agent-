<?php
session_start();
if (isset($_POST['ajax_login'])) {
    $_SESSION['status'] = "login";
    $_SESSION['id_pengukuran'] = $_POST['id'];
    $_SESSION['nama_pasien'] = $_POST['nama'];
    // Update session tanggal agar Dashboard membaca folder yang benar
    $_SESSION['tanggal_pilih'] = $_POST['tanggal']; 
    echo "ok"; exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health IoT - Data Pasien</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #F1F5F9; 
            color: #1E293B;
            min-height: 100vh; 
            overflow-x: hidden; 
        }
        
        .glass-card { 
            background: rgba(255, 255, 255, 0.95); 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05); 
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        }

        .input-style { background: rgba(241, 245, 249, 0.7); border: 1.5px solid #e2e8f0; transition: all 0.3s ease; }
        .input-style:focus { background: white; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); outline: none; transform: translateY(-2px); }
        
        .btn-gradient { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); transition: all 0.3s ease; }
        .btn-gradient:hover { transform: translateY(-3px); box-shadow: 0 12px 20px -8px rgba(37, 99, 235, 0.6); }
        .btn-gradient:active { transform: scale(0.96); }
        
        .detected-pulse { animation: pulse-blue 2s infinite; border-color: #3b82f6 !important; }
        @keyframes pulse-blue { 0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); } 100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); } }

        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { opacity: 0; animation: fadeUp 0.7s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        #info_teks { transition: all 0.3s ease; }
    </style>
</head>
<body class="flex items-center justify-center p-4 md:p-8">

    <div class="glass-card w-full max-w-lg p-8 md:p-12 rounded-[2.5rem] relative overflow-hidden animate-fade-up">
        <div class="text-center mb-10">
            <div class="inline-flex bg-blue-600 p-4 rounded-2xl shadow-lg mb-6 transform -rotate-3 hover:rotate-0 transition-all duration-300">
                <svg class="w-8 h-8 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 022 2h2a2 2 0 002-2M9 5a2 2 0 022 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight animate-fade-up delay-1">Data Pasien</h2>
            <p class="text-slate-500 text-sm mt-2 font-medium animate-fade-up delay-1" id="info_teks">
                <i class="fas fa-info-circle mr-1"></i> Lengkapi informasi untuk memulai pemeriksaan
            </p>
        </div>

        <form id="formPasien" class="space-y-6" autocomplete="off">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 animate-fade-up delay-2">
                <div class="space-y-2">
                    <label class="text-slate-500 text-[11px] uppercase font-bold tracking-widest ml-1">Tanggal Pemeriksaan</label>
                    <input type="date" id="tanggal_pilih" required class="input-style w-full rounded-2xl py-3.5 px-5 text-slate-900">
                </div>
                <div class="space-y-2 relative">
                    <label class="text-slate-500 text-[11px] uppercase font-bold tracking-widest ml-1">ID Pengukuran</label>
                    <input type="text" id="id_pengukuran" required class="input-style w-full rounded-2xl py-3.5 px-5 text-slate-900 uppercase" placeholder="001">
                    <div id="check_icon" class="absolute right-4 top-10 text-emerald-500 opacity-0 transition-opacity">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="space-y-2 animate-fade-up delay-3">
                <label class="text-slate-500 text-[11px] uppercase font-bold tracking-widest ml-1">Nama Lengkap Pasien</label>
                <input type="text" id="nama_pengguna" required class="input-style w-full rounded-2xl py-3.5 px-5 text-slate-900" placeholder="Ketik nama lengkap...">
            </div>

            <div class="grid grid-cols-2 gap-5 animate-fade-up delay-4">
                <div class="space-y-2">
                    <label class="text-slate-500 text-[11px] uppercase font-bold tracking-widest ml-1">Umur</label>
                    <input type="number" id="umur" class="input-style w-full rounded-2xl py-3.5 px-5 text-slate-900" placeholder="0">
                </div>
                <div class="space-y-2">
                    <label class="text-slate-500 text-[11px] uppercase font-bold tracking-widest ml-1">Gender</label>
                    <div class="relative">
                        <select id="jenis_kelamin" class="input-style w-full rounded-2xl py-3.5 px-5 text-slate-900 appearance-none cursor-pointer">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                        <div class="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" id="btn_submit" class="btn-gradient w-full text-white font-bold py-4 rounded-2xl mt-4 flex items-center justify-center gap-3 animate-fade-up delay-4 overflow-hidden relative">
                <span id="btn_text" class="tracking-wide uppercase z-10 flex items-center gap-2">
                    Masuk Dashboard <i class="fas fa-arrow-right text-sm"></i>
                </span>
            </button>
        </form>
    </div>

<script>
document.getElementById('tanggal_pilih').valueAsDate = new Date();
const firebaseConfig = { apiKey: "Apikey Firebase", databaseURL: "https://Realtime Firebase Database.firebaseio.com", projectId: "Project Tekanan darah IoT" };
firebase.initializeApp(firebaseConfig);
const db = firebase.database();

const idInput = document.getElementById('id_pengukuran');
const inputNama = document.getElementById('nama_pengguna');
const inputTanggal = document.getElementById('tanggal_pilih');
const infoTeks = document.getElementById('info_teks');
const checkIcon = document.getElementById('check_icon');

function deteksiData() {
    const tgl = inputTanggal.value;
    const id = idInput.value.toUpperCase().trim();

    if (tgl && id) {
        db.ref('pasien/' + id).once('value', (snapMaster) => {
            infoTeks.style.opacity = 0;
            
            setTimeout(() => {
                if (snapMaster.exists()) {
                    const dataMaster = snapMaster.val();
                    
                    inputNama.value = dataMaster.nama || "";
                    document.getElementById('umur').value = dataMaster.umur || "";
                    document.getElementById('jenis_kelamin').value = dataMaster.gender || "L";
                    
                    db.ref('sensor_data/' + tgl + '/' + id).once('value', (snapTanggal) => {
                        if (snapTanggal.exists()) {
                            infoTeks.innerHTML = `<i class="fas fa-check-circle text-emerald-500 mr-1"></i> Data kunjungan <b>${dataMaster.nama}</b> hari ini ditemukan.`;
                            infoTeks.className = "text-emerald-600 text-sm mt-2 font-bold";
                        } else {
                            infoTeks.innerHTML = `<i class="fas fa-history text-indigo-500 mr-1"></i> Pasien lama: <b>${dataMaster.nama}</b> (Kunjungan baru di tanggal ini).`;
                            infoTeks.className = "text-indigo-600 text-sm mt-2 font-bold";
                        }
                    });

                    idInput.classList.add('detected-pulse');
                    checkIcon.style.opacity = 1;

                } else {
                    inputNama.value = "";
                    document.getElementById('umur').value = "";
                    document.getElementById('jenis_kelamin').value = "L";
                    
                    infoTeks.innerHTML = `<i class="fas fa-user-plus text-blue-500 mr-1"></i> ID <b>${id}</b> belum terdaftar. Silakan isi data baru.`;
                    infoTeks.className = "text-blue-600 text-sm mt-2 font-bold";
                    idInput.classList.remove('detected-pulse');
                    checkIcon.style.opacity = 0;
                }
                infoTeks.style.opacity = 1;
            }, 200);
        });
    } else {
        infoTeks.innerHTML = `<i class="fas fa-info-circle mr-1"></i> Lengkapi informasi untuk memulai pemeriksaan`;
        infoTeks.className = "text-slate-500 text-sm mt-2 font-medium";
        idInput.classList.remove('detected-pulse');
        checkIcon.style.opacity = 0;
    }
}

idInput.addEventListener('input', deteksiData);
inputTanggal.addEventListener('change', deteksiData);

document.getElementById('formPasien').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btn_submit');
    const btnText = document.getElementById('btn_text');
    btn.classList.add('opacity-80', 'cursor-not-allowed');
    btnText.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Memproses...`;

    const id = idInput.value.toUpperCase().trim();
    const nama = inputNama.value;
    const tanggal = inputTanggal.value;
    const umur = document.getElementById('umur').value;
    const gender = document.getElementById('jenis_kelamin').value;

    // 1. Update ke database master pasien
    db.ref('pasien/' + id).update({ nama, umur, gender });
    
    // 2. Set antrean aktif untuk hardware ESP32
    db.ref('antrian/id_aktif').set(id);

    // Menggunakan .update() untuk memperbarui metadata tanpa menimpa data sensor
    db.ref('sensor_data/' + tanggal + '/' + id).update({
        nama: nama,
        id_pasien: id,
        umur: umur,
        gender: gender,
        last_login: new Date().toLocaleTimeString()
    });

    let fd = new FormData();
    fd.append('ajax_login', '1');
    fd.append('id', id);
    fd.append('nama', nama);
    fd.append('tanggal', tanggal); 

    fetch('data_pasien.php', { method: 'POST', body: fd })
    .then(response => response.text())
    .then(data => { 
        if(data.trim() === "ok") {
            window.location.href = "dashboard.php"; 
        } else {
            btn.classList.remove('opacity-80', 'cursor-not-allowed');
            btnText.innerHTML = `Masuk Dashboard <i class="fas fa-arrow-right text-sm"></i>`;
        }
    });
});
</script>
</body>
</html>