<?php
// Start session first
session_start();

require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Tampilkan error jika ada
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tambah remedial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_remedial'])) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . base_url('login.php?error=not_logged_in'));
        exit();
    }
    
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
            $file_path = base_url('uploads/remedial/') . $file_name; // Store relative path
        }
    }

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert remedial_assignments
        $stmt = $pdo->prepare("INSERT INTO remedial_assignments (teacher_id, subject, description, file_path, deadline) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$teacher_id, $subject, $description, $file_path, $deadline]);
        $assignment_id = $pdo->lastInsertId();

        // Insert student_remedial
        $stmt = $pdo->prepare("INSERT INTO student_remedial (assignment_id, student_id, score, is_completed, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$assignment_id, $student_id, $score, $is_completed]);
        
        // Commit transaction
        $pdo->commit();
        
        header("Location: " . base_url('pages/dashboard_guru.php?status=sukses'));
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        header("Location: " . base_url('pages/dashboard_guru.php?status=error&msg=' . urlencode($e->getMessage())));
        exit();
    }
}

// Hapus remedial berdasarkan ID student_remedial
if (isset($_GET['hapus'])) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . base_url('login.php?error=not_logged_in'));
        exit();
    }
    
    $id = (int)$_GET['hapus'];

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get assignment_id before deleting student_remedial
        $stmt = $pdo->prepare("SELECT assignment_id FROM student_remedial WHERE id = ?");
        $stmt->execute([$id]);
        $assignment_id = $stmt->fetchColumn();
        
        // Hapus dari student_remedial
        $stmt = $pdo->prepare("DELETE FROM student_remedial WHERE id = ?");
        $stmt->execute([$id]);

        // Optional: Check if assignment has no more students and delete if needed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_remedial WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);
        $student_count = $stmt->fetchColumn();
        
        if ($student_count == 0) {
            // Delete the assignment if no students are assigned to it
            $stmt = $pdo->prepare("DELETE FROM remedial_assignments WHERE id = ?");
            $stmt->execute([$assignment_id]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        header("Location: " . base_url('pages/dashboard_guru.php?remed_deleted=1'));
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        header("Location: " . base_url('pages/dashboard_guru.php?remed_delete_error=1'));
        exit();
    }
}

// Update remedial (jika kamu punya form update dengan name="update_remedial")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_remedial'])) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . base_url('auth/login.php?error=not_logged_in'));
        exit();
    }
    
    $remed_id = $_POST['remed_id'];
    $score = $_POST['score'];
    $is_completed = isset($_POST['is_completed']) ? 1 : 0;

    try {
        // Update score & status
        $stmt = $pdo->prepare("UPDATE student_remedial SET score = ?, is_completed = ? WHERE id = ?");
        $stmt->execute([$score, $is_completed, $remed_id]);

        header("Location: " . base_url('pages/dashboard_guru.php?remed_updated=1'));
        exit();
    } catch (Exception $e) {
        header("Location: " . base_url('pages/dashboard_guru.php?remed_update_error=1'));
        exit();
    }
}
?>