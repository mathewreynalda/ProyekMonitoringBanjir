<?php
session_start();
include '../config.php';
include '../includes/status_level.php';
date_default_timezone_set('Asia/Jakarta');

// Cek login
if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

// Ambil status sensor
$statusRow = $conn->query("SELECT status FROM control_status WHERE id=1");
$status = $statusRow && $statusRow->num_rows > 0 ? $statusRow->fetch_assoc()['status'] : 'OFF';

// Cek log dan user jika admin
$logs = $users = null;
if ($_SESSION['role'] === 'admin') {
    $logs = $conn->query("SELECT * FROM user_logs ORDER BY log_time DESC LIMIT 10");
    $users = $conn->query("SELECT username, role FROM users ORDER BY id ASC");
}

// Inisialisasi notifikasi dan error
$success_telegram = $error_telegram = "";
$success_user = $error_user = $success_reset = $error_reset = $success_profile = $error_profile = "";

// === HANDLE POST REDIRECT (agar tidak repeat form) ===
function redirectSafe() {
    header("Location: dashboard.php");
    exit;
}

// === HANDLE UPDATE PROFIL ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['username'];

    // Ubah nama
    if (isset($_POST['update_name']) && isset($_POST['new_name'])) {
        $newname = ucfirst(strtolower(trim($_POST['new_name'])));
        if ($newname === '') {
            $error_profile = "Nama tidak boleh kosong!";
        } else {
            $stmt = $conn->prepare("SELECT username FROM users WHERE LOWER(username)=LOWER(?) AND username!=?");
            $stmt->bind_param("ss", $newname, $username);
            $stmt->execute();
            $cek = $stmt->get_result();
            if ($cek->num_rows > 0) {
                $error_profile = "Nama sudah digunakan!";
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=? WHERE username=?");
                $stmt->bind_param("ss", $newname, $username);
                if ($stmt->execute()) {
                    $_SESSION['username'] = $newname;
                    $success_profile = "Nama berhasil diubah!";
                    $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$newname', 'Ubah nama user', NOW())");
                    redirectSafe();
                } else {
                    $error_profile = "Gagal mengubah nama!";
                }
            }
        }
    }

    // Ganti password
    if (isset($_POST['update_pass']) && isset($_POST['old_pass']) && isset($_POST['new_pass'])) {
        $old = $_POST['old_pass'];
        $new = $_POST['new_pass'];
        if (strlen($new) < 4) {
            $error_profile = "Password minimal 4 karakter!";
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE username=?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                if (password_verify($old, $row['password'])) {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE username=?");
                    $stmt2->bind_param("ss", $hashed, $username);
                    if ($stmt2->execute()) {
                        $success_profile = "Password berhasil diubah!";
                        $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$username', 'Ganti password', NOW())");
                        redirectSafe();
                    } else {
                        $error_profile = "Gagal mengubah password!";
                    }
                } else {
                    $error_profile = "Password lama salah!";
                }
            }
        }
    }

    // Tambah user (admin)
    if ($_SESSION['role'] === 'admin' && isset($_POST['new_username']) && isset($_POST['new_password'])) {
        $new_username = ucfirst(strtolower(trim($_POST['new_username'])));
        $new_password = trim($_POST['new_password']);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
        $stmt->bind_param("ss", $new_username, $hashed_password);
        if ($stmt->execute()) {
            $success_user = "User berhasil dibuat!";
            $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$username', 'Tambah user $new_username', NOW())");
            redirectSafe();
        } else {
            $error_user = "Gagal menambah user!";
        }
    }

    // Reset password user
    if ($_SESSION['role'] === 'admin' && isset($_POST['reset_user']) && isset($_POST['reset_password'])) {
        $reset_user = $_POST['reset_user'];
        $reset_password = $_POST['reset_password'];
        $hashed_new = password_hash($reset_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE username=?");
        $stmt->bind_param("ss", $hashed_new, $reset_user);
        if ($stmt->execute()) {
            $success_reset = "Password user $reset_user berhasil direset!";
            $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$username', 'Reset password $reset_user', NOW())");
            redirectSafe();
        } else {
            $error_reset = "Gagal reset password!";
        }
    }

    // Kirim Telegram manual
    if ($_SESSION['role'] === 'admin' && isset($_POST['telegram_msg'])) {
        $telegram_msg = trim($_POST['telegram_msg']);
        if (!empty($telegram_msg)) {
            include '../includes/telegram_helper.php';
            if (sendTelegramMessage($telegram_msg)) {
                $success_telegram = "Pesan Telegram terkirim: $telegram_msg";
                $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$username', 'Kirim Telegram: $telegram_msg', NOW())");
                redirectSafe();
            } else {
                $error_telegram = "Gagal mengirim pesan Telegram!";
            }
        }
    }
}

