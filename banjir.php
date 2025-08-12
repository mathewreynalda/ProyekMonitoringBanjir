<?php session_start(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edukasi Banjir</title>
  <link rel="icon" href="assets/img/unbin.png">
  <link rel="stylesheet" href="assets/css/style.css?v=<?=time()?>">  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="edukasi-page">
<!-- HEADER (copy dari index.php header) -->
<header class="header">
  <a href="index.php" class="logo">
    <img src="assets/img/unbin.png" alt="Logo" />
    <span class="sitename">Peringatan Dini Banjir</span>
  </a>
  <button class="hamburger" id="menu-toggle" aria-label="Buka Menu">&#9776;</button>
  <nav class="navmenu" id="main-menu">
    <a href="index.php" class="nav-link">Beranda</a>
    <a href="banjir.php" class="nav-link">Banjir</a>
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
      <a href="admin/dashboard.php" class="btn-getstarted">Dashboard</a>
      <a href="admin/logout.php" class="btn-getstarted" style="background:#ff4d4d;color:#fff;">Logout</a>
    <?php else: ?>
      <a href="admin/login.php" class="btn-getstarted">Login</a>
    <?php endif; ?>
  </nav>
</header>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainMenu = document.getElementById('main-menu');
  function toggleMenu(e) {
    e.stopPropagation();
    mainMenu.classList.toggle('open');
  }
  menuToggle.addEventListener('click', toggleMenu);

  // Tutup menu jika klik di luar pada mobile
  document.addEventListener('click', function(e){
    if (window.innerWidth <= 650 && !mainMenu.contains(e.target) && e.target !== menuToggle) {
      mainMenu.classList.remove('open');
    }
  });

  // Jika resize dari mobile ke desktop, pastikan menu tampil lagi
  window.addEventListener('resize', function() {
    if (window.innerWidth > 650) {
      mainMenu.classList.remove('open');
    }
  });
});
</script>

<!-- KONTEN EDUKASI -->
<div class="content" style="max-width:600px;margin:38px auto 0 auto;padding:28px 7vw;background:#fff;border-radius:15px;box-shadow:0 1px 8px rgba(0,0,0,0.06);">
    <h2 class="mb-3">Edukasi Banjir</h2>
    <p>
        Banjir adalah salah satu bencana yang dapat dicegah dengan edukasi dan kesiapsiagaan.
    </p>
    <ul style="text-align: left; max-width: 500px; margin: 0 auto;">
        <li><b>Penyebab:</b> curah hujan tinggi, drainase buruk, buang sampah sembarangan.</li>
        <li><b>Pencegahan:</b> rajin bersihkan selokan, tidak menutup saluran air, tanam pohon.</li>
        <li><b>Jika terjadi banjir:</b> amankan dokumen penting, segera matikan listrik, evakuasi jika perlu.</li>
    </ul>
</div>

<!-- FOOTER (copy dari index.php footer) -->
<footer class="main-footer">
    <div>
        <p>&copy; <?= date('Y') ?> <strong>Mathew Reynalda Rusli</strong>. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
