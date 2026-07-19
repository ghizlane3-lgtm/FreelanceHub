<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'freelancer') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$job_id  = (int)($_GET['job_id'] ?? 0);
$error   = '';
$success = '';

if (!$job_id) {
    header('Location: feed.php');
    exit;
}

// Get freelancer_id
$stmt = $pdo->prepare("SELECT id FROM freelancer_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
$freelancer_id = $freelancer['id'] ?? 0;

if (!$freelancer_id) {
    header('Location: profile.php');
    exit;
}

// Get the job
$stmt = $pdo->prepare("
    SELECT jp.*, u.first_name, u.last_name, d.name AS domain_name
    FROM job_proposals jp
    JOIN users u ON jp.client_id = u.id
    LEFT JOIN domains d ON jp.domain_id = d.id
    WHERE jp.id = ? AND jp.status = 'open'
");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: feed.php');
    exit;
}

// Already applied?
$stmt = $pdo->prepare("SELECT id FROM job_applications WHERE job_id = ? AND freelancer_id = ?");
$stmt->execute([$job_id, $freelancer_id]);
$already_applied = $stmt->fetch();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_applied) {
    $cover_letter    = trim($_POST['cover_letter'] ?? '');
    $proposed_rate   = floatval($_POST['proposed_rate'] ?? 0);

    if (empty($cover_letter)) {
        $error = 'Cover letter is required.';
    } elseif ($proposed_rate <= 0) {
        $error = 'Proposed budget must be greater than 0.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO job_applications (job_id, freelancer_id, cover_letter, proposed_rate, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$job_id, $freelancer_id, $cover_letter, $proposed_rate]);
        $success = 'Application sent successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apply — <?= htmlspecialchars($job['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
</head>
<body class="bg-gray-50 min-h-screen">

<nav class="fixed top-0 w-full bg-yellow-400 shadow-md z-40 h-14 flex items-center px-6">
  <a href="feed.php" class="text-yellow-800 font-bold hover:text-yellow-900">
    <i class="fa-solid fa-arrow-left mr-2"></i>Back to Feed
  </a>
  <span class="mx-auto text-xl font-extrabold text-yellow-800">FreelanceHub</span>
</nav>

<div class="pt-20 max-w-2xl mx-auto px-4 pb-10">

  <!-- Job details card -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <?php if ($job['domain_name']): ?>
      <span class="text-xs font-bold text-yellow-700 bg-yellow-100 px-3 py-1 rounded-full">
        <?= htmlspecialchars($job['domain_name']) ?>
      </span>
    <?php endif; ?>
    <h1 class="text-2xl font-extrabold text-gray-800 mt-3 mb-1"><?= htmlspecialchars($job['title']) ?></h1>
    <p class="text-sm text-gray-400 mb-4">
      Posted by <?= htmlspecialchars($job['first_name'].' '.$job['last_name']) ?>
    </p>
    <p class="text-gray-600 leading-relaxed mb-4"><?= nl2br(htmlspecialchars($job['description'])) ?></p>
    <div class="flex flex-wrap gap-4 text-sm border-t border-gray-100 pt-4">
      <span class="text-green-600 font-bold">
        <i class="fa-solid fa-dollar-sign mr-1"></i>
        $<?= number_format($job['budget_min']) ?> – $<?= number_format($job['budget_max']) ?>
        <span class="text-gray-400 font-normal">(<?= $job['budget_type'] ?>)</span>
      </span>
      <span class="text-gray-400">
        <i class="fa-solid fa-signal mr-1"></i><?= ucfirst($job['experience_level']) ?>
      </span>
      <?php if ($job['deadline']): ?>
      <span class="text-gray-400">
        <i class="fa-solid fa-calendar mr-1"></i>Deadline: <?= $job['deadline'] ?>
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Already applied -->
  <?php if ($already_applied): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 p-5 rounded-2xl flex items-center gap-3">
      <i class="fa-solid fa-circle-check text-xl"></i>
      <div>
        <p class="font-bold">Already Applied</p>
        <p class="text-sm">You already submitted an application for this job.</p>
      </div>
    </div>

  <!-- Success -->
  <?php elseif ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 p-5 rounded-2xl flex items-center gap-3">
      <i class="fa-solid fa-circle-check text-xl"></i>
      <div>
        <p class="font-bold">Application Sent!</p>
        <p class="text-sm">The client will review your application soon.</p>
      </div>
    </div>
    <a href="feed.php" class="mt-4 block text-center text-sm text-yellow-600 font-bold hover:underline">
      ← Back to Feed
    </a>

  <!-- Form -->
  <?php else: ?>
    <?php if ($error): ?>
      <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-600 rounded-xl text-sm flex items-center gap-2">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
      <h2 class="text-xl font-extrabold text-gray-800">Submit Your Application</h2>

      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Cover Letter *</label>
        <textarea name="cover_letter" rows="6" required
          placeholder="Explain why you're the best fit for this job. Mention your experience, relevant projects..."
          class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-yellow-400 outline-none resize-none"><?= htmlspecialchars($_POST['cover_letter'] ?? '') ?></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Your Proposed Rate ($) *</label>
        <input type="number" name="proposed_rate" step="0.01" min="1"
          value="<?= htmlspecialchars($_POST['proposed_rate'] ?? '') ?>"
          placeholder="e.g. 500"
          class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
        <p class="text-xs text-gray-400 mt-1">
          Client's budget: $<?= number_format($job['budget_min']) ?> – $<?= number_format($job['budget_max']) ?>
        </p>
      </div>

      <button type="submit"
        class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-extrabold py-3 rounded-xl transition text-sm shadow-md">
        <i class="fa-solid fa-paper-plane mr-2"></i>Apply Now
      </button>
    </form>
  <?php endif; ?>

</div>
</body>
</html>