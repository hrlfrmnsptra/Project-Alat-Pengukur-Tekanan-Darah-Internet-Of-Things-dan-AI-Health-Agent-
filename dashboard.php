<?php
session_start();

// =====================================================================
// BLOK API HANDLER AI (GROQ)
// =====================================================================
$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['action']) && $input['action'] === 'chat_ai') {
    header('Content-Type: application/json');
    
    $apiKey = 'Apikey Website Groq AI'; 
    $userMsg = $input['message'] ?? '';
    $context = $input['context'] ?? '';

    $systemPrompt = "Anda adalah AI Health Agent cerdas dalam dashboard IoT pemantau tensi darah.\n" .
                    "Data hasil ukur real-time pasien: " . $context . ".\n\n" .
                    "ATURAN KLASIFIKASI TEKANAN DARAH:\n" .
                    "- Hipotensi: <=90 / <=60 mmHg\n" .
                    "- Normal: 91-139 / 61-89 mmHg\n" .
                    "- Hipertensi: >=140 / >=90 mmHg\n\n" .
                    "ATURAN MUTLAK MENJAWAB:\n" .
                    "1. WAJIB 100% Bahasa Indonesia.\n" .
                    "2. Sangat singkat, to-the-point, dan berikan REKOMENDASI TINDAKAN FISIK/MANDIRI yang detail dan praktis bagi pasien saat ini.\n" .
                    "3. Format WAJIB dalam poin-poin (bullet) terstruktur:\n" .
                    "   * **Status Medis:** Evaluasi singkat angka hasil ukur (Sistolik/Diastolik/BPM).\n" .
                    "   * **Rekomendasi Tindakan Pasien:** Langkah langsung (posisi duduk tegak rileks, relaksasi napas 5 detik, minum 1 gelas air hangat, istirahat tanpa gadget 10-15 menit).\n" .
                    "   * **Instruksi Lanjutan:** Kapan ukur ulang dengan alat IoT atau periksa lanjutan.\n" .
                    "4. DILARANG membuat teks bertele-tele atau peringatan medis panjang berulang.";

    $data = [
        'model' => 'openai/gpt-oss-20b',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMsg]
        ],
        'temperature' => 0.2,
        'presence_penalty' => 0.3,
        'frequency_penalty' => 0.3,
        'max_tokens' => 750
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo json_encode([
            'choices' => [
                ['message' => ['content' => 'cURL Error: ' . curl_error($ch)]]
            ]
        ]);
    } else {
        $resJson = json_decode($response, true);
        if ($httpCode !== 200 && isset($resJson['error']['message'])) {
            echo json_encode([
                'choices' => [
                    ['message' => ['content' => 'Groq Error (' . $httpCode . '): ' . $resJson['error']['message']]]
                ]
            ]);
        } else {
            echo $response;
        }
    }
    curl_close($ch);
    exit(); 
}
// =====================================================================

date_default_timezone_set('Asia/Makassar');
$tanggal_pilih = $_SESSION['tanggal_pilih'] ?? date('Y-m-d');

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:data_pasien.php");
    exit();
}

$id_aktif = isset($_SESSION['id_pengukuran']) ? trim($_SESSION['id_pengukuran']) : '';
$nama_tampilan = $_SESSION['nama_pasien'] ?? 'Pasien Umum';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health IoT Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%); 
            color: #1E293B; 
            min-height: 100vh;
        }
        .glass-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(10px); 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05); 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .glass-card:hover { 
            transform: translateY(-5px) scale(1.01); 
            box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.1); 
        }
        .stat-value { letter-spacing: -2px; color: #0F172A; }
        .pulse-green { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); animation: pulse 2s infinite; }
        @keyframes pulse { 
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5); } 
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); } 
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } 
        }
        .sidebar-active { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3); }
        .chat-container { height: 320px; overflow-y: auto; padding-right: 8px; scroll-behavior: smooth; }
        .chat-container::-webkit-scrollbar { width: 4px; }
        .chat-container::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
        @keyframes popIn { 
            0% { opacity: 0; transform: translateY(10px) scale(0.95); } 
            100% { opacity: 1; transform: translateY(0) scale(1); } 
        }
        .bubble-ai { 
            background: #EFF6FF; color: #1E40AF; border-radius: 20px; border-top-left-radius: 4px; 
            padding: 12px 16px; font-size: 13px; font-weight: 600; border: 1px solid #DBEAFE; 
            margin-bottom: 12px; line-height: 1.5; 
            animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
        }
        .bubble-user { 
            background: #2563EB; color: #FFFFFF; border-radius: 20px; border-bottom-right-radius: 4px; 
            padding: 12px 16px; font-size: 13px; font-weight: 700; margin-bottom: 12px; 
            margin-left: auto; width: fit-content; max-width: 85%; 
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2); 
            animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
        }
        @keyframes fadeUp { 
            0% { opacity: 0; transform: translateY(20px); } 
            100% { opacity: 1; transform: translateY(0); } 
        }
        .animate-fade-up { opacity: 0; animation: fadeUp 0.6s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }
        .btn-send { transition: all 0.2s ease; }
        .btn-send:active { transform: scale(0.9); }
    </style>