// Handle AJAX notifikasi terakhir
if (isset($_GET['action']) && $_GET['action'] === 'get_last_telegram') {
    header('Content-Type: application/json');
    $result = $conn->query("SELECT value, updated_at FROM notify_status WHERE id = 1");
    if ($result && $row = $result->fetch_assoc()) {
        $status = $row['value'];
        $time = $row['updated_at'];

        if ($status === 'Sensor Mati') {
            $msg = "🚨 Sensor tidak aktif sejak pukul " . date('H:i', strtotime($time)) . "!";
        } else if ($status === 'Normal') {
            $msg = "✅ Status kembali Normal.";
        } else if ($status === 'Sensor kembali aktif') {
            $msg = "✅ Sensor kembali aktif pada pukul " . date('H:i', strtotime($time)) . ".";
        } else {
            $msg = "⚠️ Peringatan Banjir: Status {$status}!";
        }

        echo json_encode(['msg' => "Kirim Telegram: $msg", 'time' => $time]);
    } else {
        echo json_encode(['msg' => 'Belum ada notifikasi peringatan.', 'time' => '-']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Peringatan Dini Banjir</title>
  <link rel="icon" href="../assets/img/unbin.png">
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar">
  <div class="sidebar-logo">
    <img src="../assets/img/unbin.png" alt="Logo" width="40" />
    <span>Peringatan Banjir</span>
  </div>
  <nav>
    <a href="dashboard.php" class="nav-link active"><i class="fa fa-home"></i> Dashboard</a>
    <a href="#data" class="nav-link"><i class="fa fa-database"></i> Data Sensor</a>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <a href="#user" class="nav-link"><i class="fa fa-users"></i> Manajemen User</a>
    <?php endif; ?>
    <a href="#" onclick="openProfileModal();return false;" class="nav-link"><i class="fa fa-user-edit"></i> Edit Profil</a>
    <a href="../admin/logout.php" onclick="return confirm('Logout sekarang?')" class="nav-link"><i class="fa fa-sign-out"></i> Logout</a>
  </nav>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
  <header class="main-header">
    <button id="toggleSidebar" class="hamburger"><i class="fa fa-bars"></i></button>
    <h1>Dashboard</h1>
    <div>
      <b><?= ucfirst($_SESSION['username']) ?></b>
      <span class="role"><?= $_SESSION['role'] ?></span>
    </div>
  </header>

  <!-- STATUS CARD -->
  <div class="dashboard-cards">
    <div class="status-card big" id="status-card">
      <div class="status-card-icon"><i class="fa fa-bell"></i></div>
      <div>
        <div>Status</div>
        <b id="status-level">Loading...</b>
      </div>
    </div>
    <div class="status-card big" id="tinggi-card">
      <div class="status-card-icon"><i class="fa fa-water"></i></div>
      <div>
        <div>Tinggi Air</div>
        <b id="tinggi-terbaru">0</b> <span style="font-size:0.92em;">cm</span>
      </div>
    </div>
    <div class="status-card big" id="sensor-card">
      <div class="status-card-icon"><i class="fa fa-microchip"></i></div>
      <div>
        <div>Sensor</div>
        <b id="sensor-status"><?= $status == 'ON' ? "Aktif" : "Tidak Aktif"; ?></b>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <button onclick="showPopupControl('ON')" class="btn-action">ON</button>
        <button onclick="showPopupControl('OFF')" class="btn-action" style="background:#ff4d4d;">OFF</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CHART -->
  <div class="chart-container">
    <canvas id="chartTinggi"></canvas>
  </div>
<!-- ===== DATA SENSOR ===== -->
<section id="data">
  <div class="export-section">
    <label for="start_date">Dari:</label>
    <input type="date" id="start_date">
    <label for="end_date">Sampai:</label>
    <input type="date" id="end_date">
    <button onclick="exportData('excel')" class="btn-action"><i class="fa fa-file-excel"></i> Export Excel</button>
    <button onclick="exportData('pdf')" class="btn-action"><i class="fa fa-file-pdf"></i> Export PDF</button>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <select id="daysRange">
        <option value="1">1 hari</option>
        <option value="7">7 hari</option>
        <option value="14">14 hari</option>
        <option value="30" selected>30 hari</option>
      </select>
      <button onclick="showPopupArchive()" class="btn-action btn-archive"><i class="fa fa-archive"></i> Arsipkan</button>
    <?php endif; ?>
  </div>

  <div class="responsive-table">
    <table>
      <thead>
        <tr><th>ID</th><th>Tinggi</th><th>Status</th><th>Waktu</th></tr>
      </thead>
      <tbody id="sensor-table">
        <!-- Data akan dimuat via JavaScript -->
      </tbody>
    </table>
  </div>
</section>

<!-- ===== NOTIFIKASI TELEGRAM TERAKHIR ===== -->
<section style="margin-top:32px;">
  <h2>Notifikasi Telegram Terakhir</h2>
  <p id="last-telegram-msg" style="color:blue;">Memuat data...</p>
  <p><small>Waktu: <span id="last-telegram-time">-</span></small></p>
</section>

<!-- ===== LOG AKTIVITAS (Admin Only) ===== -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<section style="margin-top:32px;">
  <h2>Log Aktivitas</h2>
  <div class="log-activity" style="max-height:150px;overflow-y:auto;">
    <ul id="log-list" style="font-size:0.97em;">
      <?php if ($logs) while ($log = $logs->fetch_assoc()) {
        if (strtoupper($log['user']) === 'SYSTEM' &&
          preg_match('/^Kirim Telegram: .*Sistem sensor (dinyalakan|dimatikan) oleh/i', $log['activity'])
        ) continue;
        $log_user = ucfirst(strtolower($log['user']));
        echo "<li>{$log['log_time']} - {$log_user}: {$log['activity']}</li>";
      } ?>
    </ul>
  </div>
</section>
<?php endif; ?>
<!-- ===== KIRIM TELEGRAM MANUAL ===== -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<section style="margin-top:32px;">
  <h2>Kirim Notifikasi Telegram</h2>
  <?php if (!empty($success_telegram)) echo "<p style='color:green;'>$success_telegram</p>"; ?>
  <?php if (!empty($error_telegram)) echo "<p style='color:red;'>$error_telegram</p>"; ?>
  <form method="POST" class="telegram-form">
    <input type="text" name="telegram_msg" placeholder="Isi pesan Telegram..." required>
    <button type="submit" class="btn-action">Kirim Pesan</button>
  </form>
</section>
<?php endif; ?>

<!-- ===== MANAJEMEN USER ===== -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<section id="user" style="margin-top:32px;">
  <h2>Manajemen User</h2>
  <?php if (!empty($success_user)) echo "<p style='color:green;'>$success_user</p>"; ?>
  <?php if (!empty($error_user)) echo "<p style='color:red;'>$error_user</p>"; ?>
  <?php if (!empty($success_reset)) echo "<p style='color:green;'>$success_reset</p>"; ?>
  <?php if (!empty($error_reset)) echo "<p style='color:red;'>$error_reset</p>"; ?>
  
  <!-- Tambah User -->
  <form method="POST" class="user-form" style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;">
    <input type="text" name="new_username" placeholder="Username" required>
    <input type="password" name="new_password" placeholder="Password" required>
    <button type="submit" class="btn-action">Tambah User</button>
  </form>

  <!-- Daftar User -->
  <h3>Daftar User</h3>
  <div class="responsive-table">
    <table>
      <thead>
        <tr><th>No</th><th>Username</th><th>Role</th><th>Reset Password</th></tr>
      </thead>
      <tbody>
        <?php if ($users) { $users->data_seek(0); $no = 1;
        while ($u = $users->fetch_assoc()) { ?>
        <tr>
          <td><?= $no ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td>
            <form method="POST" class="reset-password-form" onsubmit="return confirm('Reset password user <?= htmlspecialchars($u['username']) ?>?')">
              <input type="hidden" name="reset_user" value="<?= htmlspecialchars($u['username']) ?>">
              <input type="text" name="reset_password" placeholder="Password baru" required class="reset-password-input">
              <button type="submit" class="btn-action btn-reset">Reset</button>
            </form>
          </td>
        </tr>
        <?php $no++; } } ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- ===== MODAL EDIT PROFIL ===== -->
<div id="profileModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:white;padding:25px;border-radius:12px;max-width:400px;width:90%;">
    <h2>Edit Profil</h2>
    <?php if (!empty($success_profile)) echo "<p style='color:green;'>$success_profile</p>"; ?>
    <?php if (!empty($error_profile)) echo "<p style='color:red;'>$error_profile</p>"; ?>

    <!-- Form ubah nama -->
    <form method="POST" style="margin-bottom:18px;">
      <label>Nama Baru:</label>
      <input type="text" name="new_name" value="<?= htmlspecialchars($_SESSION['username']) ?>" required>
      <button type="submit" name="update_name" class="btn-action">Ubah Nama</button>
    </form>

    <!-- Form ubah password -->
    <form method="POST">
      <label>Password Lama:</label>
      <input type="password" name="old_pass" required>
      <label>Password Baru:</label>
      <input type="password" name="new_pass" required>
      <button type="submit" name="update_pass" class="btn-action">Ganti Password</button>
    </form>

    <button onclick="closeProfileModal()" class="btn-action" style="background:#bbb;margin-top:12px;">Tutup</button>
  </div>
</div>
<!-- MODAL KONFIRMASI UMUM -->
<div id="customConfirmModal" class="custom-modal">
  <div class="custom-modal-content">
    <p id="customConfirmMessage">Yakin?</p>
    <div class="modal-buttons">
      <button id="confirmYes" class="btn-action">Ya</button>
      <button id="confirmNo" class="btn-action btn-cancel">Batal</button>
    </div>
  </div>
</div>
<!-- NOTIFIKASI SUKSES -->
<div id="successModal" class="custom-modal">
  <div class="custom-modal-content">
    <p id="successMessage">Berhasil!</p>
    <div class="modal-buttons">
      <button onclick="closeSuccessModal()" class="btn-action">Tutup</button>
    </div>
  </div>
</div>
<!-- ===== CHART.JS & SCRIPT ===== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<!-- ===== CHART.JS & SCRIPT ===== -->
// ===== CHART INISIALISASI =====
const ctx = document.getElementById('chartTinggi').getContext('2d');
let chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: [],
    datasets: [{
      label: 'Tinggi Air (cm)',
      data: [],
      pointBackgroundColor: [],
      borderColor: 'blue',
      backgroundColor: 'rgba(0,0,255,0.1)',
      fill: true,
      tension: 0.3,
      pointRadius: 5,
      pointHoverRadius: 7
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true },
      x: {
        ticks: {
          maxRotation: 45,
          minRotation: 45,
          autoSkip: true
        }
      }
    },
    plugins: {
      legend: {
        display: true,
        labels: { color: '#000' }
      }
    }
  }
});

