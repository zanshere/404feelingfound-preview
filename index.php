

<?php
session_start();
require_once __DIR__ . '/config/database.php';
include __DIR__ . '/config/baseURL.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? null;

// PHP logic remains exactly the same as your original
$moodStats = $pdo->query("
    SELECT 
        mood_value,
        COUNT(*) as count,
        (COUNT(*) * 100 / (SELECT COUNT(*) FROM mood_entries WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))) as percentage
    FROM mood_entries
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY mood_value
    ORDER BY mood_value
")->fetchAll(PDO::FETCH_ASSOC);

$moodData = [
    1 => ['emoji' => '😭', 'label' => 'Sangat Buruk', 'color' => '#FF6B6B', 'count' => 0, 'percentage' => 0],
    2 => ['emoji' => '😞', 'label' => 'Buruk', 'color' => '#FFA5A5', 'count' => 0, 'percentage' => 0],
    3 => ['emoji' => '😐', 'label' => 'Biasa', 'color' => '#FFD166', 'count' => 0, 'percentage' => 0],
    4 => ['emoji' => '😊', 'label' => 'Baik', 'color' => '#06D6A0', 'count' => 0, 'percentage' => 0],
    5 => ['emoji' => '😁', 'label' => 'Sangat Baik', 'color' => '#118AB2', 'count' => 0, 'percentage' => 0]
];

foreach ($moodStats as $stat) {
    $moodValue = $stat['mood_value'];
    if (isset($moodData[$moodValue])) {
        $moodData[$moodValue]['count'] = $stat['count'];
        $moodData[$moodValue]['percentage'] = round($stat['percentage'], 1);
    }
}

$totalMoods = $pdo->query("SELECT COUNT(*) as total FROM mood_entries WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

$public_aspirations = $pdo->query("
    SELECT a.*, u.nama_lengkap 
    FROM aspirations a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.target = 'public'
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Only declare the function if it doesn't already exist
if (!function_exists('adjustBrightness')) {
    function adjustBrightness($hex, $steps) {
        $steps = max(-255, min(255, $steps));
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
        }
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));
        $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
        return '#'.$r_hex.$g_hex.$b_hex;
    }
    
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404FeelingFound - Wadah Aspirasi Siswa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', 'Roboto', sans-serif;
    background-color: #f9fbfd;
    color: var(--dark);
    line-height: 1.6;
}

/* Navbar Container */
.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
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

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: var(--transition);
    border: none;
}

.btn-primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 15px rgba(93, 156, 236, 0.3);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(93, 156, 236, 0.4);
}

/* User Menu Styles */
.user-menu {
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.user-avatar:hover {
    transform: scale(1.1);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    padding: 10px 0;
    min-width: 200px;
    z-index: 1000;
    display: none;
    opacity: 0;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.dropdown-menu.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

.dropdown-item {
    padding: 10px 20px;
    color: var(--dark);
    text-decoration: none;
    display: block;
    transition: all 0.3s;
}

.dropdown-item:hover {
    background: #f5f7fa;
    color: var(--primary);
}

.dropdown-divider {
    border-top: 1px solid #eee;
    margin: 5px 0;
}

/* Hero Section */
.hero {
    padding: 100px 0;
    text-align: center;
    background: linear-gradient(135deg, #f5f9ff 0%, #ebf3ff 100%);
    position: relative;
    overflow: hidden;
}

.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 10px;
    background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
}

.hero h2 {
    font-size: 2.8rem;
    color: var(--dark);
    margin-bottom: 15px;
    font-weight: 700;
}

.hero p {
    font-size: 1.2rem;
    color: #7f8c8d;
    max-width: 700px;
    margin: 0 auto;
}

/* Features Section */
.features {
    padding: 80px 0;
    background: white;
}

.features-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 40px;
}

.feature-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    text-align: center;
    border: 1px solid rgba(0, 0, 0, 0.03);
}

.feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.15);
}

.feature-card .emoji {
    font-size: 2.5rem;
    margin-bottom: 20px;
    display: inline-block;
}

.feature-card h3 {
    font-size: 1.4rem;
    margin-bottom: 15px;
    color: var(--dark);
}

.feature-card p {
    color: #7f8c8d;
    font-size: 1rem;
}