</head>
<body class="pb-24 md:pb-0">

    <aside class="w-72 bg-white/80 backdrop-blur-xl min-h-screen border-r border-slate-200 hidden md:block fixed left-0 top-0 z-50 animate-fade-up">
        <div class="p-8">
            <div class="flex items-center gap-3 mb-10">
                <div class="bg-blue-600 p-2.5 rounded-2xl shadow-lg shadow-blue-500/30 transition-transform hover:rotate-12">
                    <i class="fas fa-heartbeat text-white text-xl"></i>
                </div>
                <h1 class="text-slate-900 font-extrabold text-2xl tracking-tight">Health <span class="text-blue-600"> IoT </span></h1>
            </div>
            <nav class="space-y-2">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-[2px] mb-4 pl-4">Menu Utama</p>
                <a href="dashboard.php" class="flex items-center gap-4 p-4 text-white sidebar-active rounded-2xl transition duration-300">
                    <i class="fas fa-th-large w-5 text-center text-lg"></i> 
                    <span class="font-bold">Dashboard</span>
                </a>
                <a href="riwayat_medis.php" class="flex items-center gap-4 p-4 text-slate-500 hover:bg-slate-50 hover:text-blue-600 rounded-2xl transition duration-300 group">
                    <i class="fas fa-history w-5 text-center group-hover:scale-110 transition-transform"></i> 
                    <span class="font-semibold">Riwayat Medis</span>
                </a>
                <div class="pt-10 border-t border-slate-100 mt-10">
                    <a href="logout.php" class="flex items-center gap-4 p-4 text-red-500 hover:bg-red-50 rounded-2xl transition duration-300 font-bold group">
                        <i class="fas fa-sign-out-alt w-5 text-center group-hover:translate-x-1 transition-transform"></i> 
                        <span>Keluar Sistem</span>
                    </a>
                </div>
            </nav>
        </div>
    </aside>

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-md border-t border-slate-200 px-6 py-4 z-[100] flex justify-between items-center shadow-2xl">
        <a href="dashboard.php" class="flex flex-col items-center text-blue-600">
            <i class="fas fa-th-large text-xl mb-1"></i>
            <span class="text-[10px] font-bold uppercase">Dashboard</span>
        </a>
        <a href="riwayat_medis.php" class="flex flex-col items-center text-slate-400">
            <i class="fas fa-history text-xl mb-1"></i>
            <span class="text-[10px] font-bold uppercase">Riwayat</span>
        </a>
        <a href="logout.php" class="flex flex-col items-center text-red-400">
            <i class="fas fa-sign-out-alt text-xl mb-1"></i>
            <span class="text-[10px] font-bold uppercase">Keluar</span>
        </a>
    </div>  

    <main class="flex-1 md:ml-72 p-4 md:p-10">
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10 animate-fade-up">
            <div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 mb-1 leading-tight">Halo, <?php echo $nama_tampilan; ?></h2>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="bg-blue-50 text-blue-600 text-[10px] px-3 py-1 rounded-lg font-black border border-blue-100 uppercase tracking-wider transition-all hover:bg-blue-100">ID: <?php echo $id_aktif; ?></span>
                    <span class="bg-slate-50 text-slate-600 text-[10px] px-3 py-1 rounded-lg font-black border border-slate-100 uppercase tracking-wider transition-all hover:bg-slate-100">TGL: <?php echo $tanggal_pilih; ?></span>
                    <span id="label_umur" class="hidden bg-emerald-50 text-emerald-600 text-[10px] px-3 py-1 rounded-lg font-black border border-emerald-100 uppercase tracking-wider"></span>
                    <span id="label_gender" class="hidden bg-indigo-50 text-indigo-600 text-[10px] px-3 py-1 rounded-lg font-black border border-indigo-100 uppercase tracking-wider"></span>
                </div>
            </div>
            <div class="flex items-center gap-4 bg-white p-3 px-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                <div id="status_online" class="w-2.5 h-2.5 bg-emerald-500 rounded-full pulse-green"></div>
                <span class="text-[11px] font-black text-slate-700 tracking-widest uppercase">Firebase Connection</span>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="glass-card p-7 rounded-[2.5rem] relative border-t-4 border-t-blue-500 animate-fade-up delay-1">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-blue-50 rounded-2xl text-blue-600"><i class="fas fa-arrow-trend-up text-lg"></i></div>
                    <h3 class="text-slate-400 text-[11px] font-black uppercase tracking-widest">Sistolik</h3>
                </div>
                <div class="flex items-end gap-2">
                    <p id="sis_val" class="stat-value text-5xl font-extrabold text-slate-900">0</p>
                    <span class="text-slate-400 font-bold mb-2 text-sm">mmHg</span>
                </div>
            </div>

            <div class="glass-card p-7 rounded-[2.5rem] relative border-t-4 border-t-emerald-500 animate-fade-up delay-2">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-emerald-50 rounded-2xl text-emerald-600"><i class="fas fa-arrow-trend-down text-lg"></i></div>
                    <h3 class="text-slate-400 text-[11px] font-black uppercase tracking-widest">Diastolik</h3>
                </div>
                <div class="flex items-end gap-2">
                    <p id="dia_val" class="stat-value text-5xl font-extrabold text-slate-900">0</p>
                    <span class="text-slate-400 font-bold mb-2 text-sm">mmHg</span>
                </div>
            </div>

            <div class="glass-card p-7 rounded-[2.5rem] relative border-t-4 border-t-rose-500 animate-fade-up delay-3">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-rose-50 rounded-2xl text-rose-600"><i class="fas fa-heartbeat text-lg animate-pulse"></i></div>
                    <h3 class="text-slate-400 text-[11px] font-black uppercase tracking-widest">Pulse/Min</h3>
                </div>
                <div class="flex items-end gap-2">
                    <p id="bpm_val" class="stat-value text-5xl font-extrabold text-slate-900">0</p>
                    <span class="text-slate-400 font-bold mb-2 text-sm">BPM</span>
                </div>
            </div>

            <div class="glass-card p-7 rounded-[2.5rem] relative border-l-4 border-l-slate-300 animate-fade-up delay-4" id="status_container">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 bg-slate-100 rounded-2xl transition-colors" id="status_icon_bg">
                        <i class="fas fa-stethoscope text-slate-500 text-lg" id="status_icon"></i>
                    </div>
                    <h3 class="text-slate-400 text-[11px] font-black uppercase tracking-widest">Diagnosis</h3>
                </div>
                <p id="status_text" class="text-2xl font-black text-slate-700 transition-colors uppercase">Menunggu...</p>
                <p class="text-slate-400 text-[10px] mt-2 font-bold uppercase tracking-tighter">Berdasarkan Data</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
            <div class="glass-card p-5 md:p-8 rounded-[2.5rem] lg:col-span-2 overflow-hidden animate-fade-up delay-5">
                <h3 class="font-extrabold text-xl text-slate-900 flex items-center gap-3 mb-8">
                    <span class="w-1.5 h-7 bg-blue-600 rounded-full"></span>
                    Grafik Pemeriksaan Hari Ini
                </h3>
                <div class="h-[250px] sm:h-[300px] md:h-[400px] w-full relative">
                    <canvas id="bloodPressureChart"></canvas>
                </div>
            </div>

            <div id="advice_card" class="glass-card p-6 rounded-[2.5rem] border-t-4 border-t-blue-600 flex flex-col h-[500px] animate-fade-up delay-5">
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-600 w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-lg transition-transform hover:scale-110">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h3 class="font-black text-xs tracking-widest uppercase text-slate-400">AI Health Agent</h3>
                    </div>
                    <div class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                </div>

                <div id="chat_display" class="chat-container flex-1">
                    <div class="bubble-ai">
                        Halo! Saya siap memantau kesehatan anda.
                    </div>
                    <div id="advice_content" class="hidden"></div>
                </div>

                <div class="p-3 mb-2 bg-amber-50 border border-amber-200 rounded-xl text-[10px] font-semibold text-amber-700 flex items-start gap-2 animate-fade-up">
                    <i class="fas fa-exclamation-triangle mt-0.5 text-xs"></i>
                    <span><b>Disclaimer:</b> Ini hanya untuk tujuan Informasi. untuk mendapatkan diagnosis atau saran medis, hubungi tenaga medis.</span>
                </div>

                <div class="relative pt-4 mt-2 border-t border-slate-100">
                    <input type="text" id="ai_input" 
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl py-4 px-5 pr-14 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none transition-all focus:bg-white" 
                        placeholder="Tanyakan obat, gejala, atau langkah tindakan...">
                    <button onclick="sendChatMessage()" class="btn-send absolute right-2 top-[22px] bg-blue-600 hover:bg-blue-700 text-white w-10 h-10 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200">
                        <i class="fas fa-paper-plane text-xs"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="glass-card rounded-[2.5rem] overflow-hidden border-none animate-fade-up delay-5">
            <div class="p-6 px-8 border-b border-slate-50 flex justify-between items-center bg-white">
                <h3 class="font-black text-xs tracking-[2px] uppercase text-slate-400">Log Aktivitas</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[700px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-[2px]">
                            <th class="p-6 pl-10">Waktu</th>
                            <th class="p-6 text-center">Sistolik</th>
                            <th class="p-6 text-center">Diastolik</th>
                            <th class="p-6 text-center">Pulse/Min</th>
                            <th class="p-6 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="log_table_body" class="text-sm font-bold text-slate-700 divide-y divide-slate-50">
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<script>
    const firebaseConfig = {
        apiKey: "Api key Firebase",
        databaseURL: "https://Realtime Firebase Database.firebaseio.com",
        projectId: "Project Tekanan darah IoT"
    };
    firebase.initializeApp(firebaseConfig);
    const db = firebase.database();
    
    const idPasien = "<?php echo $id_aktif; ?>";
    const tanggalSekarang = "<?php echo $tanggal_pilih; ?>";

    const ctx = document.getElementById('bloodPressureChart').getContext('2d');
    const blueGrad = ctx.createLinearGradient(0, 0, 0, 400); blueGrad.addColorStop(0, 'rgba(37, 99, 235, 0.25)'); blueGrad.addColorStop(1, 'rgba(37, 99, 235, 0)');
    const greenGrad = ctx.createLinearGradient(0, 0, 0, 400); greenGrad.addColorStop(0, 'rgba(16, 185, 129, 0.25)'); greenGrad.addColorStop(1, 'rgba(16, 185, 129, 0)');
    const roseGrad = ctx.createLinearGradient(0, 0, 0, 400); roseGrad.addColorStop(0, 'rgba(244, 63, 94, 0.25)'); roseGrad.addColorStop(1, 'rgba(244, 63, 94, 0)');

    let bloodChart = new Chart(ctx, {
        type: 'line',
        data: { 
            labels: [], 
            datasets: [
                { 
                    label: 'Sistolik', 
                    borderColor: '#2563eb', 
                    backgroundColor: blueGrad, 
                    data: [], 
                    tension: 0.4, 
                    fill: true, 
                    pointRadius: 4, 
                    pointBackgroundColor: '#ffffff', 
                    pointBorderColor: '#2563eb', 
                    pointBorderWidth: 2, 
                    borderWidth: 3 
                },
                { 
                    label: 'Diastolik', 
                    borderColor: '#10b981', 
                    backgroundColor: greenGrad, 
                    data: [], 
                    tension: 0.4, 
                    fill: true, 
                    pointRadius: 4, 
                    pointBackgroundColor: '#ffffff', 
                    pointBorderColor: '#10b981', 
                    pointBorderWidth: 2, 
                    borderWidth: 3 
                },
                { 
                    label: 'Pulse/Min', 
                    borderColor: '#f43f5e', 
                    backgroundColor: roseGrad, 
                    data: [], 
                    tension: 0.4, 
                    fill: true, 
                    pointRadius: 4, 
                    pointBackgroundColor: '#ffffff', 
                    pointBorderColor: '#f43f5e', 
                    pointBorderWidth: 2, 
                    borderWidth: 3 
                }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            animation: { duration: 1200, easing: 'easeOutQuart' },
            plugins: { 
                legend: { 
                    display: true, 
                    position: 'top', 
                    align: 'end', 
                    labels: { color: '#64748b', font: { weight: 'bold', size: 12 }, usePointStyle: true, padding: 20 } 
                }, 
                tooltip: { 
                    backgroundColor: '#1e293b', 
                    padding: 12, 
                    cornerRadius: 8, 
                    titleFont: {size: 13}, 
                    bodyFont: {size: 14, weight: 'bold'} 
                } 
            }, 
            scales: { 
                y: { 
                    grid: { color: '#f1f5f9' }, 
                    ticks: { color: '#94a3b8', font: { weight: '600' }, stepSize: 10 }, 
                    min: 10, 
                    max: 160 
                }, 
                x: { 
                    grid: { display: false }, 
                    ticks: { color: '#94a3b8', font: { weight: '600' }, maxTicksLimit: 10 } 
                } 
            } 
        }
    });

    db.ref('pasien/' + idPasien).once('value').then((snapshot) => {
        const profil = snapshot.val();
        if (profil) {
            if (profil.umur) { document.getElementById('label_umur').innerText = "UMUR: " + profil.umur + " THN"; document.getElementById('label_umur').classList.remove('hidden'); }
            if (profil.gender) { document.getElementById('label_gender').innerText = (profil.gender == 'L' || profil.gender == 'Pria' ? "PRIA" : "WANITA"); document.getElementById('label_gender').classList.remove('hidden'); }
        }
    });

    let lastProcessedData = { sistolik: 0, diastolik: 0, pulse_fix: 0 };

    const dataRef = db.ref('sensor_data/' + tanggalSekarang + '/' + idPasien);

    dataRef.on('value', (snapshot) => {
        const logBody = document.getElementById('log_table_body');
        const data = snapshot.val();
        let rawDataArray = [];

        if (data) {
            Object.keys(data).forEach(key => {
                const item = data[key];
                if (item && typeof item === 'object' && item.sistolik !== undefined) {
                    item.pulse_fix = item.pulse || item['Pulse/Min'] || item.bpm || 0;
                    if (!item.waktu) item.waktu = "00:00:00";
                    rawDataArray.push(item);
                }
            });

            if (rawDataArray.length === 0 && data.sistolik !== undefined) {
                data.pulse_fix = data.pulse || data['Pulse/Min'] || data.bpm || 0;
                if (!data.waktu) data.waktu = "00:00:00";
                rawDataArray.push(data);
            }

            rawDataArray.sort((a, b) => (a.waktu > b.waktu) ? 1 : -1);
        }

        if (rawDataArray.length > 0) {
            const latest = rawDataArray[rawDataArray.length - 1];

            animateValue("sis_val", Number(latest.sistolik));
            animateValue("dia_val", Number(latest.diastolik));
            animateValue("bpm_val", Number(latest.pulse_fix));
            updateStatusMedis(Number(latest.sistolik), Number(latest.diastolik), Number(latest.pulse_fix), latest.status);

            logBody.innerHTML = "";
            [...rawDataArray].reverse().forEach(item => {
                logBody.innerHTML += renderRow(item.sistolik, item.diastolik, item.pulse_fix, item.waktu, item.status);
            });

            const validDataForChart = rawDataArray.filter(d => Number(d.sistolik) > 0);

            bloodChart.data.labels = validDataForChart.map(item => item.waktu ? item.waktu.substring(0, 5) : "--");
            bloodChart.data.datasets[0].data = validDataForChart.map(item => Number(item.sistolik));
            bloodChart.data.datasets[1].data = validDataForChart.map(item => Number(item.diastolik));
            bloodChart.data.datasets[2].data = validDataForChart.map(item => Number(item.pulse_fix));
            bloodChart.update();

            // Pemicu AI hanya jika data pengukuran valid (> 0)
            if (Number(latest.sistolik) > 40 && Number(latest.diastolik) > 30) {
                triggerAiAutoAnalysis(Number(latest.sistolik), Number(latest.diastolik), Number(latest.pulse_fix));
            }
        } else {
            logBody.innerHTML = `<tr><td colspan='5' class='p-20 text-center text-slate-300 font-bold'>Tidak ada data pemeriksaan.</td></tr>`;
            document.getElementById("sis_val").innerText = "0"; document.getElementById("dia_val").innerText = "0"; document.getElementById("bpm_val").innerText = "0";
            document.getElementById("status_text").innerText = "MENUNGGU...";
            bloodChart.update();
        }
    });

    function animateValue(id, value) {
        const obj = document.getElementById(id);
        const start = parseInt(obj.innerText) || 0;
        let current = start; if (start === value) { obj.innerText = value; return; }
        const step = value > start ? 1 : -1;
        const timer = setInterval(() => { current += step; obj.innerText = current; if (current == value) clearInterval(timer); }, 15);
    }

    // LOGIKA UPDATE STATUS MEDIS: 91-139 SIS & 61-89 DIA -> NORMAL
    function updateStatusMedis(s, d, b, statusAsli = "") {
        const stText = document.getElementById('status_text'); 
        const stCont = document.getElementById('status_container');
        const stIcon = document.getElementById('status_icon'); 
        const stIconBg = document.getElementById('status_icon_bg');
        
        stCont.className = "glass-card p-7 rounded-[2.5rem] relative transition-all duration-500 border-l-4 animate-fade-up delay-4";
        
        if (s === 0 || d === 0 || statusAsli === "ERROR") {
            stText.innerText = "ERROR"; 
            stText.className = "text-2xl font-black text-rose-600 uppercase transition-colors";
            stCont.classList.add('border-l-rose-500'); 
            stIcon.className = "fas fa-triangle-exclamation text-rose-600 text-lg"; 
            stIconBg.className = "p-3 bg-rose-50 rounded-2xl transition-colors";
        } 
        else if (s >= 140 || d >= 90) {
            stText.innerText = "HIPERTENSI"; 
            stText.className = "text-2xl font-black text-red-600 uppercase transition-colors";
            stCont.classList.add('border-l-red-500'); 
            stIcon.className = "fas fa-heart-circle-exclamation text-red-600 text-lg"; 
            stIconBg.className = "p-3 bg-red-50 rounded-2xl transition-colors";
        } 
        else if (s <= 90 || d <= 60) {
            stText.innerText = "HIPOTENSI"; 
            stText.className = "text-2xl font-black text-amber-500 uppercase transition-colors";
            stCont.classList.add('border-l-amber-500'); 
            stIcon.className = "fas fa-droplet-slash text-amber-500 text-lg"; 
            stIconBg.className = "p-3 bg-amber-50 rounded-2xl transition-colors";
        } 
        else {
            // Rentang 91 - 139 (Sistolik) & 61 - 89 (Diastolik)
            stText.innerText = "NORMAL"; 
            stText.className = "text-2xl font-black text-emerald-600 uppercase transition-colors";
            stCont.classList.add('border-l-emerald-500'); 
            stIcon.className = "fas fa-heart-circle-check text-emerald-600 text-lg"; 
            stIconBg.className = "p-3 bg-emerald-50 rounded-2xl transition-colors";
        }
    }

    function triggerAiAutoAnalysis(sistolik, diastolik, pulse) {
        if (sistolik > 40 && diastolik > 30) {
            if (sistolik !== lastProcessedData.sistolik || diastolik !== lastProcessedData.diastolik || pulse !== lastProcessedData.pulse_fix) {
                lastProcessedData = { sistolik, diastolik, pulse_fix: pulse };
                
                let statusDiagnosa = "NORMAL";
                if (sistolik >= 140 || diastolik >= 90) statusDiagnosa = "HIPERTENSI";
                else if (sistolik <= 90 || diastolik <= 60) statusDiagnosa = "HIPOTENSI";
                
                const autoPrompt = `Sensor mencatat hasil ${statusDiagnosa} (${sistolik}/${diastolik} mmHg, ${pulse} BPM). Berikan evaluasi status medis, rekomendasi tindakan fisik/mandiri, dan instruksi lanjutan secara lengkap sesuai format.`;
                executeAiStream(autoPrompt, { sis: sistolik, dia: diastolik, bpm: pulse, status: statusDiagnosa });
            }
        }
    }

    async function executeAiStream(messageText, interstate = null) {
        const display = document.getElementById('chat_display'); const nama = "<?php echo $nama_tampilan; ?>";
        const sis = interstate ? interstate.sis : document.getElementById('sis_val').innerText;
        const dia = interstate ? interstate.dia : document.getElementById('dia_val').innerText;
        const bpm = interstate ? interstate.bpm : document.getElementById('bpm_val').innerText;
        const status = interstate ? interstate.status : document.getElementById('status_text').innerText;
        const contextData = `Nama Pasien: ${nama}, Tekanan Darah: ${sis}/${dia} mmHg, Detak Jantung: ${bpm} BPM, Diagnosis: ${status}`;
        const loadingId = "load_" + Date.now();
        display.innerHTML += `<div id="${loadingId}" class="bubble-ai italic text-slate-500 font-bold" style="background:transparent; border:none; box-shadow:none;"><i class="fas fa-circle-notch fa-spin text-blue-500 mr-2"></i>AI Sedang Menganalisis...</div>`;
        display.scrollTo({ top: display.scrollHeight, behavior: 'smooth' });
        try {
            const response = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'chat_ai', message: messageText, context: contextData }) });
            const data = await response.json(); document.getElementById(loadingId).remove();
            if(data.choices && data.choices.length > 0) {
                let aiText = data.choices[0].message.content;
                aiText = aiText.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>');
                display.innerHTML += `<div class="bubble-ai"><i class="fas fa-hand-holding-medical mr-1.5 text-blue-600"></i><b>Rekomendasi Tindakan Pasien:</b><br>${aiText}</div>`;
            } else {
                display.innerHTML += `<div class="bubble-ai text-red-500"><i class="fas fa-exclamation-triangle mr-2"></i>Maaf, AI gagal memberikan respon.</div>`;
            }
        } catch (error) {
            document.getElementById(loadingId).remove();
            display.innerHTML += `<div class="bubble-ai text-red-500"><i class="fas fa-wifi mr-2"></i>Gagal menghubungi server AI. Cek koneksi Anda.</div>`;
        }
        display.scrollTo({ top: display.scrollHeight, behavior: 'smooth' });
    }

    async function sendChatMessage() {
        const input = document.getElementById('ai_input'); const display = document.getElementById('chat_display'); const rawMsg = input.value.trim();
        if (rawMsg === "") return;
        display.innerHTML += `<div class="bubble-user">${rawMsg}</div>`; input.value = ""; display.scrollTo({ top: display.scrollHeight, behavior: 'smooth' });
        await executeAiStream(rawMsg);
    }

    document.getElementById('ai_input').addEventListener('keypress', (e) => { if (e.key === 'Enter') sendChatMessage(); });

    function renderRow(s, d, b, waktu, statusAsli = "") {
        let label = "NORMAL", badge = "bg-emerald-50 text-emerald-600 border-emerald-100";
        
        if (s === 0 || d === 0 || statusAsli === "ERROR") { 
            label = "ERROR"; 
            badge = "bg-rose-50 text-rose-600 border-rose-200"; 
        }
        else if (s >= 140 || d >= 90) { 
            label = "HIPERTENSI"; 
            badge = "bg-red-50 text-red-600 border-red-100"; 
        }
        else if (s <= 90 || d <= 60) { 
            label = "HIPOTENSI"; 
            badge = "bg-amber-50 text-amber-600 border-amber-100"; 
        }
        
        return `<tr class="hover:bg-slate-50 hover:shadow-inner transition-all duration-200">
                    <td class="p-6 pl-10 text-slate-500 font-bold">${waktu}</td>
                    <td class="p-6 text-center text-slate-900 font-black text-lg">${s} <span class="text-[10px] text-slate-400 font-bold">mmHg</span></td>
                    <td class="p-6 text-center text-slate-900 font-black text-lg">${d} <span class="text-[10px] text-slate-400 font-bold">mmHg</span></td>
                    <td class="p-6 text-center text-slate-900 font-black text-lg">${b} <span class="text-[10px] text-slate-400 font-bold uppercase">pulse/min</span></td>
                    <td class="p-6 text-center"><span class="px-4 py-1.5 rounded-xl text-[10px] font-black border ${badge}">${label}</span></td>
                </tr>`;
    }
</script>
</body>
</html>