// ====== UPDATE DASHBOARD ======
function updateDashboard() {
  fetch('../get_data.php')
    .then(res => res.json())
    .then(data => {
      if (!data || data.length === 0) return;

      const latest = data[0];
      document.getElementById('tinggi-terbaru').innerText = parseFloat(latest.tinggi).toFixed(2);
      document.getElementById('status-level').innerText = latest.status;
      document.getElementById('status-level').style.color = getStatusColor(latest.status);
      document.getElementById('status-card').style.borderColor = getStatusColor(latest.status);

      let tableHTML = '';
      data.slice(0, 20).forEach(item => {
        tableHTML += `
          <tr>
            <td>${item.id}</td>
            <td>${parseFloat(item.tinggi).toFixed(2)}</td>
            <td class="${getStatusClass(item.status)}">${item.status}</td>
            <td>${item.waktu}</td>
          </tr>`;
      });
      document.getElementById('sensor-table').innerHTML = tableHTML;

      const labels = [], heights = [], colors = [];
      data.forEach(d => {
        labels.push(d.waktu);
        heights.push(parseFloat(d.tinggi));
        switch (d.status) {
          case 'Normal': colors.push('green'); break;
          case 'Siaga': colors.push('orange'); break;
          case 'Bahaya': colors.push('red'); break;
          case 'Evakuasi': colors.push('darkred'); break;
          default: colors.push('gray');
        }
      });
      chart.data.labels = labels;
      chart.data.datasets[0].data = heights;
      chart.data.datasets[0].pointBackgroundColor = colors;
      chart.update();
    });
}

