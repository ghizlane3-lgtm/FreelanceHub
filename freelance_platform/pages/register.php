<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// Already logged in → go to feed
if (isLoggedIn()) {
    header('Location: feed.php');
    exit;
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'email'      => trim($_POST['email']       ?? ''),
        'role'       => $_POST['role']             ?? 'freelancer',
    ];

    $password = $_POST['password'] ?? '';

    // --- Validation ---
    if (empty($old['first_name'])) $errors['first_name'] = 'First name is required.';
    if (empty($old['last_name'])) $errors['last_name']  = 'Last name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
    if (strlen($password) < 6) $errors['password']  = 'Password must be at least 6 characters.';
    if (!in_array($old['role'], ['freelancer','client'])) $errors['role'] = 'Invalid role.';

    // Check email already taken
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) $errors['email'] = 'This email is already registered.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$old['first_name'], $old['last_name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['role']]);
            
            $userId = (int)$pdo->lastInsertId();

            if ($old['role'] === 'freelancer') {
                $pdo->prepare('INSERT INTO freelancer_profiles (user_id) VALUES (?)')->execute([$userId]);
            }

            $_SESSION['user_id']   = $userId;
            $_SESSION['role']      = $old['role'];
            $_SESSION['full_name'] = $old['first_name'] . ' ' . $old['last_name'];

            $pdo->commit();
            header('Location: feed.php');
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Registration failed: ' . $e->getMessage();
            $msg = urlencode(implode(' | ', $errors));
            header("Location: ../home.php?show_modal=1&error={$msg}");
            exit;
        }
    }
}

// If we have errors → back to homepage with modal open
if (!empty($errors)) {
    $msg = urlencode(implode(' | ', $errors));
    header("Location: ../home.php?show_modal=1&error={$msg}");
    exit;
}

// GET request with no errors = just redirect home
header('Location: ../home.php');
exit;