<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/auth.php';

if ($_SESSION['role'] !== 'siswa') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

$mood_emojis = [
    1 => '😭',
    2 => '😞',
    3 => '😐',
    4 => '😊',
    5 => '😁'
];

// Proses mood check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mood_value'])) {
    $mood_value = (int)$_POST['mood_value'];
    $notes = $_POST['notes'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO mood_entries (user_id, mood_value, notes, date) VALUES (?, ?, ?, CURDATE())");
    $stmt->execute([$_SESSION['user_id'], $mood_value, $notes]);
    header('Location: ' . base_url('pages/dashboard_siswa.php?mood_success=1'));
    exit();
}

// Hapus mood hari ini
if (isset($_GET['hapus_mood']) && $_GET['hapus_mood'] == 1) {
    $stmt = $pdo->prepare("DELETE FROM mood_entries WHERE user_id = ? AND date = CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: ' . base_url('pages/dashboard_siswa.php?mood_deleted=1'));
    exit();
}

// Proses aspirasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aspiration_content'])) {
    $content = $_POST['aspiration_content'];
    $target = $_POST['aspiration_target'];
    $is_anonymous = isset($_POST['aspiration_anonymous']) ? 1 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO aspirations 
        (user_id, content, is_anonymous, target, response, responded_at, responder_id) 
        VALUES (?, ?, ?, ?, '', NOW(), 0)
    ");
    $stmt->execute([
       $_SESSION['user_id'],
        $content,
        $is_anonymous,
        $target
    ]);
    header('Location: ' . base_url('pages/dashboard_siswa.php?aspiration_success=1'));
    exit();
}

