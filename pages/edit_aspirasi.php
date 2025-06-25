<?php
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';
require_once __DIR__ . '/../auth/auth.php';

if ($_SESSION['role'] !== 'siswa') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Ambil ID aspirasi
$id = $_GET['id'] ?? 0;

// Ambil aspirasi milik user
$stmt = $pdo->prepare("SELECT * FROM aspirations WHERE id = ? AND (user_id = ? OR is_anonymous = 1)");
$stmt->execute([$id, $_SESSION['user_id']]);
$aspirasi = $stmt->fetch();

if (!$aspirasi) {
    echo "Aspirasi tidak ditemukan atau bukan milikmu 😢";
    exit();
}

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['aspiration_content'];
    $target = $_POST['aspiration_target'];
    $is_anonymous = isset($_POST['aspiration_anonymous']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE aspirations SET content = ?, target = ?, is_anonymous = ? WHERE id = ? AND (user_id = ? OR is_anonymous = 1)");
    $stmt->execute([$content, $target, $is_anonymous, $id, $_SESSION['user_id']]);

   header("Location: edit_aspirasi.php?id=$id&success=1");
exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Aspirasi</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .container { max-width: 600px; margin: 50px auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Edit Aspirasi</h2>
        <form method="POST">
            <div class="form-group">
                <label for="aspiration_content">Isi Aspirasi/Keluhan</label>
                <textarea name="aspiration_content" id="aspiration_content" rows="4" class="form-control" required><?= htmlspecialchars($aspirasi['content']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Kirim ke:</label><br>
                <?php
                $targets = ['guru' => 'Guru', 'ortu' => 'Ortu', 'guru,ortu' => 'Guru & Ortu', 'public' => 'Publik'];
                foreach ($targets as $value => $label):
                ?>
                    <input type="radio" name="aspiration_target" value="<?= $value ?>" id="target_<?= $value ?>" <?= $aspirasi['target'] === $value ? 'checked' : '' ?>>
                    <label for="target_<?= $value ?>"><?= $label ?></label><br>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <input type="checkbox" name="aspiration_anonymous" id="aspiration_anonymous" <?= $aspirasi['is_anonymous'] ? 'checked' : '' ?>>
                <label for="aspiration_anonymous">Kirim secara anonim</label>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="<?= base_url('pages/dashboard_siswa.php') ?>" class="btn">Batal</a>
        </form>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: 'Aspirasi kamu sudah diperbarui 🎉',
    confirmButtonText: 'Oke!',
    confirmButtonColor: '#ff66b2'
}).then((result) => {
            if (result.isConfirmed) {
                window.location.href = <?= base_url('pages/dashboard_siswa.php') ?>;
            }
        });
</script>
<?php endif; ?>

</body>
</html>