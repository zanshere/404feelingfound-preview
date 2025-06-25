<?php
session_start();
require_once __DIR__ . '/../config/database.php';
include __DIR__ . '/../config/baseURL.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama'] = $user['nama_lengkap'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            header("Location: " . base_url('admin/admin_dashboard.php'));
        } else {
            header("Location: " . base_url('index.php?login=success'));
        }
        exit();
    } else {
        $error = "Username atau password salah";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - 404FeelingFound</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
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

body.login-page {
    background: linear-gradient(135deg, #E6F2FF 0%, #F0F9FF 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    position: relative;
    overflow: hidden;
}

.login-container {
    background: var(--white);
    border-radius: 16px;
    width: 100%;
    max-width: 800px;
    padding: 50px 70px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    text-align: center;
    position: relative;
    overflow: hidden;
    margin: 20px;
    z-index: 1;
}

.login-container::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 10px;
    background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
    z-index: 2;
}

.login-title {
    color: var(--primary);
    font-size: 2.5rem;
    margin-bottom: 15px;
    position: relative;
    display: inline-block;
    font-weight: 700;
}

.login-title::after {
    content: "✨";
    position: absolute;
    right: -40px;
    top: -15px;
    font-size: 1.8rem;
    animation: twinkle 2s ease-in-out infinite alternate;
}

.login-title::before {
    content: "✨";
    position: absolute;
    left: -40px;
    top: -15px;
    font-size: 1.8rem;
    animation: twinkle 2s ease-in-out infinite alternate-reverse;
}

@keyframes twinkle {
    0% { opacity: 0.7; transform: scale(0.9); }
    100% { opacity: 1; transform: scale(1.1); }
}

.login-subtitle {
    color: var(--dark);
    margin-bottom: 40px;
    font-size: 1.1rem;
    line-height: 1.6;
}

.login-form {
    margin-top: 30px;
}

.form-group {
    margin-bottom: 25px;
    text-align: left;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    color: var(--dark);
    font-weight: 600;
    font-size: 1.05rem;
}

.form-group input {
    width: 100%;
    padding: 15px 20px;
    border: 2px solid var(--gray);
    border-radius: 10px;
    font-size: 1.05rem;
    transition: all 0.3s ease;
    background-color: var(--light);
}

.form-group input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 4px rgba(93, 156, 236, 0.2);
    background-color: var(--white);
}

.btn {
    display: inline-block;
    padding: 16px 24px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
}

.btn-primary {
    background-color: var(--primary);
    color: var(--white);
    width: 100%;
    margin-top: 10px;
    position: relative;
    overflow: hidden;
}

.btn-primary:hover {
    background-color: var(--primary-light);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(93, 156, 236, 0.3);
}

.btn-primary:active {
    transform: translateY(-1px);
}

.alert {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-size: 1rem;
    text-align: left;
}

.alert-error {
    background-color: #FDEDEE;
    color: var(--danger);
    border-left: 5px solid var(--danger);
}

.login-footer {
    margin-top: 30px;
    font-size: 1rem;
    color: var(--dark);
    line-height: 1.6;
}

.login-footer a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
    position: relative;
}

.login-footer a:hover {
    color: var(--secondary);
    text-decoration: underline;
}

.login-footer a::after {
    content: "";
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 2px;
    background: var(--accent);
    transform: scaleX(0);
    transition: transform 0.3s;
}

.login-footer a:hover::after {
    transform: scaleX(1);
}

.emoji-float {
    position: absolute;
    font-size: 2rem;
    opacity: 0.08;
    z-index: 0;
    animation-duration: 10s;
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
}

.emoji-float:nth-child(1) {
    top: 15%;
    left: 10%;
    animation-name: float-1;
}

.emoji-float:nth-child(2) {
    top: 75%;
    left: 15%;
    animation-name: float-2;
}

.emoji-float:nth-child(3) {
    top: 35%;
    right: 10%;
    animation-name: float-3;
}

.emoji-float:nth-child(4) {
    top: 85%;
    right: 15%;
    animation-name: float-4;
}

@keyframes float-1 {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    50% { transform: translate(20px, -30px) rotate(15deg); }
}

@keyframes float-2 {
    0%, 100% { transform: translate(0, 0) rotate(5deg); }
    50% { transform: translate(-15px, -25px) rotate(-10deg); }
}

@keyframes float-3 {
    0%, 100% { transform: translate(0, 0) rotate(-5deg); }
    50% { transform: translate(25px, -20px) rotate(20deg); }
}

@keyframes float-4 {
    0%, 100% { transform: translate(0, 0) rotate(10deg); }
    50% { transform: translate(-20px, -15px) rotate(-15deg); }
}

@media (max-width: 900px) {
    .login-container {
        max-width: 700px;
        padding: 40px 50px;
    }
}

@media (max-width: 768px) {
    .login-container {
        max-width: 600px;
        padding: 35px 40px;
    }
    
    .login-title {
        font-size: 2.2rem;
    }
    
    .login-title::after,
    .login-title::before {
        font-size: 1.5rem;
    }
}

@media (max-width: 600px) {
    .login-container {
        max-width: 90%;
        padding: 30px;
        margin: 15px;
    }
    
    .login-title {
        font-size: 2rem;
    }
    
    .login-title::after,
    .login-title::before {
        display: none;
    }
    
    .form-group input {
        padding: 12px 15px;
    }
    
    .btn {
        padding: 14px 20px;
    }
}

@media (max-width: 480px) {
    .login-container {
        padding: 25px 20px;
    }
    
    .login-title {
        font-size: 1.8rem;
    }
    
    .login-subtitle {
        font-size: 1rem;
        margin-bottom: 30px;
    }
}
    </style>
</head>
<body class="login-page">
    <!-- Emoji floating decorations -->
    <div class="emoji-float">😊</div>
    <div class="emoji-float">💬</div>
    <div class="emoji-float">📢</div>
    <div class="emoji-float">🌟</div>
    
    <div class="login-container">
    <h1 class="login-title">404FeelingFound</h1>
    <p class="login-subtitle">Silakan masuk untuk mengakses dashboard Anda</p>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" class="login-form">
        <div class="form-row">  <!-- Baris dengan grid -->
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="remember"> Ingat saya
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary">Masuk ke Sistem</button>
    </form>
    
    <div class="login-footer">
        <p>Lupa password? <a href="#">Reset di sini</a></p>
        <p>Belum punya akun? <a href="#">Hubungi administrator</a></p>
    </div>
</div>