// Hapus aspirasi berdasarkan ID
if (isset($_GET['hapus_aspirasi'])) {
    $id_aspirasi = (int)$_GET['hapus_aspirasi'];
    $stmt = $pdo->prepare("DELETE FROM aspirations WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    $stmt->execute([$id_aspirasi, $_SESSION['user_id']]);
    header('Location: ' . base_url('pages/dashboard_siswa.php?aspiration_deleted=1'));
    exit();
}

// Ambil data mood hari ini
$stmt = $pdo->prepare("SELECT * FROM mood_entries WHERE user_id = ? AND date = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$today_mood = $stmt->fetch();

// Ambil 7 mood terakhir
$stmt2 = $pdo->prepare("SELECT * FROM mood_entries WHERE user_id = ? ORDER BY date DESC LIMIT 7");
$stmt2->execute([$_SESSION['user_id']]);
$mood_history = $stmt2->fetchAll();

// Ambil aspirasi user
$stmt3 = $pdo->prepare("
    SELECT * FROM aspirations 
    WHERE user_id = :uid
    ORDER BY created_at DESC
");
$stmt3->execute(['uid' => $_SESSION['user_id']]);


$aspirations = $stmt3->fetchAll();

// DELETE laporan
if (isset($_GET['hapus_laporan'])) {
    $id = (int)$_GET['hapus_laporan'];
    
    if (!empty($_SESSION['user_id']) && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ? AND (user_id = ? OR is_anonymous = 1)");
        $stmt->execute([$id, $_SESSION['user_id']]);

        header('Location: ' . base_url('pages/dashboard_siswa.php?report_deleted=1'));
        exit();
    } else {
        echo "Gagal menghapus laporan: ID atau user tidak valid.";
    }
}
// $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ? AND (user_id = ? OR is_anonymous = 1)");
// $stmt->execute([$id, $_SESSION['user_id']]);

// Ambil nilai remedial
$remedial_stmt = $pdo->prepare("
    SELECT sr.id, sr.assignment_id, sr.student_id, sr.score, sr.is_completed, sr.created_at,
           ra.subject, u.nama_lengkap as teacher_name
    FROM student_remedial sr
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    JOIN users u ON ra.teacher_id = u.id
    WHERE sr.student_id = ?
    ORDER BY sr.created_at DESC
");
$remedial_stmt->execute([$_SESSION['user_id']]);
$remedial_scores = $remedial_stmt->fetchAll();

$filter_category = $_GET['filter_category'] ?? '';
$reports_query = "SELECT * FROM reports WHERE user_id = ?";
$params = [$_SESSION['user_id']];
if ($filter_category) {
    $reports_query .= " AND category = ?";
    $params[] = $filter_category;
}

// submit laporan
$reports_query .= " ORDER BY created_at DESC";
$reports_stmt = $pdo->prepare($reports_query);
$reports_stmt->execute($params);
$reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses laporan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['content'], $_POST['category'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $stmt = $pdo->prepare("INSERT INTO reports (user_id, title, content, category, is_anonymous, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $is_anonymous ? null : $_SESSION['user_id'],
        $title,
        $content,
        $category,
        $is_anonymous
    ]);
    header('Location: ' . base_url('pages/dashboard_siswa.php?report_success=1'));
    exit();
}

// Hapus laporan
if (isset($_GET['hapus_laporan'])) {
    $id = (int)$_GET['hapus_laporan'];
    $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    $stmt->execute([$id, $_SESSION['user_id']]);
    header('Location: ' . base_url('pages/dashboard_siswa.php?report_deleted=1'));
    exit();
}

// Tambah penanganan upload tugas remedial oleh siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_remedial_task'])) {
    $remedial_id = (int)$_POST['remedial_id'];

    // Cek apakah remedial_id milik siswa ini
    $check_stmt = $pdo->prepare("SELECT * FROM student_remedial WHERE id = ? AND student_id = ?");
    $check_stmt->execute([$remedial_id, $_SESSION['user_id']]);
    $remedial = $check_stmt->fetch();

    if ($remedial) {
        // Upload file jika ada
        $upload_path = null;
        if (!empty($_FILES['remedial_file']['name'])) {
            $target_dir = base_url('uploads/remedial_siswa/');
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $filename = time() . "_" . basename($_FILES['remedial_file']['name']);
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($_FILES['remedial_file']['tmp_name'], $target_file)) {
                $upload_path = $target_file;
            }
        }
        

        // Update status menjadi selesai + simpan file_path
        $update_stmt = $pdo->prepare("UPDATE student_remedial SET is_completed = 1, submission_file = ? WHERE id = ?");
        $update_stmt->execute([$upload_path, $remedial_id]);
    }
    header('Location: ' . base_url('pages/dashboard_siswa.php?tab=profil&remedial_uploaded=1'));
    exit();
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Siswa - 404FeelingFound</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
    :root {
        --primary: #5D9CEC;
        --primary-dark: #4a89dc;
        --secondary: rgb(218, 247, 188);
        --accent: rgb(237, 218, 171);
        --danger: #ED5565;
        --light: #F5F7FA;
        --dark: #2c3e50;
        --gray: #ecf0f1;
        --white: #ffffff;
        --card-shadow: 0 10px 30px -15px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        --success: #4CAF50;
        --warning: #FFC107;
        --text: #333333;
        --text-light: #777777;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        background-color: #f8fafc;
        color: var(--text);
        line-height: 1.6;
    }

    /* Premium Navbar */
    .navbar {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
        padding: 15px 0;
        transition: var(--transition);
    }

    .navbar.scrolled {
        padding: 10px 0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .nav-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .logo {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary);
        text-decoration: none;
        display: flex;
        align-items: center;
    }

    .logo i {
        margin-right: 10px;
        font-size: 1.5rem;
    }

    .logo span {
        color: #FFD700;
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 25px;
    }

    .nav-links a {
        color: var(--dark);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        position: relative;
    }

    .nav-links a:hover {
        color: var(--primary);
    }

    .nav-links a::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 0;
        width: 0;
        height: 2px;
        background: var(--primary);
        transition: var(--transition);
    }

    .nav-links a:hover::after {
        width: 100%;
    }

    /* Rainbow line */
    .rainbow-line {
        height: 4px;
        background: linear-gradient(90deg,
                #5D9CEC 0%,
                #FF6B6B 20%,
                #FFD166 40%,
                #06D6A0 60%,
                #118AB2 80%,
                #073B4C 100%);
        width: 100%;
    }

    /* Rest of your existing styles... */
    .btn {
        display: inline-block;
        padding: 0.5rem 1.2rem;
        border-radius: 6px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
    }

    .btn-danger {
        background-color: var(--danger);
        color: white;
    }

    .btn-danger:hover {
        background-color: #E53935;
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 0.3rem 0.8rem;
        font-size: 0.8rem;
    }

    .main {
        padding: 2rem 0;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    h2 {
        font-size: 2rem;
        margin-bottom: 1.5rem;
        color: var(--dark);
        position: relative;
        padding-bottom: 0.5rem;
    }

    h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 4px;
        background: var(--primary);
        border-radius: 2px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        background-color: #f8fafc;
        color: var(--text);
        line-height: 1.6;
    }

    .header {
        background: linear-gradient(135deg, #5D9CEC 0%, #3A7BD5 100%);
        color: white;
        padding: 1rem 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .header .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        text-decoration: none;
    }

    .logo span {
        color: #FFD700;
    }

    .nav {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .nav span {
        font-weight: 500;
    }

    .btn {
        display: inline-block;
        padding: 0.5rem 1.2rem;
        border-radius: 6px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background-color: #4A89DC;
        transform: translateY(-2px);
    }

    .btn-danger {
        background-color: var(--danger);
        color: white;
    }

    .btn-danger:hover {
        background-color: #E53935;
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 0.3rem 0.8rem;
        font-size: 0.8rem;
    }

    .main {
        padding: 2rem 0;
    }

    h2 {
        font-size: 2rem;
        margin-bottom: 1.5rem;
        color: var(--dark);
        position: relative;
        padding-bottom: 0.5rem;
    }

    h2::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 4px;
        background: var(--primary);
        border-radius: 2px;
    }

    h3 {
        font-size: 1.4rem;
        margin-bottom: 1rem;
        color: var(--dark);
    }

    .card {
        background: var(--light);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .tab-container {
        display: flex;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 0.5rem;
        border-bottom: 1px solid #eee;
        padding-bottom: 0.5rem;
    }

    .tab {
        padding: 0.6rem 1.2rem;
        cursor: pointer;
        border-radius: 6px;
        font-weight: 500;
        color: var(--text-light);
        transition: all 0.3s ease;
        position: relative;
    }

    .tab:hover {
        color: var(--primary);
        background: rgba(93, 156, 236, 0.1);
    }

    .tab.active {
        color: var(--primary);
        background: rgba(93, 156, 236, 0.1);
        font-weight: 600;
    }

    .tab.active::after {
        content: '';
        position: absolute;
        bottom: -0.6rem;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--primary);
        border-radius: 3px 3px 0 0;
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .tab-content.active {
        display: block;
    }

    .mood-options {
        display: flex;
        gap: 1rem;
        margin: 1.5rem 0;
        flex-wrap: wrap;
    }

    .mood-options button {
        font-size: 2rem;
        background: none;
        border: 3px solid #eee;
        border-radius: 50%;
        width: 70px;
        height: 70px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mood-options button:hover {
        transform: scale(1.1);
        border-color: var(--primary);
    }

    .form-group {
        margin-bottom: 1.2rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--dark);
    }

    .form-control {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 0.95rem;
        transition: border 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(93, 156, 236, 0.2);
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    .alert {
        padding: 0.8rem 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .alert-success {
        background-color: rgba(76, 175, 80, 0.1);
        color: #2E7D32;
        border-left: 4px solid var(--success);
    }

    .alert-warning {
        background-color: rgba(255, 193, 7, 0.1);
        color: #FF8F00;
        border-left: 4px solid var(--warning);
    }

    .mood-history-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 0.8rem;
        background: rgba(245, 247, 250, 0.5);
        border-radius: 8px;
    }

    .mood-value {
        font-size: 1.5rem;
        min-width: 40px;
        text-align: center;
    }

    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        font-size: 0.8rem;
        font-weight: bold;
        margin-left: 0.5rem;
    }

    .rank-1 {
        background: linear-gradient(135deg, #FFD700 0%, #FFC600 100%);
    }

    .rank-2 {
        background: linear-gradient(135deg, #C0C0C0 0%, #B0B0B0 100%);
    }

    .rank-3 {
        background: linear-gradient(135deg, #CD7F32 0%, #B87333 100%);
    }

    .badge {
        display: inline-block;
        padding: 0.25rem 0.6rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-completed {
        background: rgba(76, 175, 80, 0.1);
        color: var(--success);
    }

    .badge-not-completed {
        background: rgba(244, 67, 54, 0.1);
        color: var(--danger);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
    }

    th,
    td {
        padding: 0.8rem;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    th {
        background-color: #f5f7fa;
        font-weight: 600;
        color: var(--dark);
    }

    tr:hover {
        background-color: rgba(93, 156, 236, 0.05);
    }

    .flex-center {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-menu {
        display: flex;
        gap: 1.5rem;
    }

    .nav-link {
        color: white;
        text-decoration: none;
        font-weight: 500;
        padding: 0.5rem 0;
        position: relative;
        transition: color 0.3s ease;
    }

    .nav-link:hover {
        color: #FFD700;
    }

    .nav-link::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background: #FFD700;
        transition: width 0.3s ease;
    }

    .nav-link:hover::after {
        width: 100%;
    }

    .responsive-table {
        overflow-x: auto;
    }

    @media (max-width: 768px) {
        .header .container {
            flex-direction: column;
            gap: 1rem;
        }

        .tab-container {
            overflow-x: auto;
            padding-bottom: 0.5rem;
            white-space: nowrap;
            display: block;
        }

        .tab {
            display: inline-block;
        }

        .mood-options {
            justify-content: center;
        }
    }
    </style>
</head>

<body>
    <!-- Premium Navbar with Rainbow Line -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="<?= base_url('index.php') ?>" class="logo">
                <i class="fas fa-heart"></i> 404<span>FeelingFound</span>
            </a>
            <div class="nav-links">
                <a href="<?= base_url('index.php') ?>"><i class="fas fa-home"></i> Beranda</a>
                <span class="flex-center"><i class="fas fa-user-circle"></i>
                    <?= htmlspecialchars($_SESSION['nama']) ?></span>
                <a href="<?= base_url('auth/logout.php') ?>" class="btn btn-primary"><i class="fas fa-sign-out-alt"></i>
                    Logout</a>
            </div>
        </div>
    </nav>
    <div class="rainbow-line"></div>

    <main class="main">
        <div class="container">
            <h2><i class="fas fa-tachometer-alt"></i> Dashboard Siswa</h2>

            <div class="tab-container">
                <div class="tab active" onclick="switchTab('mood')"><i class="fas fa-smile"></i> Mood Check</div>
                <div class="tab" onclick="switchTab('aspirasi')"><i class="fas fa-comment-dots"></i> Aspirasi</div>
                <div class="tab" onclick="switchTab('laporan')"><i class="fas fa-flag"></i> Laporan</div>
                <div class="tab" onclick="switchTab('profil')"><i class="fas fa-user"></i> Remedial</div>
            </div>

            <!-- Tab Mood -->
            <div id="mood-tab" class="tab-content active">
                <div class="card">
                    <h3><i class="fas fa-heart"></i> Bagaimana perasaanmu hari ini?</h3>
                    <?php if (isset($_GET['mood_success'])): ?>
                    <div class="alert alert-success">Mood harian berhasil dicatat!</div>
                    <?php elseif (isset($_GET['mood_deleted'])): ?>
                    <div class="alert alert-success">Mood hari ini telah dihapus.</div>
                    <?php endif; ?>
                    <?php if ($today_mood): ?>
                    <div class="alert">
                        <p>Kamu sudah mengisi mood hari ini:</p>
                        <div class="flex-center" style="margin: 0.5rem 0;">
                            <span class="mood-value"><?= $mood_emojis[$today_mood['mood_value']] ?? '😊' ?></span>
                            <?php if ($today_mood['notes']): ?>
                            <span>"<?= htmlspecialchars($today_mood['notes']) ?>"</span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="edit_mood.php?date=<?= $today_mood['date'] ?>" class="btn btn-primary btn-sm"><i
                                    class="fas fa-edit"></i> Edit</a>
                            <a href="dashboard_siswa.php?hapus_mood=1" class="btn btn-danger btn-sm btn-hapus-mood"><i
                                    class="fas fa-trash"></i> Hapus</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <form method="POST">
                        <div class="mood-options">
                            <?php foreach ($mood_emojis as $val => $emoji): ?>
                            <button type="button" onclick="setMood(<?= $val ?>)"><?= $emoji ?></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="mood_value" id="mood_value">
                        <div class="form-group">
                            <label for="notes"><i class="fas fa-sticky-note"></i> Catatan (opsional)</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control"
                                placeholder="Apa yang membuatmu merasa seperti ini?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3><i class="fas fa-history"></i> Riwayat Mood 7 Hari Terakhir</h3>
                    <?php foreach ($mood_history as $mood): ?>
                    <div class="mood-history-item">
                        <span class="mood-value"><?= $mood_emojis[$mood['mood_value']] ?? '😊' ?></span>
                        <span><?= date('d M Y', strtotime($mood['date'])) ?></span>
                        <?php if ($mood['notes']): ?>
                        <span style="color: var(--text-light);">"<?= htmlspecialchars($mood['notes']) ?>"</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab Aspirasi -->
            <div id="aspirasi-tab" class="tab-content">
                <div class="card">
                    <?php if (isset($_GET['aspiration_success'])): ?>
                    <div class="alert alert-success">Aspirasi berhasil dikirim!</div>
                    <?php endif; ?>

                    <h3><i class="fas fa-paper-plane"></i> Kirim Aspirasi</h3>
                    <form method="POST" id="aspirationForm">
                        <div class="form-group">
                            <label for="aspiration_content"><i class="fas fa-comment"></i> Isi Aspirasi/Keluhan</label>
                            <textarea name="aspiration_content" id="aspiration_content" rows="3" class="form-control"
                                required placeholder="Tuliskan aspirasi atau keluhanmu di sini..."></textarea>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-users"></i> Kirim ke:</label>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <div>
                                    <input type="radio" name="aspiration_target" value="guru" id="target_guru" checked>
                                    <label for="target_guru"><i class="fas fa-chalkboard-teacher"></i> Guru</label>
                                </div>
                                <div>
                                    <input type="radio" name="aspiration_target" value="ortu" id="target_ortu">
                                    <label for="target_ortu"><i class="fas fa-user-friends"></i> Ortu</label>
                                </div>
                                <div>
                                    <input type="radio" name="aspiration_target" value="guru,ortu" id="target_both">
                                    <label for="target_both"><i class="fas fa-users"></i> Guru & Ortu</label>
                                </div>
                                <div>
                                    <input type="radio" name="aspiration_target" value="public" id="target_public">
                                    <label for="target_public"><i class="fas fa-globe"></i> Publik</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="checkbox" name="aspiration_anonymous" id="aspiration_anonymous">
                            <label for="aspiration_anonymous"><i class="fas fa-user-secret"></i> Sampaikan secara
                                anonim</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim
                            Aspirasi</button>
                    </form>
                </div>

                <div class="card">
                    <h3><i class="fas fa-inbox"></i> Aspirasi yang Telah Dikirim</h3>
                    <?php foreach ($aspirations as $asp): ?>
                    <div style="margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 1rem;">
                        <p><?= htmlspecialchars($asp['content']) ?></p>
                        <?php if (!empty($asp['response'])): ?>
                        <div style="background: #f0f8ff; padding: 0.8rem; margin-top: 0.5rem; border-radius: 6px;">
                            <strong><i class="fas fa-reply"></i> Balasan Guru:</strong>
                            <p><?= htmlspecialchars($asp['response']) ?></p>
                        </div>
                        <?php endif; ?>
                        <small style="display: block; margin-top: 0.5rem; color: var(--text-light);">
                            <i class="fas fa-users"></i> Dikirim ke: <?= $asp['target'] ?> |
                            <i class="<?= $asp['is_anonymous'] ? 'fas fa-user-secret' : 'fas fa-user' ?>"></i>
                            <?= $asp['is_anonymous'] ? 'Anonim' : 'Bukan Anonim' ?> |
                            <i class="fas fa-clock"></i> <?= date('d M Y H:i', strtotime($asp['created_at'])) ?>
                        </small>
                        <div style="margin-top: 0.5rem;">
                            <a href="edit_aspirasi.php?id=<?= $asp['id'] ?>" class="btn btn-primary btn-sm"><i
                                    class="fas fa-edit"></i> Edit</a>
                            <a href="dashboard_siswa.php?hapus_aspirasi=<?= $asp['id'] ?>"
                                class="btn btn-danger btn-sm btn-hapus-aspirasi"><i class="fas fa-trash"></i> Hapus</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tab Laporan -->
            <div id="laporan-tab" class="tab-content">
                <div class="card">
                    <h3><i class="fas fa-edit"></i> Buat Laporan</h3>
                    <form method="POST" action="<?= base_url('functions/submit_report.php') ?>">
                        <div class="form-group">
                            <label for="report_title"><i class="fas fa-heading"></i> Judul</label>
                            <input type="text" name="title" id="report_title" class="form-control" required
                                placeholder="Judul laporan">
                        </div>
                        <div class="form-group">
                            <label for="report_content"><i class="fas fa-align-left"></i> Isi Laporan</label>
                            <textarea name="content" id="report_content" rows="3" class="form-control" required
                                placeholder="Deskripsi lengkap laporan"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="report_category"><i class="fas fa-tag"></i> Kategori</label>
                            <select name="category" id="report_category" class="form-control" required>
                                <option value="kinerja_osis">Kinerja OSIS</option>
                                <option value="kinerja_guru">Kinerja Guru</option>
                                <option value="kasus">Kasus</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="checkbox" name="is_anonymous" id="report_anonymous" checked>
                            <label for="report_anonymous"><i class="fas fa-user-secret"></i> Kirim secara anonim</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim
                            Laporan</button>
                    </form>
                </div>

                <div class="form-group">
                    <form method="GET" action="<?= base_url('pages/dashboard_siswa.php') ?>">
                        <input type="hidden" name="tab" value="laporan">
                        <label for="filter_category"><i class="fas fa-filter"></i> Filter Kategori:</label>
                        <select name="filter_category" id="filter_category" onchange="this.form.submit()"
                            class="form-control" style="width: auto; display: inline-block; margin-left: 0.5rem;">
                            <option value="">Semua</option>
                            <option value="kinerja_osis" <?= $filter_category === 'kinerja_osis' ? 'selected' : '' ?>>
                                Kinerja OSIS</option>
                            <option value="kinerja_guru" <?= $filter_category === 'kinerja_guru' ? 'selected' : '' ?>>
                                Kinerja Guru</option>
                            <option value="kasus" <?= $filter_category === 'kasus' ? 'selected' : '' ?>>Kasus</option>
                        </select>
                    </form>
                </div>

                <div class="card">
                    <h3><i class="fas fa-list"></i> Daftar Laporan</h3>
                    <?php if (empty($reports)): ?>
                    <p style="text-align: center; color: var(--text-light);">Tidak ada laporan untuk kategori ini.</p>
                    <?php else: ?>
                    <div class="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-heading"></i> Judul</th>
                                    <th><i class="fas fa-tag"></i> Kategori</th>
                                    <th><i class="fas fa-align-left"></i> Isi</th>
                                    <th><i class="fas fa-clock"></i> Tanggal</th>
                                    <th><i class="fas fa-cog"></i> Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $rep): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rep['title']) ?></td>
                                    <td><?= htmlspecialchars($rep['category']) ?></td>
                                    <td><?= htmlspecialchars(substr($rep['content'], 0, 50)) . (strlen($rep['content']) > 50 ? '...' : '') ?>
                                    </td>
                                    <td><?= date('d M Y H:i', strtotime($rep['created_at'])) ?></td>
                                    <td>
                                        <a href="edit_laporan.php?id=<?= $rep['id'] ?>"
                                            class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                        <a href="dashboard_siswa.php?hapus_laporan=<?= $rep['id'] ?>"
                                            class="btn btn-danger btn-sm btn-hapus-laporan"><i
                                                class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Profil -->
            <div class="card">
                <h3><i class="fas fa-clipboard-check"></i> Nilai Remedial</h3>
                <?php if (empty($remedial_scores)): ?>
                <p style="text-align: center; color: var(--text-light);">Belum ada nilai remedial</p>
                <?php else: ?>
                <div class="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-book"></i> Mata Pelajaran</th>
                                <th><i class="fas fa-star"></i> Nilai</th>
                                <th><i class="fas fa-check-circle"></i> Status</th>
                                <th><i class="fas fa-chalkboard-teacher"></i> Guru</th>
                                <th><i class="fas fa-calendar-alt"></i> Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($remedial_scores as $remedial): ?>
                            <tr>
                                <td><?= htmlspecialchars($remedial['subject']) ?></td>
                                <td><?= $remedial['score'] ?></td>
                                <td>
                                    <span
                                        class="badge <?= $remedial['is_completed'] ? 'badge-completed' : 'badge-not-completed' ?>">
                                        <?= $remedial['is_completed'] ? 'Selesai' : 'Belum Selesai' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($remedial['teacher_name']) ?></td>
                                <td><?= date('d M Y', strtotime($remedial['created_at'])) ?></td>
                            </tr>

                            <?php if (!$remedial['is_completed']): ?>
                            <tr>
                                <td colspan="5">
                                    <form action="<?= base_url('functions/submit_remedial_file.php') ?>" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="remedial_id" value="<?= $remedial['id'] ?>">
                                        <label>Upload File Tugas:</label>
                                        <input type="file" name="submission_file" required>
                                        <button type="submit" class="btn btn-primary btn-sm"
                                            style="margin-top: 0.5rem;">
                                            <i class="fas fa-upload"></i> Kirim Tugas
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 10) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Rest of your existing JavaScript remains the same
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const targetTab = document.querySelector([onclick = "switchTab('${tab}')"]);
                if (targetTab) targetTab.click();
            }
        });

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const targetTab = document.querySelector([onclick = "switchTab('${tab}')"]);
                if (targetTab) targetTab.click();
            }
        });

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        function setMood(value) {
            document.getElementById('mood_value').value = value;
        }

        // Konfirmasi SweetAlert saat menghapus mood
        document.querySelectorAll('.btn-hapus-mood').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault(); // mencegah link langsung berjalan

                Swal.fire({
                    title: 'Yakin mau hapus?',
                    text: 'Mood hari ini akan hilang selamanya 😭',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ff6699',
                    cancelButtonColor: '#ccc',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                    background: '#fff0f5',
                    color: '#444',
                    customClass: {
                        popup: 'sweet-popup',
                        confirmButton: 'sweet-confirm',
                        cancelButton: 'sweet-cancel'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = this.href;
                    }
                });
            });
        });

        // Jika mood sudah dihapus, tampilkan notifikasi sukses
        <?php if (isset($_GET['mood_deleted'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Mood Dihapus!',
            text: 'Mood kamu sudah dibersihkan 🧼',
            confirmButtonColor: '#ff66b2'
        });
        <?php endif; ?>

        // Konfirmasi SweetAlert saat menghapus aspirasi
        document.querySelectorAll('.btn-hapus-aspirasi').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Yakin mau hapus?',
                    text: 'Aspirasi ini akan dihapus dan tidak bisa dikembalikan 😢',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e6739f',
                    cancelButtonColor: '#aaa',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal',
                    background: '#fff0f5',
                    color: '#444',
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = this.href;
                    }
                });
            });
        });

        // Jika aspirasi dihapus
        <?php if (isset($_GET['aspiration_deleted'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Aspirasi Dihapus',
            text: 'Satu aspirasi telah dihapus 💨',
            confirmButtonColor: '#e6739f'
        });
        <?php endif; ?>

        <?php if (isset($_GET['report_success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Laporan Dikirim!',
            text: 'Terima kasih atas laporannya 😊',
            confirmButtonColor: '#5D9CEC'
        });
        <?php endif; ?>

        <?php if (isset($_GET['report_deleted'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Laporan Dihapus!',
            text: 'Laporan berhasil dihapus 🗑',
            confirmButtonColor: '#ff6666'
        });
        <?php endif; ?>

        // SweetAlert konfirmasi hapus laporan
        const hapusBtns = document.querySelectorAll('.btn-hapus-laporan');
        hapusBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.href;
                Swal.fire({
                    title: 'Yakin mau hapus?',
                    text: 'Laporan ini tidak bisa dikembalikan 😥',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ff6699',
                    cancelButtonColor: '#ccc',
                    confirmButtonText: 'Ya, hapus!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });

        <?php if (isset($_GET['report_success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Laporan Dikirim!',
            text: 'Terima kasih atas laporannya 😊',
            confirmButtonColor: '#5D9CEC'
        });
        <?php endif; ?>

        <?php if (isset($_GET['report_edited'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Laporan Diperbarui!',
            text: 'Perubahan telah disimpan 🎉',
            confirmButtonColor: '#66CC66'
        });
        <?php endif; ?>

        <?php if (isset($_GET['report_deleted'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Laporan Dihapus!',
            text: 'Laporan berhasil dihapus 🗑',
            confirmButtonColor: '#ff6666'
        });
        <?php endif; ?>

        // SweetAlert konfirmasi sebelum kirim aspirasi
        document.getElementById('aspirationForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Cegah kirim langsung

            Swal.fire({
                title: 'Kirim Aspirasi?',
                text: 'Aspirasi akan langsung dikirim dan tidak bisa diubah!',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#5D9CEC',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Kirim!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit(); // Submit form secara manual
                }
            });
        });
        </script>
        </div>
    </main>
</body>

</html>