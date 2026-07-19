<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// ✅ NOUVEAU : Récupérer d'abord l'id réel du profil de freelance associé à l'utilisateur connecté
$stmt_profile = $pdo->prepare("SELECT id FROM freelancer_profiles WHERE user_id = ?");
$stmt_profile->execute([$user_id]);
$profile = $stmt_profile->fetch(PDO::FETCH_ASSOC);

// Sécurité supplémentaire : Si l'utilisateur n'a pas de profil freelance (ex: c'est un client), on lui refuse l'accès
if (!$profile) {
    header('Location: profile.php?error=not_a_freelancer');
    exit;
}

$freelancer_id = $profile['id']; // C'est cette valeur qu'on va utiliser dans le INSERT !

// Récupérer domains pour le select
$stmt_domains = $pdo->query("SELECT id, name FROM domains ORDER BY name");
$domains = $stmt_domains->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $domain_id = $_POST['domain_id'] ?: null;
    $progress = $_POST['progress'];
    $project_url = trim($_POST['project_url']) ?: null;
    $cover_image = null;

    // Upload photo
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
        $upload_dir = '../uploads/projects/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array(strtolower($ext), $allowed)) {
            $filename = uniqid('proj_') . '.' . $ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $filename)) {
                $cover_image = $filename;
            } else {
                $error = 'Upload failed';
            }
        } else {
            $error = 'Only JPG, PNG, WEBP allowed';
        }
    }

    if (empty($title) || empty($description)) {
        $error = 'Title and description are required';
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("
            INSERT INTO projects (freelancer_id, title, description, cover_image, project_url, domain_id, progress) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        
        $stmt->execute([$freelancer_id, $title, $description, $cover_image, $project_url, $domain_id, $progress]);
        $success = 'Project added successfully!';
        
        // Reset form
        $title = $description = $project_url = '';
        $domain_id = $progress = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Add New Project</h1>
        <a href="profile.php" class="text-gray-600 hover:text-gray-800">
            <i class="fa-solid fa-arrow-left mr-1"></i> Back to Profile
        </a>
    </div>

    <?php if($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4"><?= $success ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow p-6 space-y-4">
        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">Project Title *</label>
            <input type="text" name="title" value="<?= htmlspecialchars($title ?? '') ?>" required
                class="w-full p-3 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">Description *</label>
            <textarea name="description" rows="4" required
                class="w-full p-3 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"><?= htmlspecialchars($description ?? '') ?></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-2">Domain</label>
                <select name="domain_id" class="w-full p-3 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    <option value="">Select domain</option>
                    <?php foreach($domains as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-2">Status</label>
                <select name="progress" class="w-full p-3 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                    <option value="completed">Completed</option>
                    <option value="in_progress">In Progress</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">Project URL</label>
            <input type="url" name="project_url" value="<?= htmlspecialchars($project_url ?? '') ?>" placeholder="https://github.com/..."
                class="w-full p-3 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-600 mb-2">Cover Image</label>
            <input type="file" name="cover_image" accept="image/*"
                class="w-full p-3 border-gray-300 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100">
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP max 2MB</p>
        </div>

        <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 rounded-lg transition">
            Add Project
        </button>
    </form>
</div>
</body>
</html>