<?php
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';
require_once __DIR__ . '/../auth/auth.php';

// Redirect jika bukan ortu
if ($_SESSION['role'] !== 'ortu') {
    header('Location: ' . base_url('auth/login.php') );
    exit();
}

// Ambil data anak
$stmt = $pdo->prepare("SELECT * FROM users WHERE parent_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$children = $stmt->fetchAll();

// Ambil mood, remedial, dan aspirasi
$childMoods = [];
$childRemedials = [];
$aspirations = [];
if (!empty($children)) {
    $child_ids = array_column($children, 'id');
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));

    foreach ($children as $child) {
        // Mood
        $stmt = $pdo->prepare("SELECT * FROM mood_entries WHERE user_id = ? ORDER BY date DESC LIMIT 7");
        $stmt->execute([$child['id']]);
        $childMoods[$child['id']] = $stmt->fetchAll();

        // Remedial
        $stmt = $pdo->prepare("
    SELECT sr.*, ra.subject, ra.description, ra.file_path, ra.deadline, u.nama_lengkap as teacher_name
    FROM student_remedial sr
    JOIN remedial_assignments ra ON sr.assignment_id = ra.id
    JOIN users u ON ra.teacher_id = u.id
    WHERE sr.student_id = ?
    ORDER BY sr.created_at DESC
");
$stmt->execute([$child['id']]);
$childRemedials[$child['id']] = $stmt->fetchAll();

    }

    // Aspirasi
    $stmt = $pdo->prepare("SELECT a.*, u.nama_lengkap FROM aspirations a JOIN users u ON a.user_id = u.id WHERE a.user_id IN ($placeholders) AND a.target LIKE '%ortu%' ORDER BY a.created_at DESC");
    $stmt->execute($child_ids);
    $aspirations = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Orang Tua - 404FeelingFound</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #6EC3F4;
        --secondary: #F0F9FF;
        --accent: #FF9E7D;
        --dark: #2C3E50;
        --light: #FFFFFF;
        --success: #4CAF50;
        --warning: #FFC107;
        --danger: #F44336;
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

    .header {
        background: #fff;
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
        color: #5D9CEC;
        text-decoration: none;
    }

    .logo span {
       color: #5D9CEC;
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
        background-color: #5AB0E0;
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

    .child-card {
        background: var(--light);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .child-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .child-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #eee;
    }

    .child-name {
        font-size: 1.3rem;
        color: var(--primary);
        margin: 0;
        font-weight: 600;
    }

    .section-title {
        color: var(--dark);
        font-size: 1.1rem;
        margin: 1.5rem 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .mood-history, .remedial-history {
        margin-top: 1rem;
    }

    .mood-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.8rem;
        padding: 0.8rem;
        background: rgba(240, 249, 255, 0.7);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .mood-item:hover {
        background: rgba(110, 195, 244, 0.1);
    }

    .mood-emoji {
        font-size: 1.5rem;
        min-width: 40px;
        text-align: center;
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

    th, td {
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
        background-color: rgba(110, 195, 244, 0.05);
    }

    .aspirations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .aspiration-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(110, 195, 244, 0.3);
        transition: all 0.3s ease;
        position: relative;
    }

    .aspiration-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        border-color: var(--primary);
    }

    .aspiration-content {
        margin-bottom: 1rem;
        color: var(--text);
    }

    .aspiration-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        color: var(--text-light);
    }

    .aspiration-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 1rem;
    }

    .mark-read-btn {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }

    .mark-read-btn:hover {
        background: #5AB0E0;
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

        .child-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .aspirations-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <header class="header">
        <div class="container">
            <a href="<?= base_url('index.php') ?>" class="logo">404<span>FeelingFound</span></a>
            <nav class="nav">
                <div class="nav-menu">
                    <a href="<?= base_url('index.php') ?>" class="nav-link"><i class="fas fa-home"></i> Beranda</a>
                    <a href="<?= base_url('about.php') ?>" class="nav-link"><i class="fas fa-info-circle"></i> Tentang</a>
                    <a href="<?= base_url('resources.php') ?>" class="nav-link"><i class="fas fa-book"></i> Sumber Daya</a>
                </div>
                <span class="flex-center" style="color: #5AB0E0;"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['nama']) ?></span>
                <a href="<?= base_url('auth/logout.php') ?>" class="btn btn-primary"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <h2><i class="fas fa-user-shield"></i> Dashboard Orang Tua</h2>
            
            <?php foreach ($children as $child): ?>
                <div class="child-card">
                    <div class="child-header">
                        <h3 class="child-name"><i class="fas fa-child"></i> <?= htmlspecialchars($child['nama_lengkap']) ?> (Kelas <?= htmlspecialchars($child['kelas']) ?>)</h3>
                    </div>
                    
                    <h4 class="section-title"><i class="fas fa-heartbeat"></i> Perkembangan Mood 7 Hari Terakhir</h4>
                    <div class="mood-history">
                        <?php if (!empty($childMoods[$child['id']])): ?>
                            <?php foreach ($childMoods[$child['id']] as $mood): ?>
                                <div class="mood-item">
                                    <span class="mood-emoji"><?= str_repeat('😊', $mood['mood_value']) ?></span>
                                    <div>
                                        <div><?= date('d M Y', strtotime($mood['date'])) ?></div>
                                        <?php if ($mood['notes']): ?>
                                            <div style="color: var(--text-light); font-size: 0.9rem;">"<?= htmlspecialchars($mood['notes']) ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--text-light);">Belum ada data mood.</p>
                        <?php endif; ?>
                    </div>
                    
                    <h4 class="section-title"><i class="fas fa-clipboard-check"></i> Nilai Remedial</h4>
                    <div class="remedial-history">
                        <?php if (!empty($childRemedials[$child['id']])): ?>
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
                                        <?php foreach ($childRemedials[$child['id']] as $remedial): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($remedial['subject']) ?></td>
                                                <td><?= $remedial['score'] ?></td>
                                                <td>
                                                    <span class="badge <?= $remedial['is_completed'] ? 'badge-completed' : 'badge-not-completed' ?>">
                                                        <?= $remedial['is_completed'] ? 'Selesai' : 'Belum Selesai' ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($remedial['teacher_name']) ?></td>
                                                <td><?= date('d M Y', strtotime($remedial['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--text-light);">Belum ada data remedial.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="card">
                <h3><i class="fas fa-comment-dots"></i> Aspirasi dari Anak</h3>
                <?php if (empty($aspirations)): ?>
                    <p style="text-align: center; color: var(--text-light);">Belum ada aspirasi yang dikirimkan.</p>
                <?php else: ?>
                    <div class="aspirations-grid">
                        <?php foreach ($aspirations as $aspiration): ?>
                            <div class="aspiration-card" data-aspiration-id="<?= $aspiration['id'] ?>">
                                <div class="aspiration-content">
                                    <p><?= htmlspecialchars($aspiration['content']) ?></p>
                                </div>
                                <div class="aspiration-meta">
                                    <span><i class="<?= $aspiration['is_anonymous'] ? 'fas fa-user-secret' : 'fas fa-user' ?>"></i> 
                                    <?= $aspiration['is_anonymous'] ? 'Anonim' : htmlspecialchars($aspiration['nama_lengkap']) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= date('d M Y H:i', strtotime($aspiration['created_at'])) ?></span>
                                </div>
                                <div class="aspiration-actions">
                                    <button class="mark-read-btn" onclick="markAsRead(<?= $aspiration['id'] ?>)">
                                        <i class="fas fa-check"></i> Tandai Sudah Dibaca
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    // SweetAlert notifications
    <?php if (isset($_GET['mood_deleted'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Mood Dihapus!',
        text: 'Mood anak sudah dibersihkan 🧼',
        confirmButtonColor: '#6EC3F4'
    });
    <?php endif; ?>

    <?php if (isset($_GET['aspiration_deleted'])): ?>
    Swal.fire({
        icon: 'success',
        title: 'Aspirasi Dihapus',
        text: 'Satu aspirasi telah dihapus 💨',
        confirmButtonColor: '#6EC3F4'
    });
    <?php endif; ?>

    // Function to mark aspiration as read and fade out
    function markAsRead(aspirationId) {
        const aspirationCard = document.querySelector(`.aspiration-card[data-aspiration-id="${aspirationId}"]`);
        
        // Show loading state
        const button = aspirationCard.querySelector('.mark-read-btn');
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        button.disabled = true;
        
        // Simulate API call (in a real app, you would make an AJAX request here)
        setTimeout(() => {
            // Fade out animation
            aspirationCard.style.transition = 'all 0.5s ease';
            aspirationCard.style.opacity = '0';
            aspirationCard.style.transform = 'translateY(20px)';
            
            // Remove the card after animation completes
            setTimeout(() => {
                aspirationCard.remove();
                
                // Show success message if no cards left
                if (document.querySelectorAll('.aspiration-card').length === 0) {
                    document.querySelector('.aspirations-grid').innerHTML = 
                        '<p style="text-align: center; color: var(--text-light); grid-column: 1/-1;">Semua aspirasi telah dibaca.</p>';
                }
            }, 500);
            
            // In a real application, you would also send a request to the server to mark as read
            // fetch(/mark-aspiration-read.php?id=${aspirationId}, { method: 'POST' })
            //   .then(response => response.json())
            //   .then(data => { ... });
            
        }, 800);
    }

    // Auto-dismiss aspirations after 1 hour of being marked as read
    // In a real application, this would be handled server-side
    document.addEventListener('DOMContentLoaded', function() {
        // This is just a simulation - in reality you would check the read status from the server
        setTimeout(() => {
            const cards = document.querySelectorAll('.aspiration-card');
            if (cards.length > 0) {
                const randomCard = cards[Math.floor(Math.random() * cards.length)];
                markAsRead(randomCard.dataset.aspirationId);
            }
        }, 3600000); // 1 hour in milliseconds
    });
    </script>
</body>
</html>