<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Cek apakah user login sebagai siswa
if ($_SESSION['role'] !== 'siswa') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Proses jika form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $category = $_POST['category'] ?? '';
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    // Validasi sederhana
    if (!empty($title) && !empty($content) && !empty($category)) {
        $stmt = $pdo->prepare("
            INSERT INTO reports (user_id, title, content, category, is_anonymous, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $title,
            $content,
            $category,
            $is_anonymous
        ]);

        // Redirect dengan notifikasi sukses
        header('Location: ' . base_url('pages/dashboard_siswa.php?laporan_success=1'));
        exit();
    } else {
        // Redirect dengan notifikasi gagal
        header('Location: ' . base_url('pages/dashboard_siswa.php?laporan_failed=1'));
        exit();
    }
} else {
    // Jika bukan POST, redirect saja
    header('Location: ' . base_url('pages/dashboard_siswa.php'));
    exit();
}
