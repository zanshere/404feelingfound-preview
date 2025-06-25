<?php
session_start();
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Check if user is logged in, if not redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Get user data
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Prepare different queries based on user role
switch ($userRole) {
    case 'siswa':
        $query = "SELECT 
                    u.id, u.username, u.email, u.nama_lengkap, 
                    u.jenis_kelamin, u.tanggal_lahir, u.alamat, u.deskripsi,
                    s.nis, s.kelas
                  FROM users u
                  LEFT JOIN siswa s ON u.id = s.user_id
                  WHERE u.id = ?";
        break;

    case 'guru':
        $query = "SELECT 
                    u.id, u.username, u.email, u.nama_lengkap, 
                    u.jenis_kelamin, u.tanggal_lahir, u.alamat, u.deskripsi,
                    g.nip, g.jabatan
                  FROM users u
                  LEFT JOIN guru g ON u.id = g.user_id
                  WHERE u.id = ?";
        break;

    case 'ortu':
        $query = "SELECT 
                    u.id, u.username, u.email, u.nama_lengkap, 
                    u.jenis_kelamin, u.tanggal_lahir, u.alamat, u.deskripsi,
                    o.nomor_telepon, o.hubungan
                  FROM users u
                  LEFT JOIN ortu o ON u.id = o.user_id
                  WHERE u.id = ?";
        break;

    default:
        header('Location: ' . base_url('index.php'));
        exit();

}

$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // User not found in database
    session_destroy();
    header('Location: '. base_url('auth/login.php'));
    exit();
}

// Get user's mood entries for the chart
$moodEntries = $pdo->prepare("
    SELECT DATE_FORMAT(date, '%Y-%m-%d') as date, mood_value 
    FROM mood_entries 
    WHERE user_id = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date ASC
");
$moodEntries->execute([$userId]);
$entries = $moodEntries->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for chart
$chartData = [];
foreach ($entries as $entry) {
    $chartData[] = [
        'date' => $entry['date'],
        'mood' => $entry['mood_value']
    ];
}

// Get user's aspirations
$aspirations = $pdo->prepare("
    SELECT * FROM aspirations 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$aspirations->execute([$userId]);
$userAspirations = $aspirations->fetchAll(PDO::FETCH_ASSOC);

// Handle password change
$passwordChanged = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($currentPassword, $user['password'])) {
        if ($newPassword === $confirmPassword) {
            if (strlen($newPassword) >= 8) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $userId]);
                $passwordChanged = true;
            } else {
                $error = "Password baru harus minimal 8 karakter";
            }
        } else {
            $error = "Password baru dan konfirmasi password tidak cocok";
        }
    } else {
        $error = "Password saat ini salah";
    }
}

// Proses update data pribadi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal'])) {
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = $_POST['alamat'];
    $deskripsi = $_POST['deskripsi'];

    $stmt = $pdo->prepare("UPDATE users SET jenis_kelamin = ?, tanggal_lahir = ?, alamat = ?, deskripsi = ? WHERE id = ?");
    $stmt->execute([$jenis_kelamin, $tanggal_lahir, $alamat, $deskripsi, $userId]);

    header("Location: " . base_url('pages/profile.php?updated=true'));
    exit();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - 404FeelingFound</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #4fc3f7;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Navbar */
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 8px;
            color: var(--accent-color);
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--primary-color);
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 0;
            border-radius: var(--border-radius);
            margin: 20px 0;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .profile-header-content {
            display: flex;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-right: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .profile-info h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Profile Content */
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Profile Card */
        .profile-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .profile-card h2 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        /* User Details */
        .user-detail {
            display: flex;
            margin-bottom: 15px;
        }
        
        .user-detail-label {
            font-weight: 600;
            width: 120px;
            color: #666;
        }
        
        .user-detail-value {
            flex: 1;
        }
        
        /* Mood Chart */
        .mood-chart {
            height: 250px;
            margin-top: 20px;
            position: relative;
        }
        
        .mood-day {
            position: absolute;
            bottom: 0;
            width: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .mood-bar {
            width: 100%;
            border-radius: 4px 4px 0 0;
            transition: height 0.5s ease;
        }
        
        .mood-day-label {
            font-size: 0.7rem;
            margin-top: 5px;
            color: #666;
        }
        
        /* Aspiration List */
        .aspiration-item {
            background-color: #f8f9fa;
            border-left: 4px solid var(--accent-color);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }
        
        .aspiration-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Password Form */
        .password-form input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
        }
        
        .password-form button {
            width: 100%;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* edit modal */
        #editModal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    z-index: 9999;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

#editModal .modal-content {
    background-color: white;
    padding: 30px;
    border-radius: var(--border-radius);
    max-width: 500px;
    width: 90%;
    box-shadow: var(--box-shadow);
    position: relative;
    animation: fadeIn 0.3s ease-out;
}

#editModal h2 {
    margin-bottom: 20px;
    color: var(--primary-color);
}

