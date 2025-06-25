<?php
session_start();
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Get user data to edit
$user = null;
$siswaList = [];
$error = '';
$success = '';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $error = "Pengguna tidak ditemukan!";
    } else {
        // Get student list for parent selection
        $siswaList = $pdo->query("SELECT id, nama_lengkap, kelas FROM users WHERE role = 'siswa' ORDER BY nama_lengkap")->fetchAll();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = $_POST['id'];
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $role = $_POST['role'];
    $kelas = $_POST['kelas'] ?? null;
    $parent_id = $_POST['parent_id'] ?? null;
    
    // Handle empty values properly
    if ($role !== 'siswa') {
        $kelas = null;
    }
    if ($role !== 'ortu') {
        $parent_id = null;
    }
    
    // Convert empty strings to null for integer fields
    if ($parent_id === '' || $parent_id === '0') {
        $parent_id = null;
    }
    if ($kelas === '') {
        $kelas = null;
    }
    
    // Validate
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    
    if ($stmt->rowCount() > 0) {
        $error = "Username '$username' sudah digunakan oleh pengguna lain.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $updateData = [
                'username' => $username,
                'nama_lengkap' => $nama_lengkap,
                'role' => $role,
                'kelas' => $kelas,
                'parent_id' => $parent_id,
                'id' => $id
            ];
            
            // Update user
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = :username, 
                    nama_lengkap = :nama_lengkap, 
                    role = :role, 
                    kelas = :kelas, 
                    parent_id = :parent_id 
                WHERE id = :id
            ");
            $stmt->execute($updateData);
            
            // Update password if provided
            if (!empty($_POST['password'])) {
                $hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $id]);
            }
            
            $pdo->commit();
            $success = "Data pengguna berhasil diperbarui!";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna - 404FeelingFound</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #E6F2FF 0%, #F0F9FF 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #5D9CEC;
            margin-bottom: 20px;
            font-weight: 700;
            text-align: center;
        }
        .alert-error {
            background-color: #FDEDEE;
            color: #ED5565;
            border-left: 5px solid #ED5565;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 10px;
        }
        .alert-success {
            background-color: #E7F9EF;
            color: #3C763D;
            border-left: 5px solid #3C763D;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #434A54;
        }
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E6E9ED;
            border-radius: 10px;
            font-size: 1rem;
            background-color: #F5F7FA;
            transition: border-color 0.3s ease;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #5D9CEC;
            background-color: #fff;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #5D9CEC;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 1rem;
        }
        .btn:hover {
            background-color: #8BB9F0;
        }
        .btn-back {
            background-color: #E6E9ED;
            color: #434A54;
            margin-right: 15px;
        }
        .btn-back:hover {
            background-color: #d1d5db;
        }
        .hidden {
            display: none;
        }
        .role-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .role-admin { background-color: #E3F2FD; color: #1976D2; }
        .role-guru { background-color: #FFF3E0; color: #F57C00; }
        .role-siswa { background-color: #E8F5E9; color: #388E3C; }
        .role-ortu { background-color: #F3E5F5; color: #8E24AA; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-user-edit"></i> Edit Pengguna</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php elseif (!empty($success)): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($user): ?>
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="update_user" value="1">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="guru" <?= $user['role'] === 'guru' ? 'selected' : '' ?>>Guru</option>
                            <option value="siswa" <?= $user['role'] === 'siswa' ? 'selected' : '' ?>>Siswa</option>
                            <option value="ortu" <?= $user['role'] === 'ortu' ? 'selected' : '' ?>>Orang Tua</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password (Kosongkan jika tidak ingin mengubah)</label>
                        <input type="password" id="password" name="password">
                    </div>
                    
                    <div class="form-group" id="kelas-group" <?= $user['role'] !== 'siswa' ? 'style="display:none;"' : '' ?>>
                        <label for="kelas">Kelas</label>
                        <select id="kelas" name="kelas">
                            <option value="">-- Pilih Kelas --</option>
                            <option value="X RPL 1" <?= $user['kelas'] === 'X RPL 1' ? 'selected' : '' ?>>X RPL 1</option>
                            <option value="X RPL 2" <?= $user['kelas'] === 'X RPL 2' ? 'selected' : '' ?>>X RPL 2</option>
                            <option value="X DKV 1" <?= $user['kelas'] === 'X DKV 1' ? 'selected' : '' ?>>X DKV 1</option>
                            <option value="X DKV 2" <?= $user['kelas'] === 'X DKV 2' ? 'selected' : '' ?>>X DKV 2</option>
                            <option value="X TKJ 1" <?= $user['kelas'] === 'X TKJ 1' ? 'selected' : '' ?>>X TKJ 1</option>
                            <option value="X TKJ 2" <?= $user['kelas'] === 'X TKJ 2' ? 'selected' : '' ?>>X TKJ 2</option>
                            <option value="XI TKJ" <?= $user['kelas'] === 'XI TKJ' ? 'selected' : '' ?>>XI TKJ</option>
                            <option value="XI DKV 1" <?= $user['kelas'] === 'XI DKV 1' ? 'selected' : '' ?>>XI DKV 1</option>
                            <option value="XI DKV 2" <?= $user['kelas'] === 'XI DKV 2' ? 'selected' : '' ?>>XI DKV 2</option>
                            <option value="XI DKV 3" <?= $user['kelas'] === 'XI DKV 3' ? 'selected' : '' ?>>XI DKV 3</option>
                            <option value="XI RPL 1" <?= $user['kelas'] === 'XI RPL 1' ? 'selected' : '' ?>>XI RPL 1</option>
                            <option value="XI RPL 2" <?= $user['kelas'] === 'XI RPL 2' ? 'selected' : '' ?>>XI RPL 2</option>
                            <option value="XII TKJ 1" <?= $user['kelas'] === 'XII TKJ 1' ? 'selected' : '' ?>>XII TKJ 1</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="parent-id-group" <?= $user['role'] !== 'ortu' ? 'style="display:none;"' : '' ?>>
                        <label for="parent_id">Anak (Siswa)</label>
                        <select id="parent_id" name="parent_id">
                            <option value="">-- Pilih Anak (Siswa) --</option>
                            <?php foreach ($siswaList as $siswa): ?>
                                <option value="<?= $siswa['id'] ?>" <?= $user['parent_id'] == $siswa['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($siswa['nama_lengkap']) ?> (<?= htmlspecialchars($siswa['kelas']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <a href="<?= base_url('admin/manage_users.php') ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            <?php else: ?>
                <p>Pengguna tidak ditemukan.</p>
                <a href="<?= base_url('admin/manage_users.php') ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Pengguna</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const roleSelect = document.getElementById('role');
        const kelasGroup = document.getElementById('kelas-group');
        const parentIdGroup = document.getElementById('parent-id-group');

        function toggleFields() {
            const role = roleSelect.value;
            kelasGroup.style.display = role === 'siswa' ? 'block' : 'none';
            parentIdGroup.style.display = role === 'ortu' ? 'block' : 'none';
            
            // Reset values when hiding
            if (role !== 'siswa') {
                document.getElementById('kelas').value = '';
            }
            if (role !== 'ortu') {
                document.getElementById('parent_id').value = '';
            }
        }

        // Initial setup
        toggleFields();
        
        // Add event listener
        roleSelect.addEventListener('change', toggleFields);
    </script>
</body>
</html>