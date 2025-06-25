<?php
session_start();
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . base_url('auth/login.php'));
    exit();
}

// Get all users with parent information
$users = $pdo->query("
    SELECT u.*, p.nama_lengkap as parent_name 
    FROM users u
    LEFT JOIN users p ON u.parent_id = p.id
    ORDER BY u.role, u.nama_lengkap
")->fetchAll();

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        // Delete related records first
        $pdo->prepare("DELETE FROM mood_entries WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM aspirations WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reports WHERE user_id = ?")->execute([$id]);
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = "Pengguna berhasil dihapus!";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Gagal menghapus pengguna: " . $e->getMessage();
    }
    header("Location: " . base_url('admin/manage_users.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - 404FeelingFound</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #E6F2FF 0%, #F0F9FF 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1200px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #E6E9ED;
        }
        th {
            background-color: #F5F7FA;
            font-weight: 600;
            color: #434A54;
        }
        tr:hover {
            background-color: #F5F7FA;
        }
        .role-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .role-admin { background-color: #E3F2FD; color: #1976D2; }
        .role-guru { background-color: #FFF3E0; color: #F57C00; }
        .role-siswa { background-color: #E8F5E9; color: #388E3C; }
        .role-ortu { background-color: #F3E5F5; color: #8E24AA; }
        .action-btns a {
            color: #5D9CEC;
            margin-right: 10px;
            text-decoration: none;
        }
        .action-btns a:hover {
            text-decoration: underline;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #5D9CEC;
            color: white;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #8BB9F0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-users"></i> Kelola Pengguna</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php elseif (!empty($success)): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <a href="<?= base_url('admin/tambah_user.php') ?>" class="btn"><i class="fas fa-user-plus"></i> Tambah Pengguna</a>
            
            <table>
                <thead>
                    <tr>
                        <th>Nama Lengkap</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Kelas</th>
                        <th>Orang Tua</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <span class="role-badge role-<?= $user['role'] ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['kelas'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['parent_name'] ?? '-') ?></td>
                            <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                            <td class="action-btns">
                                <a href="edit_user.php?id=<?= $user['id'] ?>"><i class="fas fa-edit"></i> Edit</a>
                                <a href="manage_users.php?delete=<?= $user['id'] ?>" onclick="return confirm('Yakin ingin menghapus pengguna ini?')"><i class="fas fa-trash"></i> Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>