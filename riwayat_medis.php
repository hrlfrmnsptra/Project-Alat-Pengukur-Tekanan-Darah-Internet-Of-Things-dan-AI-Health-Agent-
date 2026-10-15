<?php
session_start();
if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:data_pasien.php");
    exit();
}
// Ambil ID dan TANGGAL dari session login
$id_aktif = isset($_SESSION['id_pengukuran']) ? trim($_SESSION['id_pengukuran']) : '';
$tanggal_aktif = $_SESSION['tanggal_pilih'] ?? date('Y-m-d');
$nama_tampilan = $_SESSION['nama_pasien'] ?? 'Pasien Umum';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: #F1F5F9;
            color: #1E293B; 
            min-height: 100vh;
        }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        
        .glass-card { 
            background: rgba(255, 255, 255, 0.95); 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            box-shadow: 0 4px 15px -5px rgba(0, 0, 0, 0.05); 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .glass-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        }

        .filter-btn { transition: all 0.2s ease; }
        .filter-btn-active {
            background: #2563eb !important;
            color: white !important;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        .sidebar-item-active {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }

        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-up { opacity: 0; animation: fadeUp 0.6s ease-out forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
    </style>
</head>
<body class="pb-24 md:pb-0">

    <aside class="w-72 bg-white/90 backdrop-blur-xl min-h-screen border-r border-slate-200 hidden md:block fixed left-0 top-0 z-50 animate-fade-up">
        <div class="p-8">
            <div class="flex items-center gap-3 mb-10">
                <div class="bg-blue-600 p-2.5 rounded-2xl shadow-lg shadow-blue-500/30 transition-transform hover:rotate-12">
                    <i class="fas fa-heartbeat text-white text-xl"></i>
                </div>
                <h1 class="text-slate-900 font-extrabold text-2xl tracking-tight">Health <span class="text-blue-600">IoT</span></h1>
            </div>
            
            <nav class="space-y-2">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-[2px] mb-4 pl-4">Menu Utama</p>
                <a href="dashboard.php" class="flex items-center gap-4 p-4 text-slate-500 hover:bg-slate-50 hover:text-blue-600 rounded-2xl transition duration-300 group">
                    <i class="fas fa-th-large w-5 text-center group-hover:scale-110 transition-transform"></i> 
                    <span class="font-bold">Dashboard</span>
                </a>
                <a href="riwayat_medis.php" class="flex items-center gap-4 p-4 sidebar-item-active rounded-2xl transition duration-300">
                    <i class="fas fa-history w-5 text-center"></i> 
                    <span class="font-bold">Riwayat Medis</span>
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

    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-md border-t border-slate-200 px-8 py-4 z-[100] flex justify-between items-center shadow-2xl">
        <a href="dashboard.php" class="flex flex-col items-center text-slate-400">
            <i class="fas fa-th-large text-xl mb-1"></i>
            <span class="text-[10px] font-bold mt-1 uppercase">Dashboard</span>
        </a>
        <a href="riwayat_medis.php" class="flex flex-col items-center text-blue-600">
            <i class="fas fa-history text-xl mb-1"></i>
            <span class="text-[10px] font-bold mt-1 uppercase">Riwayat</span>
        </a>
        <a href="logout.php" class="flex flex-col items-center text-red-400">
            <i class="fas fa-sign-out-alt text-xl mb-1"></i>
            <span class="text-[10px] font-bold mt-1 uppercase">Keluar</span>
        </a>
    </div>

    <main class="flex-1 md:ml-72 p-4 md:p-10">
        <header class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 mb-10 animate-fade-up">
            <div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 mb-2 leading-tight">Arsip Riwayat Medis</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="bg-blue-50 text-blue-600 text-[10px] px-3 py-1 rounded-lg font-black border border-blue-100 uppercase tracking-wider transition-all hover:bg-blue-100">ID: <?php echo $id_aktif; ?></span>
                    <span class="bg-slate-50 text-slate-600 text-[10px] px-3 py-1 rounded-lg font-black border border-slate-100 uppercase tracking-wider transition-all hover:bg-slate-100">TGL: <?php echo $tanggal_aktif; ?></span>
                    <p id="filter_title" class="text-slate-500 text-xs md:text-sm font-bold italic ml-2">Memuat data hari ini...</p>
                </div>
            </div>
            
            <div class="w-full lg:w-auto overflow-x-auto hide-scrollbar pb-2 lg:pb-0">
                <div class="flex gap-2 bg-white p-1.5 rounded-2xl border border-slate-200 w-max shadow-sm hover:shadow-md transition-shadow">
                    <button onclick="filterData('1jam')" id="btn-1jam" class="filter-btn px-4 md:px-5 py-2 rounded-xl text-[10px] md:text-xs font-bold text-slate-500 hover:bg-slate-50">1 JAM</button>
                    <button onclick="filterData('6jam')" id="btn-6jam" class="filter-btn px-4 md:px-5 py-2 rounded-xl text-[10px] md:text-xs font-bold text-slate-500 hover:bg-slate-50">6 JAM</button>
                    <button onclick="filterData('8jam')" id="btn-8jam" class="filter-btn px-4 md:px-5 py-2 rounded-xl text-[10px] md:text-xs font-bold text-slate-500 hover:bg-slate-50">8 JAM</button>
                    <button onclick="filterData('semua')" id="btn-semua" class="filter-btn px-4 md:px-5 py-2 rounded-xl text-[10px] md:text-xs font-bold filter-btn-active">SEMUA</button>
                </div>
            </div>
        </header>

        <div class="glass-card p-5 md:p-8 rounded-[2rem] md:rounded-[2.5rem] mb-10 relative overflow-hidden animate-fade-up delay-1">
             <div class="flex justify-between items-center mb-8">
                <h3 class="font-extrabold text-base md:text-lg flex items-center gap-3 text-slate-900">
                    <span class="w-1.5 h-6 md:h-7 bg-blue-600 rounded-full"></span>
                    Grafik Riwayat Pasien
                </h3>
            </div>
            <div class="h-[250px] sm:h-[300px] md:h-[400px] w-full">
                <canvas id="historyChart"></canvas>
            </div>
        </div>

        <div class="glass-card rounded-[2rem] overflow-hidden shadow-sm animate-fade-up delay-2">
            <div class="p-6 border-b border-slate-50 bg-white flex items-center gap-3">
                <h3 class="font-black text-xs tracking-[2px] uppercase text-slate-400">Log Data Pemeriksaan</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-center min-w-[700px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-[2px]">
                            <th class="p-6 text-left pl-10">Tanggal & Waktu Ambil</th>
                            <th class="p-6">Sistolik</th>
                            <th class="p-6">Diastolik</th>
                            <th class="p-6">Pulse/Min</th>
                            <th class="p-6 text-right pr-10">Diagnosis</th>
                        </tr>
                    </thead>
                    <tbody id="table_history_body" class="divide-y divide-slate-50 text-sm font-bold text-slate-700">
                        </tbody>
                </table>
            </div>
        </div>
    </main>

<script>
    const firebaseConfig = {
        apiKey: "Apikey Firebase",
        databaseURL: "https://Realtime Firebase Database.firebaseio.com",
        projectId: "Project Tekanan darah IoT"
    };
    firebase.initializeApp(firebaseConfig);
    const db = firebase.database();
    
    const idPasienAktif = "<?php echo $id_aktif; ?>";
    const tanggalAktif = "<?php echo $tanggal_aktif; ?>";

    let allData = [];

    const ctx = document.getElementById('historyChart').getContext('2d');
    const blueGrad = ctx.createLinearGradient(0, 0, 0, 400); blueGrad.addColorStop(0, 'rgba(37, 99, 235, 0.15)'); blueGrad.addColorStop(1, 'rgba(37, 99, 235, 0)');
    const greenGrad = ctx.createLinearGradient(0, 0, 0, 400); greenGrad.addColorStop(0, 'rgba(16, 185, 129, 0.15)'); greenGrad.addColorStop(1, 'rgba(16, 185, 129, 0)');
    const roseGrad = ctx.createLinearGradient(0, 0, 0, 400); roseGrad.addColorStop(0, 'rgba(244, 63, 94, 0.15)'); roseGrad.addColorStop(1, 'rgba(244, 63, 94, 0)');

    let historyChart = new Chart(ctx, {
        type: 'line',
        data: { 
            labels: [], 
            datasets: [
                { label: 'Sistolik', borderColor: '#2563eb', backgroundColor: blueGrad, data: [], tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#ffffff', pointBorderColor: '#2563eb', pointBorderWidth: 2, borderWidth: 3 },
                { label: 'Diastolik', borderColor: '#10b981', backgroundColor: greenGrad, data: [], tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#ffffff', pointBorderColor: '#10b981', pointBorderWidth: 2, borderWidth: 3 },
                { label: 'Pulse/Min', borderColor: '#f43f5e', backgroundColor: roseGrad, data: [], tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#ffffff', pointBorderColor: '#f43f5e', pointBorderWidth: 2, borderWidth: 3 }
            ]
        },
        options: { 
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 1200, easing: 'easeOutQuart' },
            plugins: { 
                legend: { position: 'top', align: 'end', labels: { color: '#64748b', font: { weight: 'bold', size: 12 }, usePointStyle: true, padding: 20 } },
                tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 }
            },
            scales: {
                y: { grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8', font: { weight: '600' }, stepSize: 10 }, min: 10, max: 160 },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { weight: '600' }, maxTicksLimit: 8 } }
            }
        }
    });

    db.ref('sensor_data').on('value', (snapshot) => {
        const rootData = snapshot.val();
        allData = []; 

        if (rootData) {
            Object.keys(rootData).forEach(tanggalKey => {
                const pasienNode = rootData[tanggalKey][idPasienAktif];
                if (pasienNode) {
                    Object.keys(pasienNode).forEach(pushKey => {
                        const item = pasienNode[pushKey];
                        if (item && item.sistolik != null) {
                            item.pulse_fix = item.pulse || item['Pulse/Min'] || item.bpm || 0;
                            if (!item.waktu) item.waktu = "00:00:00";
                            
                            const ts = new Date(`${tanggalKey}T${item.waktu}`).getTime() || 0;
                            allData.push({
                                ...item,
                                tgl_fix: tanggalKey,
                                displayDate: `${tanggalKey} (${item.waktu.substring(0,5)})`,
                                timestamp: ts
                            });
                        }
                    });
                }
            });
        }

        allData.sort((a, b) => b.timestamp - a.timestamp);
        filterData('semua');
    });

    function filterData(type) {
        const now = Date.now();
        let filtered = [];
        if (type === '1jam') filtered = allData.filter(d => d.timestamp > (now - 3600000));
        else if (type === '6jam') filtered = allData.filter(d => d.timestamp > (now - 21600000));
        else if (type === '8jam') filtered = allData.filter(d => d.timestamp > (now - 28800000));
        else filtered = allData;

        document.getElementById('filter_title').innerText = type === 'semua' ? "Seluruh Arsip Log Pemeriksaan" : `Aktivitas ${type} Terakhir`;
        updateUI(filtered);
        updateButtonStyles(type);
    }

    function updateUI(data) {
        const tableBody = document.getElementById('table_history_body');
        tableBody.innerHTML = "";

        const chartData = [...data].reverse();
        historyChart.data.labels = chartData.map(d => d.tgl_fix.substring(5) + " " + d.waktu.substring(0, 5));
        historyChart.data.datasets[0].data = chartData.map(d => Number(d.sistolik));
        historyChart.data.datasets[1].data = chartData.map(d => Number(d.diastolik));
        historyChart.data.datasets[2].data = chartData.map(d => Number(d.pulse_fix));
        historyChart.update();

        if (data.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='5' class='p-20 text-center text-slate-300 font-bold'>Tidak ada data pemeriksaan yang sesuai filter.</td></tr>";
            return;
        }

        data.forEach(d => {
            const s = Number(d.sistolik), dia = Number(d.diastolik);
            const p = Number(d.pulse_fix);
            let st = "NORMAL", badge = "bg-emerald-50 text-emerald-600 border-emerald-100";
            if (s >= 140 || dia >= 90) { st = "HIPERTENSI"; badge = "bg-red-50 text-red-600 border-red-100"; }
            else if (s > 0 && (s <= 90 || dia <= 60)) { st = "HIPOTENSI"; badge = "bg-amber-50 text-amber-600 border-amber-100"; }

            tableBody.innerHTML += `
                <tr class="hover:bg-slate-50 hover:shadow-inner transition-all duration-200">
                    <td class="p-6 text-left pl-10 text-slate-500 font-bold">${d.displayDate}</td>
                    <td class="p-6 font-black text-slate-900 text-lg">${s} <span class="text-[10px] font-bold text-slate-400">mmHg</span></td>
                    <td class="p-6 font-black text-slate-900 text-lg">${dia} <span class="text-[10px] font-bold text-slate-400">mmHg</span></td>
                    <td class="p-6 font-black text-slate-900 text-lg">${p} <span class="text-[10px] font-bold text-slate-400 uppercase">pulse/min</span></td>
                    <td class="p-6 text-right pr-10"><span class="px-4 py-1.5 rounded-xl text-[10px] font-black border ${badge}">${st}</span></td>
                </tr>`;
        });
    }

    function updateButtonStyles(activeType) {
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('filter-btn-active'));
        const activeBtn = document.getElementById('btn-' + activeType);
        if(activeBtn) activeBtn.classList.add('filter-btn-active');
    }
</script>
</body>
</html>