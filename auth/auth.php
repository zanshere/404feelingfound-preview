<?php
session_start();
include __DIR__ . '/../config/baseURL.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: ' . base_url('auth/login.php'));
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}
?>