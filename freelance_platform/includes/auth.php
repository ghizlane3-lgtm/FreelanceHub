<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /pages/login.php');
        exit;
    }
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function isFreelancer() {
    return ($_SESSION['role'] ?? '') === 'freelancer';
}

function isClient() {
    return ($_SESSION['role'] ?? '') === 'client';
}
