<?php
session_start();
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ' . base_url('auth/login.php'));
  exit();
}

// Setiap kali halaman dimuat, anggap semua aspirasi belum dibaca
$_SESSION['read_ids'] = [];

// Tangani permintaan tandai dibaca via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    if (!in_array($id, $_SESSION['read_ids'])) {
        $_SESSION['read_ids'][] = $id;
    }
    echo 'success';
    exit;
}

// Ambil data aspirasi + user (kelas & nama)
$stmt = $pdo->query("SELECT a.id, a.content, a.created_at, a.is_public, a.is_anonymous, a.target, u.nama_lengkap, u.kelas
                     FROM aspirations a
                     LEFT JOIN users u ON a.user_id = u.id
                     ORDER BY u.kelas, a.created_at DESC");

$aspirasi_by_kelas = [];
while ($row = $stmt->fetch()) {
    $kelas = $row['kelas'] ?? 'Tanpa Kelas';
    if (!isset($aspirasi_by_kelas[$kelas])) {
        $aspirasi_by_kelas[$kelas] = [];
    }
    $row['is_read'] = in_array($row['id'], $_SESSION['read_ids']);
    $aspirasi_by_kelas[$kelas][] = $row;
}
?>
<!-- Asumsikan bagian PHP di atas ini tetap sama -->

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Kelola Aspirasi Siswa</title>
  <style>
    :root {
      --primary: #6C5CE7;
      --primary-light: #F2F1FF;
      --danger: #FF7675;
      --success: #00B894;
      --gray-dark: #2D3436;
      --gray-medium: #636E72;
      --gray-light: #DFE6E9;
      --white: #fff;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #FAFAFA;
      margin: 0 auto;
      padding: 30px;
      color: var(--gray-dark);
      max-width: 1200px;
    }

    h1 {
      text-align: center;
      color: var(--primary);
      font-weight: 600;
      margin: 20px 0 40px;
    }

    /* Tombol kembali yang ada di inline bar */
    .btn-kembali-inline {
      background: linear-gradient(135deg, #8e2de2, #4a00e0);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 20px;
      font-weight: 600;
      cursor: pointer;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      margin-right: 10px;
    }

    .btn-kembali-inline:hover {
      background: var(--primary);
      transform: translateY(-1px);
    }

    .kelas-buttons {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 10px;
      margin-bottom: 30px;
    }

    .kelas-buttons button {
      background: var(--white);
      color: var(--primary);
      border: none;
      padding: 10px 20px;
      border-radius: 20px;
      font-weight: 500;
      cursor: pointer;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .kelas-buttons button:hover {
      background: var(--primary-light);
      transform: translateY(-1px);
    }

    .kelas-buttons button.active {
      background: var(--primary);
      color: var(--white);
      box-shadow: 0 4px 8px rgba(108, 92, 231, 0.2);
    }

    .kelas-section {
      display: none;
      margin-bottom: 40px;
    }

    .kelas-section.active {
      display: block;
      animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .kelas-title {
      font-size: 1.4rem;
      margin: 30px 0 20px;
      color: var(--primary);
      font-weight: 600;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--gray-light);
    }

    .aspirasi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }

    .aspirasi-entry {
      background: var(--white);
      padding: 20px;
      border-radius: 12px;
      color: var(--gray-dark);
      transition: all 0.3s ease;
      box-shadow: 0 3px 10px rgba(0,0,0,0.04);
      border: 1px solid rgba(0,0,0,0.04);
    }

    .aspirasi-entry:hover {
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      transform: translateY(-2px);
    }

    .aspirasi-entry.read {
      background: var(--gray-light);
      opacity: 0.8;
    }

    .aspirasi-header {
      font-weight: 600;
      margin-bottom: 12px;
      color: var(--primary);
      font-size: 1.05rem;
    }

    .aspirasi-entry.read .aspirasi-header {
      color: var(--gray-medium);
    }

    .aspirasi-text {
      white-space: pre-wrap;
      font-size: 0.95rem;
      margin-bottom: 12px;
      line-height: 1.7;
    }

    .aspirasi-info {
      font-size: 0.85rem;
      margin-top: 12px;
      color: var(--gray-medium);
    }

    .aspirasi-target {
      font-style: italic;
      color: var(--danger);
      font-size: 0.85rem;
      margin: 8px 0;
      font-weight: 500;
    }

    .mark-read-btn {
      margin-top: 15px;
      padding: 8px 16px;
      font-size: 0.85rem;
      background: var(--success);
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s ease;
      font-weight: 500;
    }

    .mark-read-btn:hover:not(.read) {
      background: #00A885;
      transform: translateY(-1px);
    }

    .mark-read-btn.read {
      background: var(--gray-medium);
      cursor: default;
    }

    #searchBar {
      display: block;
      margin: 25px auto 35px;
      padding: 12px 20px;
      width: 100%;
      max-width: 450px;
      border-radius: 8px;
      border: 1px solid var(--gray-light);
      font-size: 1rem;
      outline: none;
      transition: all 0.3s ease;
    }

    #searchBar:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
    }

    @media (max-width: 768px) {
      body { padding: 20px; }
      .aspirasi-grid { grid-template-columns: 1fr; }
    }
  </style>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<h1>Kelola Aspirasi Siswa</h1>

