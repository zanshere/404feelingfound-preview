<?php
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Tampilkan error jika ada
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tambah remedial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_remedial'])) {
    $teacher_id = $_SESSION['user_id'];
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $student_id = $_POST['student_id'];
    $score = $_POST['score'];
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;

    // Upload file (opsional)
    $file_path = '';
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = base_url('uploads/remedial/');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = time() . '_' . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            $file_path = $target_path;
        }
    }

    // Insert remedial_assignments
    $stmt = $pdo->prepare("INSERT INTO remedial_assignments (teacher_id, subject, description, file_path, deadline) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$teacher_id, $subject, $description, $file_path, $deadline]);
    $assignment_id = $pdo->lastInsertId();

    // Insert student_remedial
    $stmt = $pdo->prepare("INSERT INTO student_remedial (assignment_id, student_id, score, is_completed, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$assignment_id, $student_id, $score, $is_completed]);

    header("Location: " . base_url('pages/dashboard_guru.php?status=sukses'));
    exit();
}

// Hapus remedial berdasarkan ID student_remedial
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    // Hapus dari student_remedial
    $stmt = $pdo->prepare("DELETE FROM student_remedial WHERE id = ?");
    $stmt->execute([$id]);

    // Opsional: bisa tambahkan hapus remedial_assignments kalau memang assignment tidak dipakai oleh siswa lain

    header("Location: " . base_url('dashboard_guru.php?remed_deleted=1'));
    exit();
}

// Update remedial (jika kamu punya form update dengan name="update_remedial")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_remedial'])) {
    $remed_id = $_POST['remed_id'];
    $score = $_POST['score'];
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;

    // Update score & status
    $stmt = $pdo->prepare("UPDATE student_remedial SET score = ?, is_completed = ? WHERE id = ?");
    $stmt->execute([$score, $is_completed, $remed_id]);

    header("Location: " . base_url('dashboard_guru.php?remed_updated=1'));
    exit();
}
?>