#editModal label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #444;
}

#editModal input,
#editModal textarea,
#editModal select {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: var(--border-radius);
    font-size: 1rem;
}

#editModal button.btn {
    padding: 10px 20px;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-weight: 500;
}

#editModal .btn-primary {
    background-color: var(--primary-color);
    color: white;
}

#editModal .btn-primary:hover {
    background-color: var(--secondary-color);
}

#editModal .btn-cancel {
    background-color: #ccc;
    color: #333;
}

#editModal .close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 20px;
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
}

@keyframes fadeIn {
    from {opacity: 0; transform: scale(0.95);}
    to {opacity: 1; transform: scale(1);}
}


        /* Footer */
        .footer {
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
            border-top: 1px solid #eee;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-container">
                <a href="<?= base_url('index.php') ?>" class="logo">
                    <i class="fas fa-heart"></i> 404FeelingFound
                </a>
                <div class="nav-links">
                    <a href="<?= base_url('index.php') ?>">Beranda</a>
                    <?php if ($userRole === 'siswa'): ?>
                        <a href="<?= base_url('pages/dashboard_siswa.php') ?>">Dashboard</a>
                    <?php elseif ($userRole === 'guru'): ?>
                        <a href="<?= base_url('pages/dashboard_guru.php') ?>">Dashboard</a>
                    <?php elseif ($userRole === 'ortu'): ?>
                        <a href="<?= base_url('pages/dashboard_ortu.php') ?>">Dashboard</a>
                    <?php endif; ?>
                    <div class="user-avatar" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background-color: var(--primary-color); color: white; border-radius: 50%; font-weight: bold;">
                        <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['nama_lengkap']) ?></h1>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                    <span class="role-badge">
                        <?php 
                            switch($userRole) {
                                case 'siswa': echo 'Siswa'; break;
                                case 'guru': echo 'Guru'; break;
                                case 'ortu': echo 'Orang Tua'; break;
                            }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Left Column -->
            <div class="left-column">
                <!-- User Details Card -->
                <div class="profile-card">
                    <h2><i class="fas fa-user-circle"></i> Informasi Profil</h2>
                    <div class="user-detail">
                        <div class="user-detail-label">Nama Lengkap</div>
                        <div class="user-detail-value"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                    </div>
                    <div class="user-detail">
                        <div class="user-detail-label">Email</div>
                        <div class="user-detail-value"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    
                    <?php if ($userRole === 'siswa'): ?>
                        <div class="user-detail">
                            <div class="user-detail-label">NIS</div>
                            <div class="user-detail-value"><?= htmlspecialchars($user['nis'] ?? '-') ?></div>
                        </div>
                        <div class="user-detail">
                            <div class="user-detail-label">Kelas</div>
                            <div class="user-detail-value"><?= htmlspecialchars($user['kelas'] ?? '-') ?></div>
                        </div>
                    <?php elseif ($userRole === 'guru'): ?>
                        <div class="user-detail">
                            <div class="user-detail-label">NIP</div>
                            <div class="user-detail-value"><?= htmlspecialchars($user['nip'] ?? '-') ?></div>
                        </div>
                        <div class="user-detail">
                            <div class="user-detail-label">Jabatan</div>
                            <div class="user-detail-value"><?= htmlspecialchars($user['jabatan'] ?? '-') ?></div>
                        </div>
                    <?php elseif ($userRole === 'ortu'): ?>
                        <div class="user-detail">
                            <div class="user-detail-label">No. Telepon</div>
                            <div class="user-detail-value"><?= htmlspecialchars($user['nomor_telepon'] ?? '-') ?></div>
                        </div>
                        <div class="user-detail">
                            <div class="user-detail-label">Hubungan</div>
                            <div class="user-detail-value"><?= htmlspecialchars($user['hubungan'] ?? '-') ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Change Password Card -->
                <div class="profile-card">
                    <h2><i class="fas fa-lock"></i> Ubah Password</h2>
                    <?php if ($passwordChanged): ?>
                        <div class="alert alert-success">
                            Password berhasil diubah!
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    <form class="password-form" method="POST">
                        <input type="password" name="current_password" placeholder="Password saat ini" required>
                        <input type="password" name="new_password" placeholder="Password baru" required>
                        <input type="password" name="confirm_password" placeholder="Konfirmasi password baru" required>
                        <button type="submit" name="change_password" class="btn btn-primary">Ubah Password</button>
                    </form>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                
              
