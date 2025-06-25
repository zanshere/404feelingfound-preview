<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/auth.php';

// Pastikan hanya guru
if ($_SESSION['role'] !== 'guru') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Ambil ID dari student_remedial
$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT sr.*, u.nama_lengkap, u.kelas, ra.subject 
    FROM student_remedial sr
    JOIN users u ON sr.student_id = u.id
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    WHERE sr.id = ? AND ra.teacher_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$data = $stmt->fetch();

if (!$data) {
    echo "Data tidak ditemukan.";
    exit();
}

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_remedial'])) {
    $score = $_POST['score'];
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;

    $update = $pdo->prepare("UPDATE student_remedial SET score = ?, is_completed = ? WHERE id = ?");
    $update->execute([$score, $is_completed, $id]);

    header("Location: " . base_url('pages/dashboard_guru.php?remed_updated=1'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Edit Remedial</title>
  <link href="https://fonts.googleapis.com/css2?family=Quicksand&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Quicksand', sans-serif;
      background: #fff0f5;
      margin: 0;
      padding: 40px;
    }
    .form-box {
      max-width: 600px;
      margin: auto;
      background: white;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #b24179;
      margin-bottom: 25px;
    }
    label {
      display: block;
      margin: 15px 0 5px;
      color: #4d3c45;
    }
    input[type="number"], input[type="text"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    .checkbox {
      margin-top: 15px;
    }
    .btn {
      margin-top: 25px;
      padding: 10px 20px;
      background-color: #b35d88;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }
    .btn:hover {
      background-color: #9b4975;
    }
    .back-link {
      display: inline-block;
      margin-top: 20px;
      color: #b35d88;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <div class="form-box">
    <h2>Edit Nilai Remedial</h2>

    <form method="POST">
      <label>Nama Siswa</label>
      <input type="text" value="<?= htmlspecialchars($data['nama_lengkap']) ?> (<?= $data['kelas'] ?>)" readonly>

      <label>Mata Pelajaran</label>
      <input type="text" value="<?= htmlspecialchars($data['subject']) ?>" readonly>

      <label>Nilai Remedial</label>
      <input type="number" name="score" min="0" max="100" value="<?= $data['score'] ?>" required>

      <div class="checkbox">
        <label>
          <input type="checkbox" name="is_completed" value="1" <?= $data['is_completed'] ? 'checked' : '' ?>>
          Tandai sebagai selesai
        </label>
      </div>

      <button type="submit" name="update_remedial" class="btn">Simpan Perubahan</button>
    </form>

    <a href="<?= base_url('pages/dashboard_guru.php') ?>" class="back-link">← Kembali ke Dashboard</a>
  </div>
</body>
</html>