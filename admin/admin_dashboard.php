<?php
session_start();
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY role, nama_lengkap")->fetchAll();

// Get statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_siswa' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn(),
    'total_guru' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn(),
    'total_ortu' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'ortu'")->fetchColumn(),
    'total_moods' => $pdo->query("SELECT COUNT(*) FROM mood_entries")->fetchColumn(),
    'total_aspirations' => $pdo->query("SELECT COUNT(*) FROM aspirations")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - 404FeelingFound</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary: #5D9CEC;
        --primary-light: #8BB9F0;
        --secondary: #A0D468;
        --accent: #FFCE54;
        --danger: #ED5565;
        --light: #F5F7FA;
        --dark: #434A54;
        --gray: #E6E9ED;
        --white: #FFFFFF;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        background-color: #f5f7fa;
    }

    .welcome-section {
        margin-bottom: 30px;
    }
    
    .welcome-card {
        background-color: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .welcome-card h2 {
        color: var(--primary);
        margin-top: 0;
    }
    
    .welcome-card p {
        color: var(--dark);
        line-height: 1.6;
    }
    
    .feature-highlights {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    
    .feature {
        text-align: center;
        padding: 20px 15px;
        border-radius: 8px;
        background-color: rgba(93, 156, 236, 0.05);
        transition: transform 0.3s;
    }
    
    .feature:hover {
        transform: translateY(-5px);
        background-color: rgba(93, 156, 236, 0.1);
    }
    
    .feature i {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 15px;
    }
    
    .feature h3 {
        margin: 10px 0;
        color: var(--dark);
    }
    
    .feature p {
        font-size: 0.9rem;
        color: var(--dark);
        opacity: 0.8;
        margin: 0;
    }
    
    @media (max-width: 768px) {
        .feature-highlights {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .feature-highlights {
            grid-template-columns: 1fr;
        }
    }

    .admin-container {
        display: flex;
        min-height: 100vh;
    }

    /* Sidebar */
    .admin-sidebar {
        width: 250px;
        background: linear-gradient(180deg, var(--primary), #4a6fa5);
        color: white;
        padding: 20px 0;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        position: fixed;
        height: 100%;
    }

    .admin-brand {
        text-align: center;
        padding: 0 20px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 20px;
    }

    .admin-brand h2 {
        margin: 0;
        font-size: 1.3rem;
    }

    .admin-brand p {
        margin: 5px 0 0;
        font-size: 0.8rem;
        opacity: 0.8;
    }

    .admin-menu {
        padding: 0 15px;
    }

    .admin-menu h3 {
        font-size: 0.9rem;
        text-transform: uppercase;
        margin: 20px 0 10px;
        opacity: 0.7;
        padding-left: 10px;
    }

    .admin-menu a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        border-radius: 5px;
        margin-bottom: 5px;
        transition: all 0.3s;
    }

    .admin-menu a:hover,
    .admin-menu a.active {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .admin-menu a i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }

    /* Main Content */
    .admin-main {
        flex: 1;
        margin-left: 250px;
        padding: 20px;
    }

    .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--gray);
    }

    .admin-header h1 {
        margin: 0;
        color: var(--dark);
    }

    .admin-user {
        display: flex;
        align-items: center;
    }

    .admin-user-avatar {
        width: 40px;
        height: 40px;
        background-color: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        font-weight: bold;
    }

    .admin-user-name {
        font-weight: 500;
    }

    .admin-user-role {
        font-size: 0.8rem;
        opacity: 0.7;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background-color: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .stat-card-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-card-users {
        background-color: #E3F2FD;
        color: #1976D2;
    }

    .stat-card-siswa {
        background-color: #E8F5E9;
        color: #388E3C;
    }

    .stat-card-guru {
        background-color: #FFF3E0;
        color: #F57C00;
    }

    .stat-card-ortu {
        background-color: #F3E5F5;
        color: #8E24AA;
    }

    .stat-card-moods {
        background-color: #E0F7FA;
        color: #00ACC1;
    }

    .stat-card-aspirations {
        background-color: #E8EAF6;
        color: #3949AB;
    }

    .stat-card-title {
        font-size: 0.9rem;
        color: var(--dark);
        opacity: 0.8;
    }

    .stat-card-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 5px 0;
    }

    .stat-card-change {
        font-size: 0.8rem;
        color: var(--secondary);
    }

    /* Users Table */
    .users-table {
        width: 100%;
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .users-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .users-table th,
    .users-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid var(--gray);
    }

    .users-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: var(--dark);
    }

    .users-table tr:hover {
        background-color: #f8f9fa;
    }

    .user-role {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .role-siswa {
        background-color: #E8F5E9;
        color: #388E3C;
    }

    .role-guru {
        background-color: #FFF3E0;
        color: #F57C00;
    }

    .role-ortu {
        background-color: #F3E5F5;
        color: #8E24AA;
    }

    .role-admin {
        background-color: #E3F2FD;
        color: #1976D2;
    }

    .user-actions a {
        color: var(--primary);
        margin-right: 10px;
        text-decoration: none;
    }

    .user-actions a:hover {
        text-decoration: underline;
    }

    .btn {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        font-size: 0.9rem;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background-color: var(--primary-light);
    }

    .btn-add {
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .admin-sidebar {
            width: 200px;
        }

        .admin-main {
            margin-left: 200px;
        }

        .stats-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }

    @media (max-width: 576px) {
        .admin-sidebar {
            width: 60px;
            overflow: hidden;
        }

        .admin-brand h2,
        .admin-menu h3,
        .admin-menu a span {
            display: none;
        }

        .admin-main {
            margin-left: 60px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="admin-brand">
                <h2>404FeelingFound</h2>
                <p>Admin Panel</p>
            </div>

            <div class="admin-menu">
                <h3>Menu Utama</h3>
                <a href="<?= base_url('admin/admin_dashboard.php') ?>" class="active"><i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span></a>
                <a href="<?= base_url('admin/manage_users.php') ?>"><i class="fas fa-users"></i> <span>Kelola Pengguna</span></a>
                <a href="<?= base_url('admin/kelola_mood.php') ?>"><i class="fas fa-smile"></i> <span>Kelola Mood</span></a>
                <a href="<?= base_url('admin/kelola_aspirasi.php') ?>"><i class="fas fa-comments"></i> <span>Kelola Aspirasi</span></a>

                <h3>Administrasi</h3>
                <a href="<?= base_url('admin/tambah_user.php') ?>"><i class="fas fa-user-plus"></i> <span>Tambah Pengguna</span></a>
                <h3>Aksi</h3>
                <a href="<?= base_url('auth/logout.php') ?>"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-main">
            <div class="admin-header">
                <h1>Dashboard Admin</h1>
                <div class="admin-user">
                    <div class="admin-user-avatar">
                        <?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="admin-user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                        <div class="admin-user-role">Admin</div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-title">Total Pengguna</div>
                            <div class="stat-card-value"><?= $stats['total_users'] ?></div>
                        </div>
                        <div class="stat-card-icon stat-card-users">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-title">Total Siswa</div>
                            <div class="stat-card-value"><?= $stats['total_siswa'] ?></div>
                        </div>
                        <div class="stat-card-icon stat-card-siswa">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-title">Total Guru</div>
                            <div class="stat-card-value"><?= $stats['total_guru'] ?></div>
                        </div>
                        <div class="stat-card-icon stat-card-guru">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-title">Total Orang Tua</div>
                            <div class="stat-card-value"><?= $stats['total_ortu'] ?></div>
                        </div>
                        <div class="stat-card-icon stat-card-ortu">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-title">Total Mood</div>
                            <div class="stat-card-value"><?= $stats['total_moods'] ?></div>
                        </div>
                        <div class="stat-card-icon stat-card-moods">
                            <i class="fas fa-smile"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-title">Total Aspirasi</div>
                            <div class="stat-card-value"><?= $stats['total_aspirations'] ?></div>
                        </div>
                        <div class="stat-card-icon stat-card-aspirations">
                            <i class="fas fa-comment"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- improve disini, karena ini ruang kosong jadi tambahkan saja seperti nama web lalu fitur yang ada pada halaman ini -->
            <div class="welcome-section">
                <div class="welcome-card">
                    <h2>Selamat Datang di 404FeelingFound</h2>
                    <p>Sistem pendukung kesehatan mental untuk komunitas sekolah. Dashboard ini memberikan Anda akses
                        penuh untuk mengelola seluruh aspek platform.</p>

                    <div class="feature-highlights">
                        <div class="feature">
                            <i class="fas fa-tachometer-alt"></i>
                            <h3>Dashboard Utama</h3>
                            <p>Tinjau statistik dan aktivitas terkini dalam satu tampilan.</p>
                        </div>
                        <div class="feature">
                            <i class="fas fa-users"></i>
                            <h3>Kelola Pengguna</h3>
                            <p>Kelola akun siswa, guru, orang tua, dan administrator.</p>
                        </div>
                        <div class="feature">
                            <i class="fas fa-smile"></i>
                            <h3>Pantau Mood</h3>
                            <p>Lacak kondisi emosional anggota komunitas sekolah.</p>
                        </div>
                        <div class="feature">
                            <i class="fas fa-comments"></i>
                            <h3>Kelola Aspirasi</h3>
                            <p>Tanggapi masukan dan aspirasi dari pengguna platform.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>