// ====== UPDATE LAST TELEGRAM ======
function updateLastTelegram() {
  fetch('dashboard.php?action=get_last_telegram')
    .then(res => res.json())
    .then(data => {
      document.getElementById('last-telegram-msg').innerText = data.msg;
      document.getElementById('last-telegram-time').innerText = data.time;
    });
}

// ====== WARNA STATUS ======
function getStatusColor(status) {
  switch (status) {
    case "Normal": return "green";
    case "Siaga": return "orange";
    case "Bahaya": return "red";
    case "Evakuasi": return "darkred";
    default: return "black";
  }
}
function getStatusClass(status) {
  switch (status) {
    case "Normal": return "status-normal";
    case "Siaga": return "status-siaga";
    case "Bahaya": return "status-bahaya";
    case "Evakuasi": return "status-evakuasi";
    default: return "";
  }
}

// ====== SIDEBAR TOGGLE (Mobile) ======
document.getElementById('toggleSidebar')?.addEventListener('click', () => {
  document.querySelector('.sidebar').classList.toggle('open');
});

// ====== EKSPORT DATA ======
function exportData(type) {
  const start = document.getElementById('start_date').value;
  const end = document.getElementById('end_date').value;
  if (!start || !end) {
    alert('Tanggal mulai dan akhir harus diisi.');
    return;
  }
  window.location.href = `export_${type}.php?start=${start}&end=${end}`;
}

