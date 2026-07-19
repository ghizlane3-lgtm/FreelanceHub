<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

$stmt_domains = $pdo->query("SELECT id, name FROM domains ORDER BY name");
$domains = $stmt_domains->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $domain_id = $_POST['domain_id'] ?: null;
    $budget_min = floatval($_POST['budget_min']);
    $budget_max = floatval($_POST['budget_max']);
    $budget_type = $_POST['budget_type'];
    $deadline = $_POST['deadline'] ?: null;

    if (empty($title) || empty($description) || $budget_min <= 0) {
        $error = 'Title, description and budget are required';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO job_proposals (client_id, title, description, domain_id, budget_min, budget_max, budget_type, deadline, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')
        ");
        $stmt->execute([$user_id, $title, $description, $domain_id, $budget_min, $budget_max, $budget_type, $deadline]);
        $success = 'Job posted successfully!';
        $title = $description = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Job</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<div class="max-w-2xl mx-auto py-10 px-4">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Post a New Job</h1>
    
    <?php if($success): ?><div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4"><?= $success ?></div><?php endif; ?>
    <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4"><?= $error ?></div><?php endif; ?>

    <form method="POST" class="bg-white rounded-xl shadow p-6 space-y-4">
        <input type="text" name="title" placeholder="Job Title *" required class="w-full p-3 border rounded-lg">
        <textarea name="description" rows="4" placeholder="Description *" required class="w-full p-3 border rounded-lg"></textarea>
        
        <select name="domain_id" class="w-full p-3 border rounded-lg">
            <option value="">Select domain</option>
            <?php foreach($domains as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <div class="grid grid-cols-3 gap-4">
            <input type="number" step="0.01" name="budget_min" placeholder="Min Budget *" required class="p-3 border rounded-lg">
            <input type="number" step="0.01" name="budget_max" placeholder="Max Budget" class="p-3 border rounded-lg">
            <select name="budget_type" class="p-3 border rounded-lg">
                <option value="fixed">Fixed</option>
                <option value="hourly">Hourly</option>
            </select>
        </div>
        
        <input type="date" name="deadline" class="w-full p-3 border rounded-lg">
        
        <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 rounded-lg">
            Post Job
        </button>
    </form>
</div>
</body>
</html>