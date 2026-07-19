<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'freelancer') {
    header('Location: ../home.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$job_id  = (int)($_GET['id'] ?? 0);

// Filters
$filter_domain = (int)($_GET['domain'] ?? 0);
$filter_budget = $_GET['budget'] ?? '';
$filter_level  = $_GET['level']  ?? '';

// Build query
$sql    = "SELECT jp.*, u.first_name, u.last_name, d.name as domain_name
           FROM job_proposals jp
           JOIN users u ON jp.client_id = u.id
           LEFT JOIN domains d ON jp.domain_id = d.id
           WHERE jp.status = 'open'";
$params = [];

if($filter_domain) { $sql .= " AND jp.domain_id = ?";            $params[] = $filter_domain; }
if($filter_level)  { $sql .= " AND jp.experience_level = ?";     $params[] = $filter_level;  }
if($filter_budget === 'low')  $sql .= " AND jp.budget_max < 500";
if($filter_budget === 'mid')  $sql .= " AND jp.budget_max BETWEEN 500 AND 2000";
if($filter_budget === 'high') $sql .= " AND jp.budget_max > 2000";

$sql .= " ORDER BY jp.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Single job detail
$selected_job    = null;
$already_applied = false;

if($job_id) {
    $s = $pdo->prepare("
        SELECT jp.*, u.first_name, u.last_name, u.avatar, d.name as domain_name
        FROM job_proposals jp
        JOIN users u ON jp.client_id = u.id
        LEFT JOIN domains d ON jp.domain_id = d.id
        WHERE jp.id = ?
    ");
    $s->execute([$job_id]);
    $selected_job = $s->fetch(PDO::FETCH_ASSOC);

    // Check already applied
    $fp = $pdo->prepare("SELECT id FROM freelancer_profiles WHERE user_id=?");
    $fp->execute([$user_id]);
    $fp_row = $fp->fetch();
    if($fp_row) {
        $ch = $pdo->prepare("SELECT id FROM job_applications WHERE job_id=? AND freelancer_id=?");
        $ch->execute([$job_id, $fp_row['id']]);
        $already_applied = (bool)$ch->fetch();
    }
}

// If no job selected, pick the first one
if(!$selected_job && !empty($all_jobs)) {
    $first_id = $all_jobs[0]['id'];
    $s = $pdo->prepare("
        SELECT jp.*, u.first_name, u.last_name, u.avatar, d.name as domain_name
        FROM job_proposals jp
        JOIN users u ON jp.client_id = u.id
        LEFT JOIN domains d ON jp.domain_id = d.id
        WHERE jp.id = ?
    ");
    $s->execute([$first_id]);
    $selected_job = $s->fetch(PDO::FETCH_ASSOC);
    $job_id = $first_id;
}

$domains = $pdo->query("SELECT id, name FROM domains ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Proposals - FreelanceHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .job-row { transition: all 0.15s; cursor: pointer; border-left: 3px solid transparent; }
    .job-row:hover { background: #fefce8; border-left-color: #fde68a; }
    .job-row.active { background: #fef9c3; border-left-color: #ca8a04; }
    .list-panel  { height: calc(100vh - 64px); overflow-y: auto; }
    .detail-panel { height: calc(100vh - 64px); overflow-y: auto; }
  </style>
</head>
<body class="bg-gray-50">

<!-- NAVBAR -->
<nav class="fixed top-0 w-full bg-yellow-400 shadow-md z-40 h-16 flex items-center px-6 justify-between">
  <div class="flex items-center gap-3">
    <a href="feed.php" class="text-yellow-800 font-bold hover:text-yellow-900 flex items-center gap-2">
      <i class="fa-solid fa-arrow-left"></i> Back to Feed
    </a>
    <span class="text-yellow-600">|</span>
    <span class="text-lg font-extrabold text-yellow-900">All Job Proposals</span>
  </div>
  <span class="text-sm font-bold text-yellow-800 bg-yellow-300 px-3 py-1 rounded-full">
    <?= count($all_jobs) ?> open
  </span>
</nav>

<div class="pt-16 flex">

  <!-- ===== LEFT: list ===== -->
  <div class="w-[38%] bg-white border-r border-gray-200 list-panel flex flex-col fixed left-0 top-16">

    <!-- Filters -->
    <form method="GET" class="p-4 border-b border-gray-100 space-y-2">
      <div class="flex gap-2">
        <select name="domain" onchange="this.form.submit()"
          class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-yellow-400 bg-white">
          <option value="">All Domains</option>
          <?php foreach($domains as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $filter_domain==$d['id']?'selected':'' ?>>
              <?= htmlspecialchars($d['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select name="level" onchange="this.form.submit()"
          class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-yellow-400 bg-white">
          <option value="">All Levels</option>
          <option value="entry"        <?= $filter_level==='entry'       ?'selected':'' ?>>Entry</option>
          <option value="intermediate" <?= $filter_level==='intermediate'?'selected':'' ?>>Intermediate</option>
          <option value="expert"       <?= $filter_level==='expert'      ?'selected':'' ?>>Expert</option>
        </select>
      </div>
      <div class="flex gap-2">
        <select name="budget" onchange="this.form.submit()"
          class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-yellow-400 bg-white">
          <option value="">Any Budget</option>
          <option value="low"  <?= $filter_budget==='low' ?'selected':'' ?>>Under $500</option>
          <option value="mid"  <?= $filter_budget==='mid' ?'selected':'' ?>>$500 – $2,000</option>
          <option value="high" <?= $filter_budget==='high'?'selected':'' ?>>Over $2,000</option>
        </select>
        <?php if($filter_domain || $filter_budget || $filter_level): ?>
          <a href="job.php" class="px-3 py-2 text-xs font-bold text-red-400 border border-red-200 rounded-lg hover:bg-red-50 whitespace-nowrap flex items-center">
            <i class="fa-solid fa-xmark mr-1"></i>Clear
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Job rows -->
    <div class="flex-1 overflow-y-auto divide-y divide-gray-50">
      <?php if(empty($all_jobs)): ?>
        <p class="text-gray-400 text-sm text-center py-16">No jobs match your filters.</p>
      <?php endif; ?>
      <?php foreach($all_jobs as $job): ?>
        <div class="job-row px-4 py-3 <?= $job_id==$job['id']?'active':'' ?>"
             onclick="location.href='job.php?id=<?= $job['id'] ?><?= $filter_domain?"&domain=$filter_domain":'' ?><?= $filter_level?"&level=$filter_level":'' ?><?= $filter_budget?"&budget=$filter_budget":'' ?>'">
          <div class="flex justify-between items-start gap-2 mb-1">
            <h3 class="font-bold text-gray-800 text-sm leading-snug"><?= htmlspecialchars($job['title']) ?></h3>
            <?php if($job['domain_name']): ?>
              <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0">
                <?= htmlspecialchars($job['domain_name']) ?>
              </span>
            <?php endif; ?>
          </div>
          <p class="text-xs text-gray-400 mb-1">
            <i class="fa-solid fa-user mr-1"></i><?= htmlspecialchars($job['first_name'].' '.$job['last_name']) ?>
          </p>
          <div class="flex justify-between text-xs">
            <span class="text-green-600 font-bold">
              $<?= number_format($job['budget_min']) ?> – $<?= number_format($job['budget_max']) ?>
            </span>
            <span class="text-gray-400"><?= ucfirst($job['experience_level']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ===== RIGHT: detail ===== -->
  <div class="ml-[38%] w-[62%] detail-panel">
    <?php if($selected_job): ?>
      <div class="p-8 max-w-2xl">

        <!-- Domain + Title -->
        <?php if($selected_job['domain_name']): ?>
          <span class="text-xs font-bold text-yellow-700 bg-yellow-100 px-3 py-1 rounded-full">
            <?= htmlspecialchars($selected_job['domain_name']) ?>
          </span>
        <?php endif; ?>
        <h1 class="text-2xl font-extrabold text-gray-800 mt-3 mb-3">
          <?= htmlspecialchars($selected_job['title']) ?>
        </h1>

        <!-- Client -->
        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 rounded-full bg-yellow-400 flex items-center justify-center text-white font-bold overflow-hidden flex-shrink-0">
            <?php if($selected_job['avatar']): ?>
              <img src="../uploads/avatars/<?= htmlspecialchars($selected_job['avatar']) ?>" class="w-full h-full object-cover">
            <?php else: ?>
              <?= strtoupper(substr($selected_job['first_name'],0,1).substr($selected_job['last_name'],0,1)) ?>
            <?php endif; ?>
          </div>
          <div>
            <p class="text-sm font-bold text-gray-700"><?= htmlspecialchars($selected_job['first_name'].' '.$selected_job['last_name']) ?></p>
            <p class="text-xs text-gray-400">Client · <?= date('M d, Y', strtotime($selected_job['created_at'])) ?></p>
          </div>
          <span class="ml-auto text-xs font-bold px-3 py-1 rounded-full bg-green-100 text-green-700">
            Open
          </span>
        </div>

        <!-- Meta grid -->
        <div class="grid grid-cols-2 gap-3 p-4 bg-white rounded-xl border border-gray-100 mb-6">
          <div>
            <p class="text-xs text-gray-400 mb-0.5">Budget</p>
            <p class="text-sm font-bold text-green-600">
              $<?= number_format($selected_job['budget_min']) ?> – $<?= number_format($selected_job['budget_max']) ?>
              <span class="text-gray-400 font-normal">(<?= $selected_job['budget_type'] ?>)</span>
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-400 mb-0.5">Experience</p>
            <p class="text-sm font-bold text-gray-700"><?= ucfirst($selected_job['experience_level']) ?></p>
          </div>
          <?php if($selected_job['deadline']): ?>
          <div>
            <p class="text-xs text-gray-400 mb-0.5">Deadline</p>
            <p class="text-sm font-bold text-gray-700"><?= $selected_job['deadline'] ?></p>
          </div>
          <?php endif; ?>
        </div>

        <!-- Full description -->
        <h2 class="text-base font-extrabold text-gray-700 mb-3">Description</h2>
        <div class="bg-white rounded-xl border border-gray-100 p-5 text-sm text-gray-600 leading-relaxed whitespace-pre-line mb-6">
          <?= nl2br(htmlspecialchars($selected_job['description'])) ?>
        </div>

        <!-- Apply button -->
        <?php if($already_applied): ?>
          <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-semibold">
            <i class="fa-solid fa-circle-check text-lg"></i>
            You already applied to this job.
          </div>
        <?php else: ?>
          <a href="apply.php?job_id=<?= $selected_job['id'] ?>"
             class="block w-full text-center bg-yellow-600 hover:bg-yellow-700 text-white font-extrabold py-3 rounded-xl transition shadow-md">
            <i class="fa-solid fa-paper-plane mr-2"></i>Apply Now
          </a>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <div class="flex flex-col items-center justify-center h-full text-gray-300">
        <i class="fa-solid fa-hand-pointer text-6xl mb-4 opacity-30"></i>
        <p class="text-xl font-bold text-gray-400">Select a job to read it</p>
        <p class="text-sm text-gray-400 mt-1">Click any job on the left</p>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>