/* Mood Stats Section */
.mood-stats {
    padding: 80px 0;
    background: linear-gradient(to bottom, #f9fbfd 0%, #ffffff 100%);
}

.section-title {
    text-align: center;
    margin-bottom: 50px;
    position: relative;
}

.section-title h2 {
    font-size: 2.2rem;
    color: var(--dark);
    display: inline-block;
    position: relative;
}

.section-title h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 4px;
    background: var(--primary);
    border-radius: 2px;
}

.section-title p {
    color: #7f8c8d;
    margin-top: 15px;
    font-size: 1.1rem;
}

.mood-chart-container {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    height: 350px;
    gap: 40px;
    margin: 40px 0;
}

.mood-bar {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100px;
}

.mood-bar-emoji {
    font-size: 3rem;
    margin-bottom: 20px;
    transition: var(--transition);
}

.mood-bar-emoji:hover {
    transform: scale(1.2) rotate(10deg);
}

.mood-bar-fill {
    width: 60px;
    background: linear-gradient(to top, var(--bar-color), var(--bar-color-light));
    border-radius: 8px 8px 0 0;
    position: relative;
    box-shadow: inset 0 -10px 20px rgba(0, 0, 0, 0.05);
    transition: height 1s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.mood-bar-fill::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100%;
    background: linear-gradient(to bottom, rgba(255, 255, 255, 0.3), transparent);
    border-radius: 8px 8px 0 0;
}

.mood-bar-value {
    position: absolute;
    top: -40px;
    left: 0;
    right: 0;
    text-align: center;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    font-size: 1.1rem;
}

.mood-bar-label {
    margin-top: 20px;
    font-size: 1rem;
    text-align: center;
    color: var(--dark);
    font-weight: 600;
}

.mood-bar-count {
    margin-top: 5px;
    font-size: 0.9rem;
    color: #7f8c8d;
}

.mood-total {
    text-align: center;
    margin-top: 40px;
    font-size: 1rem;
    color: var(--dark);
    padding: 15px;
    background: white;
    border-radius: 8px;
    display: inline-block;
    box-shadow: var(--card-shadow);
}

.mood-total strong {
    color: var(--primary);
    font-weight: 700;
}

/* Aspiration Section */
.aspiration-section {
    padding: 80px 0;
    background: white;
}

.aspiration-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin: 40px 0;
}

.aspiration-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    border: 1px solid rgba(0, 0, 0, 0.03);
    position: relative;
    overflow: hidden;
}

.aspiration-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.15);
}

.aspiration-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 5px;
    background: var(--primary);
}

.aspiration-content {
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 20px;
    color: var(--dark);
    font-style: italic;
    position: relative;
    padding-left: 15px;
}

.aspiration-content::before {
    content: '"';
    position: absolute;
    left: -5px;
    top: -10px;
    font-size: 3rem;
    color: var(--gray);
    font-family: serif;
    line-height: 1;
}

.aspiration-meta {
    font-size: 0.9rem;
    color: #7f8c8d;
    border-top: 1px solid var(--gray);
    padding-top: 15px;
    display: flex;
    justify-content: space-between;
}

/* Footer */
.footer {
    padding: 40px 0;
    background: var(--dark);
    color: white;
    text-align: center;
}

.footer p {
    opacity: 0.8;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.8s ease-out forwards;
}

/* Responsive Design */
@media (max-width: 992px) {
    .mood-chart-container {
        gap: 30px;
    }
    
    .mood-bar {
        width: 80px;
    }
    
    .mood-bar-fill {
        width: 50px;
    }
}

