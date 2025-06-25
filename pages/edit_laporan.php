<?php
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';
require_once __DIR__ . '/../auth/auth.php';

if ($_SESSION['role'] !== 'siswa') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

$id = $_GET['id'] ?? 0;

// Ambil data laporan
$stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$laporan = $stmt->fetch();

if (!$laporan) {
    die("Laporan tidak ditemukan.");
}

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE reports SET title = ?, content = ?, category = ?, is_anonymous = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$title, $content, $category, $is_anonymous, $id, $_SESSION['user_id']]);

    header('Location: ' . base_url('pages/dashboard_siswa.php?report_edited=1'));
exit();

}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Laporan</title>
    <link rel="stylesheet" href="<?=  base_url('assets/css/style.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #fff7fb; padding: 20px; font-family: sans-serif; }
        .form-container { background: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; }
        button { background: #e94e77; color: white; border: none; padding: 10px 15px; border-radius: 5px; }
        a { display: inline-block; margin-top: 15px; color: #e94e77; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Edit Laporan</h2>
    <form method="POST">
        <div class="form-group">
            <label>Judul</label>
            <input type="text" name="title" value="<?= htmlspecialchars($laporan['title']) ?>" required>
        </div>
        <div class="form-group">
            <label>Isi</label>
            <textarea name="content" rows="4" required><?= htmlspecialchars($laporan['content']) ?></textarea>
        </div>
        <div class="form-group">
            <label>Kategori</label>
            <select name="category" required>
                <option value="kinerja_osis" <?= $laporan['category'] === 'kinerja_osis' ? 'selected' : '' ?>>Kinerja OSIS</option>
                <option value="kinerja_guru" <?= $laporan['category'] === 'kinerja_guru' ? 'selected' : '' ?>>Kinerja Guru</option>
                <option value="kasus" <?= $laporan['category'] === 'kasus' ? 'selected' : '' ?>>Kasus</option>
            </select>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="is_anonymous" <?= $laporan['is_anonymous'] ? 'checked' : '' ?>> Kirim secara anonim</label>
        </div>
        <button type="submit">Simpan Perubahan</button>
    </form>
    <a href="<?= base_url('pages/dashboard_siswa.php') ?>">← Kembali ke Dashboard</a>
</div>

</body>
</html>
