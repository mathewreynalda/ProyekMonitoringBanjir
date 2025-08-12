<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Peringatan Dini Banjir</title>
  <link rel="icon" href="assets/img/unbin.png">
  <link rel="stylesheet" href="assets/css/style.css?v=<?=time()?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="index-page">

<!-- HEADER -->
<header class="header">
  <a href="index.php" class="logo">
    <img src="assets/img/unbin.png" alt="Logo" />
    <span class="sitename">Peringatan Dini Banjir</span>
  </a>
  <button class="hamburger" id="menu-toggle" aria-label="Buka Menu">&#9776;</button>
  <nav class="navmenu" id="main-menu">
    <a href="index.php" class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='index.php') echo ' active'; ?>">Beranda</a>
    <a href="banjir.php" class="nav-link<?php if(basename($_SERVER['PHP_SELF'])=='banjir.php') echo ' active'; ?>">Banjir</a>
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
      <a href="admin/dashboard.php" class="btn-getstarted">Dashboard</a>
      <a href="admin/logout.php" class="btn-getstarted" style="background:#ff4d4d;color:#fff;">Logout</a>
    <?php else: ?>
      <a href="admin/login.php" class="btn-getstarted">Login</a>
    <?php endif; ?>
  </nav>
</header>

<!-- HERO -->
<div class="hero">
  <div class="hero-text">
    <h1>Peringatan <span style="color:#00c6ff;">Dini Banjir</span></h1>
    <p>Pantau ketinggian air secara otomatis, dapatkan notifikasi banjir langsung di Telegram, dan akses dashboard lengkap untuk petugas.</p>
  </div>
</div>

<!-- STATUS CARDS -->
<div class="index-status-cards">
  <div class="status-card" id="status-card">
    <div class="status-card-icon"><i class="fa fa-water"></i></div>
    <div>
      <div>Status</div>
      <b id="status-level">Loading...</b>
    </div>
  </div>
  <div class="status-card" id="tinggi-card">
    <div class="status-card-icon"><i class="fa fa-chart-line"></i></div>
    <div>
      <div>Tinggi Air</div>
      <b id="tinggi-terbaru">0</b> <span style="font-size:0.92em;">cm</span>
    </div>
  </div>
  <div class="status-card" id="sensor-card">
    <div class="status-card-icon"><i class="fa fa-microchip"></i></div>
    <div>
      <div>Sensor</div>
      <b id="sensor-status">Memeriksa...</b>
    </div>
  </div>
</div>

<!-- CHART -->
<div class="chart-responsive">
  <canvas id="chartTinggi"></canvas>
</div>
<p class="update-label"><span id="last-update">Update: -</span></p>
<div id="toast"></div>

<!-- FOOTER -->
<footer class="main-footer">
  <div>
    <p>&copy; <?= date('Y') ?> <strong>Mathew Reynalda Rusli</strong>. All rights reserved.</p>
  </div>
</footer>

<!-- SCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let statusColorMap = {
  "Normal": "green",
  "Siaga": "orange",
  "Bahaya": "red",
  "Evakuasi": "darkred"
}; // fallback default

// Load warna status dari PHP
fetch('includes/status_colors.php')
  .then(res => res.json())
  .then(data => statusColorMap = data);

// Ambil warna dari map
function getStatusColor(status) {
  return statusColorMap[status] || "black";
}

// Toast notifikasi
function showToast(msg, warna="#007bff") {
  const toast = document.getElementById('toast');
  toast.innerText = msg;
  toast.style.background = warna;
  toast.style.display = "block";
  setTimeout(() => toast.style.display = "none", 2400);
}

// Ambil data status terbaru
function updatePublicStatus() {
  fetch('get_data.php')
    .then(res => res.json())
    .then(data => {
      if (Array.isArray(data) && data.length > 0) {
        const latest = data[0];
        document.getElementById('tinggi-terbaru').innerText = parseFloat(latest.tinggi).toFixed(2);
        document.getElementById('status-level').innerText = latest.status;
        document.getElementById('status-level').style.color = getStatusColor(latest.status);
        document.getElementById('sensor-status').innerText = "Aktif";
        document.getElementById('sensor-status').style.color = "green";
        document.getElementById('status-card').style.borderColor = getStatusColor(latest.status);
        document.getElementById('last-update').innerText = "Update: " + latest.waktu;
      } else {
        document.getElementById('status-level').innerText = 'Data tidak tersedia';
        document.getElementById('sensor-status').innerText = 'Tidak Aktif';
        document.getElementById('sensor-status').style.color = 'red';
        document.getElementById('tinggi-terbaru').innerText = "-";
      }
    })
    .catch(() => {
      document.getElementById('status-level').innerText = 'Gagal load data';
      document.getElementById('sensor-status').innerText = 'Tidak Aktif';
      document.getElementById('sensor-status').style.color = 'red';
    });
}
setInterval(updatePublicStatus, 3000);
updatePublicStatus();

// Chart data tinggi air
const ctx = document.getElementById('chartTinggi').getContext('2d');
let chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: [],
    datasets: [{
      label: 'Tinggi Air (cm)',
      data: [],
      borderWidth: 2,
      borderColor: 'blue',
      backgroundColor: 'rgba(0,0,255,0.08)',
      tension: 0.35,
      pointBackgroundColor: [],
      pointRadius: 4,
      pointHoverRadius: 7,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 700, easing: 'easeOutQuad' },
    plugins: {
      legend: { labels: { font: { size: 14 } } },
      tooltip: { enabled: true }
    },
    scales: {
      x: {
        ticks: {
          font: { size: 12 },
          autoSkip: true,
          maxTicksLimit: window.innerWidth < 700 ? 4 : 10,
        }
      },
      y: {
        beginAtZero: true,
        ticks: { font: { size: 12 } }
      }
    }
  }
});

function updateChart() {
  fetch('get_data.php')
    .then(res => res.json())
    .then(data => {
      if (!Array.isArray(data) || data.length === 0) return;
      const labels = data.map(item => item.waktu);
      const tinggi = data.map(item => parseFloat(item.tinggi));
      const status = data.map(item => item.status);
      const pointColors = status.map(s => getStatusColor(s));
      chart.data.labels = labels;
      chart.data.datasets[0].data = tinggi;
      chart.data.datasets[0].pointBackgroundColor = pointColors;
      chart.options.scales.x.maxTicksLimit = window.innerWidth < 700 ? 4 : 10;
      chart.update();
    });
}
setInterval(updateChart, 4000);
updateChart();

window.addEventListener('resize', () => {
  chart.options.scales.x.maxTicksLimit = window.innerWidth < 700 ? 4 : 10;
  chart.update();
});
</script>
</body>
</html>