@media (max-width: 768px) {
    .hero h2 {
        font-size: 2.2rem;
    }
    
    .hero p {
        font-size: 1rem;
    }
    
    .mood-chart-container {
        height: 300px;
        gap: 20px;
    }
    
    .mood-bar {
        width: 70px;
    }
    
    .mood-bar-fill {
        width: 40px;
    }
    
    .mood-bar-emoji {
        font-size: 2.5rem;
    }
    
    .aspiration-grid {
        grid-template-columns: 1fr;
    }
    
    .nav-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .nav-links {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .user-menu {
        margin-top: 10px;
    }
}

@media (max-width: 576px) {
    .nav-links {
        flex-direction: column;
        gap: 10px;
    }
    
    .user-menu {
        flex-direction: column;
        align-items: center;
    }
    
    .dropdown-menu {
        right: auto;
        left: 50%;
        transform: translateX(-50%) translateY(-10px);
    }
    
    .dropdown-menu.show {
        transform: translateX(-50%) translateY(0);
    }
    
    .hero {
        padding: 80px 0;
    }
    
    .hero h2 {
        font-size: 1.8rem;
    }
    
    .mood-chart-container {
        flex-direction: column;
        height: auto;
        align-items: center;
    }
    
    .mood-bar {
        flex-direction: row;
        width: 100%;
        max-width: 400px;
        margin-bottom: 20px;
        align-items: center;
    }
    
    .mood-bar-emoji {
        margin: 0 20px 0 0;
    }
    
    .mood-bar-fill {
        width: 100%;
        height: 40px !important;
        border-radius: 0 8px 8px 0;
    }
    
    .mood-bar-fill::before {
        border-radius: 0 8px 8px 0;
    }
    
    .mood-bar-value {
        top: 50%;
        left: auto;
        right: 20px;
        transform: translateY(-50%);
    }
    
    .mood-bar-label {
        margin: 0 20px 0 0;
        min-width: 100px;
        text-align: right;
    }
    
    .mood-bar-count {
        position: absolute;
        right: 20px;
        bottom: 10px;
        color: white;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    }
}
    </style>
</head>
<body>
  
  <!-- Premium Navbar -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <div class="nav-container">
                <a href="index.php" class="logo">
                    <i class="fas fa-heart"></i> 404FeelingFound
                </a>
                <div class="nav-links">
                    <a href="#features">Fitur</a>
                    <a href="#mood">Statistik</a>
                    <a href="#aspirations">Aspirasi</a>
                    
                    <?php if ($isLoggedIn): ?>
                        <div class="user-menu">
                            <?php if ($userRole === 'siswa'): ?>
                                <a href="<?= base_url('pages/dashboard_siswa.php') ?>" class="">Dashboard Siswa</a>
                            <?php elseif ($userRole === 'guru'): ?>
                                <a href="<?= base_url('pages/dashboard_guru.php') ?>" class="">Dashboard Guru</a>
                            <?php elseif ($userRole === 'ortu'): ?>
                                <a href="<?= base_url('pages/dashboard_ortu.php') ?>" class="">Dashboard Orang Tua</a>
                            <?php endif; ?>
                            
                            <div class="user-avatar" id="userMenuBtn">
                                <?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?>
                            </div>
                            
                            <div class="dropdown-menu" id="userMenu">
                                <div class="dropdown-item" style="pointer-events: none;">
                                    <small>Masuk sebagai</small>
                                    <div style="font-weight: bold;"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="<?= base_url('pages/profile.php') ?>" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profil Saya
                                </a>
                                <?php if ($userRole === 'siswa'): ?>
                                    <a href="<?= base_url('pages/dashboard_siswa.php') ?>" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                <?php elseif ($userRole === 'guru'): ?>
                                    <a href="<?= base_url('pages/dashboard_guru.php') ?>" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                <?php elseif ($userRole === 'ortu'): ?>
                                    <a href="<?= base_url('pages/dashboard_ortu.php') ?>" class="dropdown-item">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="<?= base_url('auth/logout.php') ?>" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?= base_url('auth/login.php') ?>" class="btn btn-primary">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h2 class="fade-in">Wadah Aspirasi dan Perasaan Siswa</h2>
            <p class="fade-in">Platform untuk mengekspresikan perasaan dan menyampaikan aspirasi secara positif</p>
            
            <?php if ($isLoggedIn): ?>
                <div style="margin-top: 30px;">
                    <?php if ($userRole === 'siswa'): ?>
                        <a href="<?= base_url('pages/dashboard_siswa.php') ?>" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                            <i class="fas fa-tachometer-alt"></i> Buka Dashboard Siswa
                        </a>
                    <?php elseif ($userRole === 'guru'): ?>
                        <a href="<?= base_url('pages/dashboard_guru.php') ?>" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                            <i class="fas fa-tachometer-alt"></i> Buka Dashboard Guru
                        </a>
                    <?php elseif ($userRole === 'ortu'): ?>
                        <a href="<?= base_url('pages/dashboard_ortu.php') ?>" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                            <i class="fas fa-tachometer-alt"></i> Buka Dashboard Orang Tua
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Fitur Kami</h2>
                <p>Berbagai kemudahan untuk mengekspresikan perasaan Anda</p>
            </div>
            <div class="features-container">
                <div class="feature-card fade-in">
                    <div class="emoji">😊</div>
                    <h3>Mood Check</h3>
                    <p>Catat perasaanmu setiap hari dengan emoji sederhana</p>
                </div>
                <div class="feature-card fade-in" style="animation-delay: 0.2s;">
                    <div class="emoji">💬</div>
                    <h3>Aspirasi</h3>
                    <p>Sampaikan pemikiran dan ide-ide kreatifmu</p>
                </div>
                <div class="feature-card fade-in" style="animation-delay: 0.4s;">
                    <div class="emoji">📢</div>
                    <h3>Laporan</h3>
                    <p>Laporkan hal-hal penting secara anonim atau terbuka</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mood Stats Section -->
    <section class="mood-stats" id="mood">
        <div class="container">
            <div class="section-title">
                <h2>Statistik Mood Siswa</h2>
                <p>7 hari terakhir</p>
            </div>
            
            <div class="mood-chart-container">
                <?php foreach ($moodData as $mood): ?>
                <div class="mood-bar fade-in">
                    <div class="mood-bar-emoji"><?= $mood['emoji'] ?></div>
                    <div class="mood-bar-fill" 
                         style="--bar-color: <?= $mood['color'] ?>; --bar-color-light: <?= adjustBrightness($mood['color'], 30) ?>; height: <?= $mood['percentage'] * 3 ?>px;">
                        <div class="mood-bar-value"><?= $mood['percentage'] ?>%</div>
                    </div>
                    <div class="mood-bar-label"><?= $mood['label'] ?></div>
                    <div class="mood-bar-count"><?= $mood['count'] ?> siswa</div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mood-total fade-in">
                Total <strong><?= $totalMoods ?></strong> mood check tercatat dalam seminggu terakhir
            </div>
        </div>
    </section>

    <!-- Aspiration Section -->
    <?php if (!empty($public_aspirations)): ?>
    <section class="aspiration-section" id="aspirations">
        <div class="container">
            <div class="section-title">
                <h2>Aspirasi Publik Terbaru</h2>
                <p>Ungkapan perasaan dan pemikiran dari siswa</p>
            </div>
            <div class="aspiration-grid">
                <?php foreach ($public_aspirations as $aspiration): ?>
                    <div class="aspiration-card fade-in">
                        <div class="aspiration-content">
                            <?= htmlspecialchars($aspiration['content']) ?>
                        </div>
                        <div class="aspiration-meta">
                            <span>
                                <?php if ($aspiration['is_anonymous']): ?>
                                    Anonim
                                <?php else: ?>
                                    <?= htmlspecialchars($aspiration['nama_lengkap']) ?>
                                <?php endif; ?>
                            </span>
                            <span><?= date('d M Y', strtotime($aspiration['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 404FeelingFound | Fianka - Osa- Vita - Zans? . All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Animate bars on load
        document.addEventListener('DOMContentLoaded', function() {
            const bars = document.querySelectorAll('.mood-bar-fill');
            bars.forEach(bar => {
                const targetHeight = bar.style.height;
                bar.style.height = '0px';
                setTimeout(() => {
                    bar.style.height = targetHeight;
                }, 100);
            });
            
            // Emoji hover effect
            const emojis = document.querySelectorAll('.mood-bar-emoji');
            emojis.forEach(emoji => {
                emoji.addEventListener('mouseover', function() {
                    this.style.transform = 'scale(1.3) rotate(10deg)';
                });
                emoji.addEventListener('mouseout', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });

        // Auto-refresh data every 30 seconds
        function refreshMoodData() {
            fetch('<?= base_url('functions/get_mood_stats.php') ?>')
                .then(response => response.json())
                .then(data => {
                    document.querySelectorAll('.mood-bar').forEach((bar, index) => {
                        const moodValue = index + 1;
                        const fill = bar.querySelector('.mood-bar-fill');
                        const value = bar.querySelector('.mood-bar-value');
                        const count = bar.querySelector('.mood-bar-count');
                        
                        fill.style.height = (data[moodValue].percentage * 3) + 'px';
                        value.textContent = data[moodValue].percentage + '%';
                        count.textContent = data[moodValue].count + ' siswa';
                    });
                    
                    document.querySelector('.mood-total strong').textContent = data.total;
                });
        }
        
        setInterval(refreshMoodData, 30000);

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });


document.addEventListener('DOMContentLoaded', function() {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');
    
    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            userMenu.classList.remove('show');
        });
    }
});
</script>
   
</body>
</html>

