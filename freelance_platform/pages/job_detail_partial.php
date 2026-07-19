<?php

// Démarre la session si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if(!isset($_SESSION['user_id'])) {
    http_response_code(403); exit;
}

$user_id      = $_SESSION['user_id'];
$job_id       = (int)($_GET['id'] ?? 0);

if(!$job_id) { echo '<p class="text-red-500">Job not found.</p>'; exit; }

// Fetch job
$s = $pdo->prepare("
    SELECT jp.*, u.first_name, u.last_name, u.avatar, d.name as domain_name
    FROM job_proposals jp
    JOIN users u ON jp.client_id = u.id
    LEFT JOIN domains d ON jp.domain_id = d.id
    WHERE jp.id = ?
");
$s->execute([$job_id]);
$job = $s->fetch(PDO::FETCH_ASSOC);

if(!$job) { echo '<p class="text-red-500">Job not found.</p>'; exit; }

// Check already applied
$already_applied = false;
if($_SESSION['role'] === 'freelancer') {
    $fp = $pdo->prepare("SELECT id FROM freelancer_profiles WHERE user_id=?");
    $fp->execute([$user_id]);
    $fp_row = $fp->fetch();
    if($fp_row) {
        $ch = $pdo->prepare("SELECT id FROM job_applications WHERE job_id=? AND freelancer_id=?");
        $ch->execute([$job_id, $fp_row['id']]);
        $already_applied = (bool)$ch->fetch();
    }
}
?>

<div class="max-w-2xl">

  <!-- Header -->
  <div class="mb-6">
    <?php if($job['domain_name']): ?>
      <span class="text-xs font-bold text-yellow-700 bg-yellow-100 px-3 py-1 rounded-full">
        <?= htmlspecialchars($job['domain_name']) ?>
      </span>
    <?php endif; ?>
    <h1 class="text-2xl font-extrabold text-gray-800 mt-3 mb-2"><?= htmlspecialchars($job['title']) ?></h1>

    <!-- Client info -->
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-full bg-yellow-400 flex items-center justify-center text-white font-bold text-sm">
        <?php if($job['avatar']): ?>
          <img src="../uploads/avatars/<?= htmlspecialchars($job['avatar']) ?>" class="w-full h-full object-cover rounded-full">
        <?php else: ?>
          <?= strtoupper(substr($job['first_name'],0,1).substr($job['last_name'],0,1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <p class="text-sm font-bold text-gray-700"><?= htmlspecialchars($job['first_name'].' '.$job['last_name']) ?></p>
        <p class="text-xs text-gray-400">Client</p>
      </div>
      <span class="ml-auto text-xs font-bold px-3 py-1 rounded-full
        <?= $job['status']==='open'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?>">
        <?= ucfirst($job['status']) ?>
      </span>
    </div>

    <!-- Meta info -->
    <div class="grid grid-cols-2 gap-3 p-4 bg-white rounded-xl border border-gray-100">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Budget</p>
        <p class="text-sm font-bold text-green-600">
          $<?= number_format($job['budget_min']) ?> – $<?= number_format($job['budget_max']) ?>
          <span class="text-gray-400 font-normal text-xs">(<?= $job['budget_type'] ?>)</span>
        </p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Experience Level</p>
        <p class="text-sm font-bold text-gray-700"><?= ucfirst($job['experience_level']) ?></p>
      </div>
      <?php if($job['deadline']): ?>
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Deadline</p>
        <p class="text-sm font-bold text-gray-700"><?= htmlspecialchars($job['deadline']) ?></p>
      </div>
      <?php endif; ?>
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Posted</p>
        <p class="text-sm font-bold text-gray-700"><?= date('M d, Y', strtotime($job['created_at'])) ?></p>
      </div>
    </div>
  </div>

  <!-- Full description -->
  <div class="mb-8">
    <h2 class="text-base font-extrabold text-gray-700 mb-3">Job Description</h2>
    <div class="bg-white rounded-xl border border-gray-100 p-5 text-sm text-gray-600 leading-relaxed whitespace-pre-line">
      <?= htmlspecialchars($job['description']) ?>
    </div>
  </div>

  <!-- Apply button -->
  <?php if($_SESSION['role'] === 'freelancer'): ?>
    <?php if($already_applied): ?>
      <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-semibold">
        <i class="fa-solid fa-circle-check"></i> You already applied to this job.
      </div>
    <?php elseif($job['status'] === 'open'): ?>
      <a href="apply.php?job_id=<?= $job['id'] ?>"
         class="block w-full text-center bg-yellow-600 hover:bg-yellow-700 text-white font-extrabold py-3 rounded-xl transition shadow-md text-sm">
        <i class="fa-solid fa-paper-plane mr-2"></i>Apply Now
      </a>
    <?php endif; ?>
  <?php endif; ?>

</div>