<?php
session_start();
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Ambil daftar siswa untuk pilihan orang tua
$stmtSiswa = $pdo->query("SELECT id, nama_lengkap, kelas FROM users WHERE role = 'siswa' ORDER BY nama_lengkap");
$siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];
    $role         = $_POST['role'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $kelas        = $_POST['kelas'] ?? null;
    $parent_id    = $_POST['parent_id'] ?? null;

    // Validasi
    $cek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $cek->execute([$username]);

    if ($cek->rowCount() > 0) {
        $error = "Username '$username' sudah digunakan.";
    } elseif ($role === 'siswa' && empty($kelas)) {
        $error = "Kelas wajib diisi untuk siswa.";
    } elseif ($role === 'ortu' && empty($parent_id)) {
        $error = "Anda harus memilih anak (siswa).";
    } else {
        $pass_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, role, nama_lengkap, kelas, parent_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute([$username, $pass_hash, $role, $nama_lengkap, $kelas, $parent_id])) {
            // Simpan ke parent_student jika ortu
            if ($role === 'ortu') {
                $newParentId = $pdo->lastInsertId();
                $stmtRelasi = $pdo->prepare("INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)");
                $stmtRelasi->execute([$newParentId, $parent_id]);
            }
            header("refresh:2;url=manage_users.php");
            $success = "Akun berhasil dibuat! Tunggu sebentar..";
        } else {
            $error = "Terjadi kesalahan saat menyimpan data.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Daftar - 404FeelingFound</title>
<style>
  body {
    background: linear-gradient(135deg, #E6F2FF 0%, #F0F9FF 100%);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
  }
  .container {
    background: #fff;
    padding: 40px 60px;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 500px;
    text-align: center;
  }
  h1 {
    color: #5D9CEC;
    margin-bottom: 15px;
    font-weight: 700;
    font-size: 2.5rem;
  }
  p.subtitle {
    color: #434A54;
    margin-bottom: 30px;
  }
  form {
    text-align: left;
  }
  input, select {
    width: 100%;
    padding: 14px 18px;
    margin-bottom: 25px;
    border: 2px solid #E6E9ED;
    border-radius: 10px;
    font-size: 1.05rem;
    background-color: #F5F7FA;
    transition: border-color 0.3s ease;
  }
  input:focus, select:focus {
    outline: none;
    border-color: #5D9CEC;
    background-color: #fff;
  }
  button {
    width: 100%;
    padding: 16px;
    background-color: #5D9CEC;
    border: none;
    color: #fff;
    font-weight: 600;
    font-size: 1.1rem;
    border-radius: 10px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.3s ease;
  }
  button:hover {
    background-color: #8BB9F0;
    transform: translateY(-3px);
  }
  .alert-error {
    background-color: #FDEDEE;
    color: #ED5565;
    border-left: 5px solid #ED5565;
    padding: 15px;
    margin-bottom: 25px;
    border-radius: 10px;
    font-size: 1rem;
  }
  .alert-success {
    background-color: #E7F9EF;
    color: #3C763D;
    border-left: 5px solid #3C763D;
    padding: 15px;
    margin-bottom: 25px;
    border-radius: 10px;
    font-size: 1rem;
  }
</style>
</head>
<body>

  <div class="container" role="main">
    <h1>404FeelingFound</h1>
    <p class="subtitle">Buat akun baru untuk mulai menggunakan aplikasi kami</p>

    <?php if (!empty($error)): ?>
      <div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php elseif (!empty($success)): ?>
      <div class="alert-success" role="alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('admin/tambah_user.php') ?>" novalidate>
      <input
        type="text"
        name="username"
        placeholder="Username"
        required
        minlength="3"
        maxlength="50"
        autocomplete="username"
      />

      <input
        type="password"
        name="password"
        placeholder="Password"
        required
        minlength="6"
        autocomplete="new-password"
      />

      <input
        type="text"
        name="nama_lengkap"
        placeholder="Nama Lengkap"
        required
        maxlength="100"
      />

      <select name="role" id="role-select" required>
        <option value="" disabled selected>-- Pilih Role --</option>
        <option value="admin">Admin</option>
        <option value="guru">Guru</option>
        <option value="siswa">Siswa</option>
        <option value="ortu">Orang Tua</option>
      </select>

      <select name="kelas" id="kelas-group" style="display:none;">
        <option value="" disabled selected>-- Pilih Kelas --</option>
        <option value="X RPL 1">X RPL 1</option>
        <option value="X RPL 2">X RPL 2</option>
        <option value="X DKV 1">X DKV 1</option>
        <option value="X DKV 2">X DKV 2</option>
        <option value="X TKJ 1">X TKJ 1</option>
        <option value="X TKJ 2">X TKJ 2</option>
        <option value="XI RPL 1">XI RPL 1</option>
        <option value="XI RPL 2">XI RPL 2</option>
        <option value="XI DKV 1">XI DKV 1</option>
        <option value="XI DKV 2">XI DKV 2</option>
        <option value="XI DKV 3">XI DKV 3</option>
        <option value="XI RPL 2">XI RPL 2</option>
        <option value="XI TKJ">XI TKJ</option>
        <option value="XII TKJ 1">XII TKJ 1</option>
        <!-- Tambahkan kelas lain sesuai kebutuhan -->
      </select>

      <select name="parent_id" id="parent-id-group" style="display:none;">
        <option value="" disabled selected>-- Pilih Anak (Siswa) --</option>
        <?php foreach ($siswaList as $siswa): ?>
          <option value="<?= htmlspecialchars($siswa['id']) ?>">
            <?= htmlspecialchars($siswa['nama_lengkap']) ?> (<?= htmlspecialchars($siswa['kelas']) ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Daftar</button>
    </form>
  </div>

<script>
  const roleSelect = document.getElementById('role-select');
  const kelasGroup = document.getElementById('kelas-group');
  const parentIdGroup = document.getElementById('parent-id-group');

  function toggleFields() {
    const role = roleSelect.value;
    kelasGroup.style.display = role === 'siswa' ? 'block' : 'none';
    parentIdGroup.style.display = role === 'ortu' ? 'block' : 'none';
  }

  roleSelect.addEventListener('change', toggleFields);
  window.addEventListener('DOMContentLoaded', toggleFields);
</script>

</body>
</html>