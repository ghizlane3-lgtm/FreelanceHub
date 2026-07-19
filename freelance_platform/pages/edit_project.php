<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'freelancer') {
    header('Location: login.php');
    exit;
}

$user_id    = $_SESSION['user_id'];
$project_id = (int)($_GET['id'] ?? 0);
$error      = '';
$success    = '';

if (!$project_id) {
    header('Location: profile.php');
    exit;
}

// Get freelancer_id
$stmt = $pdo->prepare("SELECT id FROM freelancer_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$fp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fp) {
    header('Location: profile.php');
    exit;
}

$fp_id = $fp['id'];

// Get project — make sure it belongs to this freelancer
$stmt = $pdo->prepare("
    SELECT p.*, d.name as domain_name
    FROM projects p
    LEFT JOIN domains d ON p.domain_id = d.id
    WHERE p.id = ? AND p.freelancer_id = ?
");
$stmt->execute([$project_id, $fp_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: profile.php');
    exit;
}

// All domains for select
$domains = $pdo->query("SELECT id, name FROM domains ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $domain_id   = (int)($_POST['domain_id']  ?? 0) ?: null;
    $project_url = trim($_POST['project_url'] ?? '');

    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        // Cover image upload
        $cover_image = $project['cover_image'];

        if (!empty($_FILES['cover_image']['name'])) {
            $allowed = ['jpg','jpeg','png','webp','gif'];
            $ext     = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Image must be JPG, PNG, WEBP or GIF.';
            } elseif ($_FILES['cover_image']['size'] > 3 * 1024 * 1024) {
                $error = 'Image must be under 3 MB.';
            } else {
                $new_name = 'project_' . $project_id . '_' . time() . '.' . $ext;
                $dest     = __DIR__ . '/../uploads/projects/' . $new_name;

                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dest)) {
                    // Delete old image
                    if ($cover_image && file_exists(__DIR__ . '/../uploads/projects/' . $cover_image)) {
                        unlink(__DIR__ . '/../uploads/projects/' . $cover_image);
                    }
                    $cover_image = $new_name;
                } else {
                    $error = 'Failed to upload image.';
                }
            }
        }

        if (empty($error)) {
            $pdo->prepare("
                UPDATE projects
                SET title = ?, description = ?, domain_id = ?, project_url = ?, cover_image = ?
                WHERE id = ? AND freelancer_id = ?
            ")->execute([$title, $description, $domain_id, $project_url ?: null, $cover_image, $project_id, $fp_id]);

            $success = 'Project updated successfully!';

            // Reload project
            $stmt = $pdo->prepare("SELECT p.*, d.name as domain_name FROM projects p LEFT JOIN domains d ON p.domain_id = d.id WHERE p.id = ?");
            $stmt->execute([$project_id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Project — FreelanceHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">

<!-- NAVBAR -->
<nav class="fixed top-0 w-full bg-yellow-400 shadow-md z-40 h-16 flex items-center px-6 justify-between">
  <a href="profile.php" class="flex items-center gap-2 text-yellow-800 font-bold hover:text-yellow-900">
    <i class="fa-solid fa-arrow-left"></i> Back to Profile
  </a>
  <span class="text-xl font-extrabold text-yellow-800">Edit Project</span>
  <a href="logout.php" class="text-sm text-yellow-800 font-semibold hover:underline">Logout</a>
</nav>

<div class="pt-20 pb-12 px-4 max-w-2xl mx-auto">

  <!-- Alerts -->
  <?php if ($success): ?>
    <div class="mb-5 flex items-center gap-3 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-semibold">
      <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
      <a href="profile.php" class="ml-auto text-green-600 hover:underline text-xs">← Back to profile</a>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="mb-5 flex items-center gap-3 p-4 bg-red-50 border border-red-200 text-red-600 rounded-xl text-sm">
      <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-5">

    <!-- Cover image preview -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="h-48 bg-yellow-50 flex items-center justify-center overflow-hidden relative" id="cover-preview-box">
        <?php if ($project['cover_image']): ?>
          <img id="cover-preview"
               src="../uploads/projects/<?= htmlspecialchars($project['cover_image']) ?>"
               class="w-full h-full object-cover">
        <?php else: ?>
          <div id="cover-placeholder" class="flex flex-col items-center text-yellow-200">
            <i class="fa-solid fa-image text-5xl mb-2"></i>
            <span class="text-sm">No cover image</span>
          </div>
          <img id="cover-preview" src="" class="w-full h-full object-cover hidden">
        <?php endif; ?>
        <!-- Overlay on hover -->
        <label for="cover-input"
               class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 hover:opacity-100 transition cursor-pointer">
          <span class="text-white font-bold text-sm bg-black/50 px-4 py-2 rounded-full">
            <i class="fa-solid fa-camera mr-2"></i>Change Photo
          </span>
        </label>
      </div>
      <input type="file" id="cover-input" name="cover_image" accept="image/*"
             class="hidden" onchange="previewCover(this)">
      <div class="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">
        Click on the image to change · Max 3MB · JPG, PNG, WEBP
      </div>
    </div>

    <!-- Fields -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
      <h2 class="font-extrabold text-gray-700 flex items-center gap-2">
        <i class="fa-solid fa-folder-open text-yellow-500"></i> Project Details
      </h2>

      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Title *</label>
        <input type="text" name="title" required
               value="<?= htmlspecialchars($project['title']) ?>"
               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
        <textarea name="description" rows="4"
                  placeholder="Describe what you built, the technologies used, the challenge solved..."
                  class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none resize-none"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Domain</label>
          <select name="domain_id"
                  class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none bg-white">
            <option value="">No domain</option>
            <?php foreach ($domains as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $project['domain_id'] == $d['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Live URL</label>
          <input type="url" name="project_url"
                 value="<?= htmlspecialchars($project['project_url'] ?? '') ?>"
                 placeholder="https://..."
                 class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
        </div>
      </div>
    </div>

    <!-- Buttons -->
    <div class="flex gap-3">
      <button type="submit"
              class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white font-extrabold py-3 rounded-xl transition shadow-md text-sm">
        <i class="fa-solid fa-floppy-disk mr-2"></i>Save Changes
      </button>
      <a href="profile.php"
         class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold rounded-xl transition text-sm">
        Cancel
      </a>
    </div>

  </form>

  <!-- Delete zone -->
  <div class="mt-8 border border-red-100 rounded-2xl p-5 bg-red-50">
    <h3 class="text-sm font-extrabold text-red-600 mb-1">Danger Zone</h3>
    <p class="text-xs text-red-400 mb-3">This will permanently delete this project and its cover image.</p>
    <a href="delete_project.php?id=<?= $project_id ?>"
       onclick="return confirm('Are you sure you want to delete this project? This cannot be undone.')"
       class="inline-flex items-center gap-2 px-5 py-2 bg-red-600 text-white text-sm font-bold rounded-xl hover:bg-red-700 transition">
      <i class="fa-solid fa-trash"></i> Delete Project
    </a>
  </div>

</div>

<script>
function previewCover(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const preview     = document.getElementById('cover-preview');
      const placeholder = document.getElementById('cover-placeholder');
      preview.src = e.target.result;
      preview.classList.remove('hidden');
      if (placeholder) placeholder.classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

</body>
</html>