// ====== MODAL PROFIL ======
function openProfileModal() {
  document.getElementById('profileModal').style.display = 'flex';
}
function closeProfileModal() {
  document.getElementById('profileModal').style.display = 'none';
}

// ====== MODAL KONFIRMASI UNIVERSAL ======
function showCustomConfirm(message, callbackYes) {
  document.getElementById('customConfirmMessage').innerText = message;
  document.getElementById('customConfirmModal').style.display = 'flex';

  const yesBtn = document.getElementById('confirmYes');
  const noBtn = document.getElementById('confirmNo');

  const cleanup = () => {
    yesBtn.onclick = null;
    noBtn.onclick = null;
    document.getElementById('customConfirmModal').style.display = 'none';
  };

  yesBtn.onclick = () => {
    callbackYes();
    cleanup();
  };
  noBtn.onclick = cleanup;
}

// ====== SENSOR ON/OFF ======
function showPopupControl(command) {
  const message = command === 'ON'
    ? "Yakin ingin mengaktifkan sensor?"
    : "Yakin ingin menonaktifkan sensor?";

  showCustomConfirm(message, () => {
    fetch(`../control.php?action=${command}`)
      .then(res => res.text())
      .then(msg => {
        showSuccessModal(msg);
        document.getElementById('sensor-status').innerText = command === 'ON' ? 'Aktif' : 'Tidak Aktif';
        document.getElementById('sensor-status').style.color = command === 'ON' ? 'green' : 'red';
      });
  });
}

// ====== ARSIPKAN DATA SENSOR ======
function showPopupArchive() {
  const days = document.getElementById('daysRange').value;
  showCustomConfirm(`Yakin ingin mengarsipkan data lebih dari ${days} hari lalu?`, () => {
    window.location.href = `archive_data.php?days=${days}`;
  });
}

// ====== LOGOUT ======
function logoutConfirm() {
  showCustomConfirm("Yakin ingin logout sekarang?", () => {
    window.location.href = "../admin/logout.php";
  });
}

// ====== RESET PASSWORD USER ======
document.querySelectorAll('.reset-password-form')?.forEach(form => {
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const username = form.querySelector('[name="reset_user"]').value;
    showCustomConfirm(`Reset password untuk user ${username}?`, () => {
      form.submit();
    });
  });
});

function showSuccessModal(msg) {
  document.getElementById('successMessage').innerText = msg;
  document.getElementById('successModal').style.display = 'flex';
}
function closeSuccessModal() {
  document.getElementById('successModal').style.display = 'none';
}


// ====== JALANKAN ======
setInterval(updateDashboard, 5000);
setInterval(updateLastTelegram, 8000);
updateDashboard();
updateLastTelegram();
setInterval(() => {
  fetch('telegram_notify.php?src=dashboard');
}, 2000); // tiap 2 detik

</script>
</div> <!-- end .main-content -->
</body>
</html>
