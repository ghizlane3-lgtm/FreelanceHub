<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$app_id  = (int)($_POST['app_id'] ?? 0);
$action  = $_POST['action'] ?? '';

if (!$app_id || !in_array($action, ['accept', 'reject'])) {
    header('Location: profile.php');
    exit;
}

// Vérifier que cette application appartient bien à un job du client connecté
$stmt = $pdo->prepare("
    SELECT ja.id, ja.job_id, ja.freelancer_id, ja.status
    FROM job_applications ja
    JOIN job_proposals jp ON ja.job_id = jp.id
    WHERE ja.id = ? AND jp.client_id = ?
");
$stmt->execute([$app_id, $user_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    header('Location: profile.php');
    exit;
}

if ($app['status'] !== 'pending') {
    header('Location: profile.php?error=already_handled');
    exit;
}

$new_status = $action === 'accept' ? 'accepted' : 'rejected';

// Update application status
$pdo->prepare("UPDATE job_applications SET status = ? WHERE id = ?")
    ->execute([$new_status, $app_id]);

// If accepted → mark job as in_progress + reject all other applicants
if ($action === 'accept') {
    $pdo->prepare("UPDATE job_proposals SET status = 'in_progress' WHERE id = ?")
        ->execute([$app['job_id']]);

    $pdo->prepare("
        UPDATE job_applications SET status = 'rejected'
        WHERE job_id = ? AND id != ? AND status = 'pending'
    ")->execute([$app['job_id'], $app_id]);

    // Increment jobs_completed for the freelancer
    $pdo->prepare("
        UPDATE freelancer_profiles SET jobs_completed = jobs_completed + 1
        WHERE id = ?
    ")->execute([$app['freelancer_id']]);
}

header('Location: profile.php?success=1');
exit;