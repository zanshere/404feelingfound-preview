<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/auth.php';

// Only guru can access this pages
if ($_SESSION['role'] !== 'guru') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Remedial Selesai
$remedial_selesai = $pdo->prepare("
    SELECT sr.*, u.nama_lengkap, u.kelas, ra.subject, ra.description, ra.deadline, rs.id as score_id, rs.score
    FROM student_remedial sr
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    JOIN users u ON sr.student_id = u.id
    JOIN remedial_scores rs ON rs.student_id = sr.student_id AND rs.subject = ra.subject
    WHERE ra.teacher_id = ? AND sr.is_completed = 1
    ORDER BY sr.created_at DESC
");
$remedial_selesai->execute([$_SESSION['user_id']]);

// Remedial Belum Selesai
$remedial_belum = $pdo->prepare("
    SELECT sr.*, u.nama_lengkap, u.kelas, ra.subject, ra.description, ra.deadline 
    FROM student_remedial sr
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    JOIN users u ON sr.student_id = u.id
    WHERE ra.teacher_id = ? AND sr.is_completed = 0
    ORDER BY sr.created_at DESC
");
$remedial_belum->execute([$_SESSION['user_id']]);
$remedial_belum = $remedial_belum->fetchAll(PDO::FETCH_ASSOC);


// Add this query before using $remedial_assignments
$remedial_assignments = $pdo->query("
    SELECT * FROM remedial_assignments 
    WHERE teacher_id = " . $_SESSION['user_id'] . "
    ORDER BY deadline ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Redirect jika bukan guru
if ($_SESSION['role'] !== 'guru') {
    header('Location: login.php');
    exit();
}

// Ambil data mood siswa per kelas
$remedial_selesai = $pdo->prepare("
    SELECT sr.*, u.nama_lengkap, u.kelas, ra.subject, ra.description, ra.deadline 
    FROM student_remedial sr
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    JOIN users u ON sr.student_id = u.id
    WHERE ra.teacher_id = ? AND sr.is_completed = 1
    ORDER BY sr.created_at DESC
");
$remedial_selesai->execute([$_SESSION['user_id']]);

// Ambil aspirasi untuk guru dengan data kelas
$aspirations = $pdo->query("
    SELECT a.*, u.nama_lengkap, u.kelas 
    FROM aspirations a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.target LIKE '%guru%'
    ORDER BY u.kelas, a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan aspirasi berdasarkan kelas
$aspirations_by_class = [];
foreach ($aspirations as $aspiration) {
    $class = $aspiration['kelas'] ?? 'Tanpa Kelas';
    if (!isset($aspirations_by_class[$class])) {
        $aspirations_by_class[$class] = [];
    }
    $aspirations_by_class[$class][] = $aspiration;
}

// Ambil laporan
$reports = $pdo->query("
    SELECT r.*, u.nama_lengkap 
    FROM reports r 
    LEFT JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Hitung total aspirasi dan laporan
$total_aspirations = count($aspirations);
$total_reports = count($reports);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_remedial'])) {
    $subject = $_POST['subject'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['remedial_file']) && $_FILES['remedial_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = base_url('uploads/remedial/');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['remedial_file']['name']);
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['remedial_file']['tmp_name'], $target_path)) {
            $file_path = $target_path;
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO remedial_assignments 
        (teacher_id, subject, description, file_path, deadline)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $subject, $description, $file_path, $deadline]);
    
    header('Location: dashboard_guru.php?remedial_uploaded=1');
    exit();
}

// Proses balas aspirasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_aspiration'])) {
    $aspiration_id = $_POST['aspiration_id'];
    $response = $_POST['response'];
    
    $stmt = $pdo->prepare("
        UPDATE aspirations 
        SET response = ?, responder_id = ?, responded_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$response, $_SESSION['user_id'], $aspiration_id]);
    
    header('Location: dashboard_guru.php?aspiration_replied=1');
    exit();
}
// Proses CRUD remedial
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_remed'])) {
        // Create/Update remedial
        $student_id = $_POST['student_id'];
        $subject = $_POST['subject'];
        $score = $_POST['score'];
        
        $stmt = $pdo->prepare("
            INSERT INTO remedial_scores 
            (student_id, subject, score, is_completed, teacher_id, created_at)
            VALUES (?, ?, ?, 0, ?, NOW())
            ON DUPLICATE KEY UPDATE
            score = VALUES(score),
            teacher_id = VALUES(teacher_id),
            updated_at = NOW()
        ");
        $stmt->execute([$student_id, $subject, $score, $_SESSION['user_id']]);
        
        header('Location: dashboard_guru.php?remed_success=1');
        exit();
        
    } elseif (isset($_POST['update_remed'])) {
        // Update status remedial
        $remed_id = $_POST['remed_id'];
        $is_completed = isset($_POST['is_completed']) ? 1 : 0;
        
        $stmt = $pdo->prepare("
            UPDATE remedial_scores 
            SET is_completed = ?, updated_at = NOW()
            WHERE id = ? AND teacher_id = ?
        ");
        $stmt->execute([$is_completed, $remed_id, $_SESSION['user_id']]);
        
        header('Location: dashboard_guru.php?remed_updated=1');
        exit();
    }
}

// Proses hapus remedial
if (isset($_GET['delete_remed'])) {
    $remed_id = (int)$_GET['delete_remed'];
    
    $stmt = $pdo->prepare("
        DELETE FROM remedial_scores 
        WHERE id = ? AND teacher_id = ?
    ");
    $stmt->execute([$remed_id, $_SESSION['user_id']]);
    
    header('Location: dashboard_guru.php?remed_deleted=1');
    exit();
}

// Ambil daftar siswa untuk dropdown
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.nama_lengkap, u.kelas
    FROM student_remedial sr
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    JOIN users u ON sr.student_id = u.id
    WHERE ra.teacher_id = ?
    ORDER BY u.kelas, u.nama_lengkap
");
$stmt->execute([$_SESSION['user_id']]);
$siswa_list = $stmt->fetchAll();


// Ambil data remedial yang belum selesai
$remedial_scores = $pdo->query("
    SELECT rs.*, u.nama_lengkap, u.kelas 
    FROM remedial_scores rs
    JOIN users u ON rs.student_id = u.id
    WHERE rs.is_completed = 0
    ORDER BY rs.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

    // Handle file upload
    $file_path = null;
    if (isset($_FILES['remedial_file']) && $_FILES['remedial_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = base_url('uploads/remedial/');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['remedial_file']['name']);
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['remedial_file']['tmp_name'], $target_path)) {
            $file_path = $target_path;
        }
    
    // Mulai transaction
    $pdo->beginTransaction();
    
    try {
        // Insert remedial assignment
        $stmt = $pdo->prepare("
            INSERT INTO remedial_assignments 
            (teacher_id, subject, description, file_path, deadline)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $subject, $description, $file_path, $deadline]);
        $assignment_id = $pdo->lastInsertId();
        
        // Insert untuk setiap siswa yang dipilih
        $stmt = $pdo->prepare("
            INSERT INTO student_remedial 
            (assignment_id, student_id)
            VALUES (?, ?)
        ");
        
        foreach ($student_ids as $student_id) {
            $stmt->execute([$assignment_id, $student_id]);
        }
        
        $pdo->commit();
        header('Location: dashboard_guru.php?remedial_uploaded=1');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        // Handle error
        die("Terjadi kesalahan: " . $e->getMessage());
    }
}

// Ambil daftar siswa untuk dropdown (multi-select)
$stmt = $pdo->query("
    SELECT id, nama_lengkap, kelas
    FROM users
    WHERE role = 'siswa'
    ORDER BY kelas, nama_lengkap
");
$siswa_list = $stmt->fetchAll();

// Ambil data remedial yang belum dinilai
$remedial_scores = $pdo->prepare("
    SELECT sr.*, u.nama_lengkap, u.kelas, ra.subject, ra.description, ra.deadline 
    FROM student_remedial sr
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    JOIN users u ON sr.student_id = u.id
    WHERE ra.teacher_id = ? AND sr.is_completed = 0
    ORDER BY ra.deadline ASC
");
$remedial_scores->execute([$_SESSION['user_id']]);
$remedial_scores = $remedial_scores->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - 404FeelingFound</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* General Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #434a54;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        .header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #5D9CEC;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        /* New Button Style */
        .btn-primary-action {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            cursor: pointer;
        }
        
        .btn-primary-action:hover {
            background-color: #3e8e41;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }
        
        .btn-primary-action i {
            margin-right: 8px;
        }
        
        /* Improved Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            background-color: #f9f9f9;
        }
        
        .form-control:focus {
            border-color: #5D9CEC;
            box-shadow: 0 0 0 3px rgba(93, 156, 236, 0.2);
            outline: none;
            background-color: white;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        /* Checkbox Style */
        .checkbox-container {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .checkbox-container input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            accent-color: #5D9CEC;
        }
        
        /* Accordion Styles */
        .accordion {
            margin: 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .accordion-item {
            background: white;
            margin-bottom: 5px;
        }
        
        .accordion-header {
            padding: 15px 20px;
            background: #5D9CEC;
            color: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }
        
        .accordion-header:hover {
            background: #4A89DC;
        }
        
        .accordion-header i {
            transition: transform 0.3s;
        }
        
        .accordion-header.active i {
            transform: rotate(180deg);
        }
        
        .accordion-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: white;
        }
        
        .accordion-content.show {
            max-height: 5000px;
            padding: 20px;
        }
        
        /* Tab Styles */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab:hover {
            color: #5D9CEC;
        }
        
        .tab.active {
            border-bottom-color: #5D9CEC;
            color: #5D9CEC;
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background-color: #5D9CEC;
            color: white;
            font-weight: 500;
        }
        
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .table tr:hover {
            background-color: #f1f1f1;
        }
        
        /* Search Box */
        .search-box {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 100%;
            margin-bottom: 15px;
            font-size: 16px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>');
            background-repeat: no-repeat;
            background-position: 15px center;
            background-size: 20px;
            padding-left: 45px;
            transition: all 0.3s ease;
        }
        
        .search-box:focus {
            border-color: #5D9CEC;
            box-shadow: 0 0 0 3px rgba(93, 156, 236, 0.2);
            outline: none;
        }
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background-color: #5D9CEC;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #4A89DC;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .btn-danger {
            background-color: #d9534f;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c9302c;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .close {
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: #333;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-bottom: none;
                border-left: 2px solid transparent;
            }
            
            .tab.active {
                border-bottom: none;
                border-left: 2px solid #5D9CEC;
            }
            
            .modal-content {
                width: 90%;
                margin: 10% auto;
            }
        }
        
        /* New Status Badges */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        /* File Input Style */
        .file-input-container {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-button {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px 15px;
            background-color: #f9f9f9;
            color: #555;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-input-button:hover {
            background-color: #f1f1f1;
        }
        
        .file-input-button i {
            margin-right: 8px;
        }
        
        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-name {
            margin-top: 5px;
            font-size: 14px;
            color: #666;
            display: none;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <h1 class="logo"><i class="fas fa-chalkboard-teacher"></i>404FeelingFound</h1>
            <nav>
                <span style="margin-right: 15px; font-weight: 500;">Halo, <?= htmlspecialchars($_SESSION['nama']) ?></span>
                <a href="<?= base_url('auth/logout.php') ?>" class="btn btn-primary"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
       <br><br> <!-- New Primary Action Button -->
       <button class="btn-primary-action" onclick="window.location.href='<?= base_url('index.php') ?>'">
    <i class="fas fa-plus"></i> HOME
        </button>  <button class="btn-primary-action" onclick="window.location.href='<?= base_url('admin/kelola_mood.php') ?>'">
    <i class="fas fa-plus"></i> MOOD STATS
        </button>
       <br><br>
        <!-- Remedial Form Accordion -->
        <div class="accordion">
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-plus-circle"></i> Buat Remedial dan Nilai Siswa</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content">
                    <form method="POST" action="<?= base_url('functions/proses_remedial.php') ?>" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Mata Pelajaran:</label>
                            <input type="text" name="subject" class="form-control" required placeholder="Masukkan mata pelajaran">
                        </div>
                        
                        <div class="form-group">
                            <label>Deskripsi:</label>
                            <textarea name="description" class="form-control" rows="3" required placeholder="Masukkan deskripsi remedial"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>File Materi (opsional):</label>
                            <div class="file-input-container">
                                <div class="file-input-button">
                                    <span><i class="fas fa-paperclip"></i> Pilih File</span>
                                    <span id="file-chosen">Tidak ada file dipilih</span>
                                </div>
                                <input type="file" name="remedial_file" id="remedial-file" class="file-input">
                                <div id="file-name" class="file-name"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Deadline:</label>
                            <input type="date" name="deadline" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="student_id">Pilih Siswa</label>
                            <select name="student_id" id="student_id" class="form-control" required>
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($siswa_list as $siswa): ?>
                                    <option value="<?= $siswa['id'] ?>">
                                        <?= htmlspecialchars($siswa['nama_lengkap']) ?> (<?= $siswa['kelas'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="score">Nilai Remedial</label>
                            <input type="number" name="score" id="score" class="form-control" min="0" max="100" required placeholder="Masukkan nilai (0-100)">
                        </div>

                        <div class="checkbox-container">
                            <input type="checkbox" name="is_completed" id="is_completed" value="1">
                            <label for="is_completed">Tandai sebagai selesai</label>
                        </div>

                        <button type="submit" name="submit_remedial" class="btn btn-primary" style="padding: 12px 24px;">
                            <i class="fas fa-paper-plane"></i> Kirim Remedial
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Remedial Done Accordion -->
        <div class="accordion">
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-check-circle"></i> Remedial yang Sudah Diselesaikan</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content">
                    <input type="text" id="searchDone" placeholder="Cari siswa/pelajaran/kelas..." 
                           class="search-box" onkeyup="filterTable('searchDone', 'tableDone')">
                    
                    <?php if (!empty($remedial_selesai)): ?>
                        <div class="table-responsive">
                            <table class="table" id="tableDone">
                                <thead>
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Pelajaran</th>
                                        <th>Nilai</th>
                                        <th>Deadline</th>
                                        <th>Tanggal Submit</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($remedial_selesai as $rem): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rem['nama_lengkap']) ?></td>
                                            <td><?= htmlspecialchars($rem['kelas']) ?></td>
                                            <td><?= htmlspecialchars($rem['subject']) ?></td>
                                            <td><?= $rem['score'] ?></td>
                                            <td><?= date('d M Y', strtotime($rem['deadline'])) ?></td>
                                            <td><?= date('d M Y', strtotime($rem['created_at'])) ?></td>
                                            <td><span class="status-badge status-completed">Selesai</span></td>
                                            <td>
                                                <a href="edit_remedial.php?id=<?= $rem['id'] ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="proses_remedial.php?hapus=<?= $rem['id'] ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Yakin hapus data ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="background: #f5f7fa; padding: 20px; border-radius: 6px; text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; color: #5D9CEC; margin-bottom: 10px;"></i>
                            <p>Belum ada remedial yang selesai.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Remedial Pending Accordion -->
        <div class="accordion">
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-clock"></i> Remedial yang Belum Diselesaikan</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content">
                    <input type="text" id="searchUndone" placeholder="Cari siswa/pelajaran/kelas..." 
                           class="search-box" onkeyup="filterTable('searchUndone', 'tableUndone')">

                    <?php if (!empty($remedial_belum)): ?>
                        <div class="table-responsive">
                            <table class="table" id="tableUndone">
                                <thead>
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Pelajaran</th>
                                        <th>Nilai</th>
                                        <th>Deadline</th>
                                        <th>Tanggal Kirim</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($remedial_belum as $rem): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rem['nama_lengkap']) ?></td>
                                            <td><?= htmlspecialchars($rem['kelas']) ?></td>
                                            <td><?= htmlspecialchars($rem['subject']) ?></td>
                                            <td><?= $rem['score'] ?></td>
                                            <td><?= date('d M Y', strtotime($rem['deadline'])) ?></td>
                                            <td><?= date('d M Y', strtotime($rem['created_at'])) ?></td>
                                            <td><span class="status-badge status-pending">Belum Selesai</span></td>
                                            <td>
                                                <a href="edit_remedial.php?id=<?= $rem['id'] ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="../functions/proses_remedial.php?hapus=<?= $rem['id'] ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Yakin hapus data ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="background: #f5f7fa; padding: 20px; border-radius: 6px; text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; color: #5D9CEC; margin-bottom: 10px;"></i>
                            <p>Semua siswa sudah menyelesaikan remedial.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aspirations Accordion -->
        <div class="accordion">
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-comments"></i> Aspirasi Siswa</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="accordion-content">
                    <div class="tabs">
                        <?php foreach ($aspirations_by_class as $class => $class_aspirations): ?>
                            <div class="tab <?= $class === array_key_first($aspirations_by_class) ? 'active' : '' ?>" 
                                 onclick="showTab('aspirations-<?= htmlspecialchars(str_replace(' ', '-', $class)) ?>')">
                                <?= htmlspecialchars($class) ?> (<?= count($class_aspirations) ?>)
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php foreach ($aspirations_by_class as $class => $class_aspirations): ?>
                        <div id="aspirations-<?= htmlspecialchars(str_replace(' ', '-', $class)) ?>" 
                             class="tab-content <?= $class === array_key_first($aspirations_by_class) ? 'active' : '' ?>">
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ($class_aspirations as $aspiration): ?>
                                    <li style="margin-bottom: 20px; padding: 15px; border-radius: 6px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: flex-start;">
                                            <div style="margin-right: 15px; font-size: 24px; color: #5D9CEC;">
                                                <i class="fas fa-comment-alt"></i>
                                            </div>
                                            <div style="flex: 1;">
                                                <p style="margin: 0 0 10px 0; font-size: 16px; line-height: 1.5;"><?= htmlspecialchars($aspiration['content']) ?></p>
                                                <small style="color: #777; display: block; margin-bottom: 10px;">
                                                    <?= $aspiration['is_anonymous'] ? 'Anonim' : htmlspecialchars($aspiration['nama_lengkap']) ?> | 
                                                    <?= date('d M Y H:i', strtotime($aspiration['created_at'])) ?>
                                                </small>
                                                
                                                <?php if ($aspiration['response']): ?>
                                                    <div style="background: #f5f7fa; padding: 15px; margin-top: 10px; border-radius: 6px; border-left: 4px solid #5D9CEC;">
                                                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                                            <i class="fas fa-reply" style="color: #5D9CEC; margin-right: 8px;"></i>
                                                            <strong style="color: #5D9CEC;">Balasan Anda:</strong>
                                                        </div>
                                                        <p style="margin: 0;"><?= htmlspecialchars($aspiration['response']) ?></p>
                                                        <small style="color: #777; display: block; margin-top: 8px;">
                                                            <?= date('d M Y H:i', strtotime($aspiration['responded_at'])) ?>
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <button onclick="openReplyModal(<?= $aspiration['id'] ?>)" 
                                                            class="btn btn-primary" style="margin-top: 10px;">
                                                        <i class="fas fa-reply"></i> Balas Aspirasi
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Reply Modal -->
        <div id="replyModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('replyModal')">&times;</span>
                <h3 style="margin-top: 0; color: #5D9CEC;"><i class="fas fa-reply"></i> Balas Aspirasi Siswa</h3>
                <form method="POST" id="replyForm">
                    <input type="hidden" name="aspiration_id" id="reply_aspiration_id">
                    <div class="form-group">
                        <label for="reply_content">Isi Balasan</label>
                        <textarea name="response" id="reply_content" rows="5" class="form-control" required 
                                  placeholder="Tulis balasan Anda untuk aspirasi siswa ini..."></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button type="button" onclick="closeModal('replyModal')" class="btn" 
                                style="margin-right: 10px; background: #f1f1f1;">
                            Batal
                        </button>
                        <button type="submit" name="reply_aspiration" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Kirim Balasan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Accordion functionality
        function toggleAccordion(header) {
            header.classList.toggle('active');
            const content = header.nextElementSibling;
            content.classList.toggle('show');
        }
        
        // Tab functionality
        function showTab(tabId) {
            // Hide all tab contents in the same accordion
            const accordion = event.currentTarget.closest('.accordion-content');
            accordion.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Deactivate all tabs in the same accordion
            accordion.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Activate clicked tab
            event.currentTarget.classList.add('active');
        }
        
        // Modal functions
        function openReplyModal(aspirationId) {
            document.getElementById('reply_aspiration_id').value = aspirationId;
            document.getElementById('replyModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                document.getElementById('replyModal').style.display = 'none';
            }
        }
        
        // Filter table function
        function filterTable(inputId, tableId) {
            const input = document.getElementById(inputId).value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;

                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(input)) {
                        match = true;
                        break;
                    }
                }

                rows[i].style.display = match ? '' : 'none';
            }
        }
        
        // File input display
        const fileInput = document.getElementById('remedial-file');
        const fileChosen = document.getElementById('file-chosen');
        
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileChosen.textContent = this.files[0].name;
                } else {
                    fileChosen.textContent = 'Tidak ada file dipilih';
                }
            });
        }
        
        // Initialize first accordion as open by default
        document.addEventListener('DOMContentLoaded', function() {
            // Open first accordion item by default
            const firstAccordion = document.querySelector('.accordion-header');
            if (firstAccordion) {
                firstAccordion.classList.add('active');
                firstAccordion.nextElementSibling.classList.add('show');
            }
            
            // Add animation to primary button
            const primaryBtn = document.querySelector('.btn-primary-action');
            if (primaryBtn) {
                setTimeout(() => {
                    primaryBtn.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        primaryBtn.style.transform = '';
                    }, 300);
                }, 500);
            }
        });
    </script>
</body>
</html>