<div class="kelas-buttons">
  <button class="btn-kembali-inline" onclick="window.location.href='<?= base_url('admin/admin_dashboard.php') ?>'">← Kembali</button>
  <?php foreach (array_keys($aspirasi_by_kelas) as $i => $kelas): ?>
    <button onclick="showKelas('kelas<?= $i ?>', this)">Kelas <?= htmlspecialchars($kelas) ?></button>
  <?php endforeach; ?>
</div>

<input type="text" id="searchBar" placeholder="🔍 Cari aspirasi..." onkeyup="filterAspirasi()">

<?php foreach ($aspirasi_by_kelas as $kelas => $list): ?>
  <?php $id = 'kelas' . array_search($kelas, array_keys($aspirasi_by_kelas)); ?>
  <div class="kelas-section" id="<?= $id ?>">
    <div class="kelas-title">Kelas <?= htmlspecialchars($kelas) ?></div>
    <div class="aspirasi-grid">
      <?php foreach ($list as $asp): ?>
        <div class="aspirasi-entry <?= $asp['is_read'] ? 'read' : '' ?>" data-id="<?= $asp['id'] ?>">
          <div class="aspirasi-header">
            Dari: <?= $asp['is_anonymous'] ? 'Anonim' : htmlspecialchars($asp['nama_lengkap'] ?? 'Tidak diketahui') ?>
            <?= $asp['is_public'] ? ' | 🌐 Publik' : '' ?>
          </div>
          <?php if ($asp['target']): ?>
            <div class="aspirasi-target">Untuk: <?= htmlspecialchars($asp['target']) ?></div>
          <?php endif; ?>
          <div class="aspirasi-text"><?= nl2br(htmlspecialchars($asp['content'])) ?></div>
          <div class="aspirasi-info">Tanggal: <?= date('d M Y H:i', strtotime($asp['created_at'])) ?></div>
          <button class="mark-read-btn <?= $asp['is_read'] ? 'read' : '' ?>" <?= $asp['is_read'] ? 'disabled' : '' ?>>
            <?= $asp['is_read'] ? '✓ Sudah Dibaca' : 'Tandai Sudah Dibaca' ?>
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<script>
function showKelas(id, button) {
  document.querySelectorAll('.kelas-section').forEach(el => el.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  document.querySelectorAll('.kelas-buttons button').forEach(btn => btn.classList.remove('active'));
  button.classList.add('active');
  document.getElementById('searchBar').value = '';
}

function filterAspirasi() {
  const q = document.getElementById('searchBar').value.toLowerCase();
  const active = document.querySelector('.kelas-section.active');
  if (!active) return;
  active.querySelectorAll('.aspirasi-entry').forEach(entry => {
    const text = entry.innerText.toLowerCase();
    entry.style.display = text.includes(q) ? 'block' : 'none';
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const firstButton = document.querySelector('.kelas-buttons button:not(.btn-kembali-inline)');
  if (firstButton) firstButton.click();

  document.querySelectorAll('.mark-read-btn').forEach(button => {
    button.addEventListener('click', function () {
      if (this.classList.contains('read')) return;
      const entry = this.closest('.aspirasi-entry');
      const id = entry.dataset.id;
      fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
      }).then(res => res.text()).then(result => {
        if (result === 'success') {
          this.textContent = '✓ Sudah Dibaca';
          this.classList.add('read');
          this.disabled = true;
          entry.classList.add('read');
          const parent = entry.parentElement;
          parent.removeChild(entry);
          parent.appendChild(entry);
        }
      });
    });
  });
});
</script>
</body>
</html>