<!-- Data Pribadi -->
<div class="profile-card">
    <h2><i class="fas fa-id-card"></i> Data Pribadi</h2>

    <div class="user-detail">
        <div class="user-detail-label">Jenis Kelamin</div>
        <div class="user-detail-value"><?= htmlspecialchars($user['jenis_kelamin'] ?? '-') ?></div>
    </div>

    <div class="user-detail">
        <div class="user-detail-label">Tanggal Lahir</div>
        <div class="user-detail-value">
            <?= !empty($user['tanggal_lahir']) ? date('d M Y', strtotime($user['tanggal_lahir'])) : '-' ?>
        </div>
    </div>

    <div class="user-detail">
        <div class="user-detail-label">Alamat</div>
        <div class="user-detail-value"><?= htmlspecialchars($user['alamat'] ?? '-') ?></div>
    </div>

    <div class="user-detail">
        <div class="user-detail-label">Deskripsi</div>
        <div class="user-detail-value"><?= htmlspecialchars($user['deskripsi'] ?? '-') ?></div>
    </div>

    <div style="margin-top: 15px;">
        <button class="btn btn-primary" onclick="openModal()">Edit Data Pribadi</button>
    </div>
</div>


                <!-- Recent Aspirations Card -->
                <div class="profile-card">
                    <h2><i class="fas fa-comment-dots"></i> Aspirasi Terakhir</h2>
                    <?php if (empty($userAspirations)): ?>
                        <p style="text-align: center; color: #666;">Anda belum mengirimkan aspirasi</p>
                    <?php else: ?>
                        <?php foreach ($userAspirations as $aspiration): ?>
                            <div class="aspiration-item">
                                <div class="aspiration-content">
                                    <?= htmlspecialchars($aspiration['content']) ?>
                                </div>
                                <div class="aspiration-meta">
                                    <span>
                                        <?= $aspiration['is_anonymous'] ? 'Anonim' : 'Publik' ?>
                                        <?php if ($aspiration['target'] !== 'public'): ?>
                                            (Untuk: <?= htmlspecialchars($aspiration['target']) ?>)
                                        <?php endif; ?>
                                    </span>
                                    <span><?= date('d M Y', strtotime($aspiration['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2023 404FeelingFound. All rights reserved.</p>
        </div>
    </footer>

  <!-- Modal Edit Data Pribadi -->
<div id="editModal">
  <div class="modal-content">
    <h2>Edit Data Pribadi</h2>
    <form method="POST">
        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin" required>
            <option value="">-- Pilih --</option>
            <option value="Laki-laki" <?= ($user['jenis_kelamin'] ?? '') === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
            <option value="Perempuan" <?= ($user['jenis_kelamin'] ?? '') === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
        </select>

        <label>Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($user['tanggal_lahir'] ?? '') ?>" required>

        <label>Alamat</label>
        <textarea name="alamat" required><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>

        <label>Deskripsi</label>
        <textarea name="deskripsi"><?= htmlspecialchars($user['deskripsi'] ?? '') ?></textarea>

        <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
            <button type="submit" name="update_personal" class="btn btn-primary">Simpan</button>
            <button type="button" onclick="closeModal()" class="btn btn-cancel">Batal</button>
        </div>
    </form>
    <button class="close-btn" onclick="closeModal()">&times;</button>
  </div>
</div>


    <script>
function openModal() {
    document.getElementById("editModal").style.display = "flex";
}
function closeModal() {
    document.getElementById("editModal").style.display = "none";
}

        // Simple animation for mood bars
        document.addEventListener('DOMContentLoaded', function() {
            const moodBars = document.querySelectorAll('.mood-bar');
            moodBars.forEach(bar => {
                const targetHeight = bar.style.height;
                bar.style.height = '0px';
                setTimeout(() => {
                    bar.style.height = targetHeight;
                }, 100);
            });
        });
        
    </script>
</body>
</html>