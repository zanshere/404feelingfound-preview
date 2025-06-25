<?php
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Cek apakah user login sebagai siswa
if ($_SESSION['role'] !== 'siswa') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['submission_file']) && isset($_POST['remedial_id'])) {
    $remedial_id = (int)$_POST['remedial_id'];
    $user_id = $_SESSION['user_id'];

    // Validasi file
    $file = $_FILES['submission_file'];
    $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'png'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if (!in_array(strtolower($ext), $allowed_ext)) {
        die("Ekstensi file tidak diizinkan.");
    }

    $upload_dir = base_url('uploads/remedial/');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $new_filename = uniqid('tugas_') . '.' . $ext;
    $destination = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Simpan file path ke DB
        $stmt = $pdo->prepare("UPDATE student_remedial SET submission_file = ?, is_completed = 1 WHERE id = ? AND student_id = ?");
        $stmt->execute([$new_filename, $remedial_id, $user_id]);

        header("Location: " . base_url('pages/dashboard_siswa.php?upload_success=1'));
        exit();
    } else {
        die("Gagal mengunggah file.");
    }
}
?>
