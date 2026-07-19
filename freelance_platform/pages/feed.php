<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$me_stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
$me_stmt->execute([$_SESSION['user_id']]);
$user = $me_stmt->fetch(PDO::FETCH_ASSOC);


// --- Fetch job proposals (left sidebar) ---
$jobsStmt = $pdo->prepare('
    SELECT jp.id, jp.title, jp.description, jp.budget_min, jp.budget_max,
           jp.budget_type, jp.deadline, jp.experience_level,
           d.name AS domain_name,
           u.first_name, u.last_name
    FROM job_proposals jp
    LEFT JOIN domains d ON jp.domain_id = d.id
    LEFT JOIN users u ON jp.client_id = u.id
    WHERE jp.status = "open"
    ORDER BY jp.created_at DESC
    LIMIT 20
');
$jobsStmt->execute();
$jobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch freelancer projects (main feed) ---
$filter_domain = isset($_GET['domain']) ? (int)$_GET['domain'] : 0;
$filter_budget = isset($_GET['budget']) ? $_GET['budget'] : '';


$sql = '
    SELECT p.id, p.title, p.description, p.cover_image, p.project_url,
           d.name AS domain_name,
           u.id AS user_id, u.first_name, u.last_name, u.avatar,
           fp.title AS freelancer_title, fp.hourly_rate
    FROM projects p
    LEFT JOIN domains d ON p.domain_id = d.id
    LEFT JOIN freelancer_profiles fp ON p.freelancer_id = fp.id
    LEFT JOIN users u ON fp.user_id = u.id
    WHERE u.id IS NOT NULL
';
$params = [];
if ($filter_domain) {
    $sql .= ' AND p.domain_id = ?';
    $params[] = $filter_domain;
}
$sql .= ' ORDER BY p.id DESC LIMIT 30'; // NETTOYÉ : Plus besoin du GROUP BY p.id vu qu'il n'y a plus d'agrégation

$projStmt = $pdo->prepare($sql);
$projStmt->execute($params);
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Domains for filter ---
$domains = $pdo->query('SELECT id, name FROM domains ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$title = 'Feed — FreelanceHub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .sidebar { height: calc(100vh - 64px); overflow-y: auto; }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
    .feed-area { height: calc(100vh - 64px); overflow-y: auto; }
    .feed-area::-webkit-scrollbar { width: 4px; }
    .feed-area::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
    .job-card { transition: all 0.2s; }
    .job-card:hover { transform: translateX(3px); }
    .project-card { transition: all 0.25s; }
    .project-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.10); }
    
    /* NETTOYÉ : Les classes CSS des étoiles ont été retirées d'ici */

    /* ── Responsive: sidebar drawer on mobile ── */
    @media (max-width: 767px) {
      .sidebar {
        position: fixed;
        top: 64px;
        left: 0;
        width: 85vw;
        max-width: 320px;
        height: calc(100vh - 64px);
        z-index: 30;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        box-shadow: 4px 0 24px rgba(0,0,0,0.10);
      }
      .sidebar.open {
        transform: translateX(0);
      }
      .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        top: 64px;
        background: rgba(0,0,0,0.35);
        z-index: 20;
      }
      .sidebar-overlay.open {
        display: block;
      }
      .feed-area {
        height: auto;
        min-height: calc(100vh - 64px);
      }
    }
  </style>
</head>
<body class="bg-gray-100">

<nav class="fixed top-0 w-full z-40 bg-yellow-400 shadow-md h-16 flex items-center px-6 justify-between">
  <a href="../home.php" class="flex items-center gap-2">
    <div class="w-8 h-8 bg-yellow-700 rounded-full flex items-center justify-center">
      <i class="fa-solid fa-briefcase text-white text-sm"></i>
    </div>
    <span class="text-xl font-extrabold text-yellow-800">FreelanceHub</span>
  </a>
  
  <div class="flex items-center gap-3">
    <button onclick="toggleSidebar()"
            class="md:hidden flex items-center gap-1.5 px-3 py-1.5 bg-yellow-700 text-white text-xs font-bold rounded-full hover:bg-yellow-800 transition">
      <i class="fa-solid fa-briefcase"></i> Jobs
      <?php if(!empty($jobs)): ?>
        <span class="bg-white text-yellow-700 text-xs font-extrabold px-1.5 rounded-full"><?= count($jobs) ?></span>
      <?php endif; ?>
    </button>

    <span class="text-yellow-800 text-sm font-medium hidden sm:block">
      Hi, <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> 👋
    </span>
    <a href="profile.php"
       class="flex items-center gap-2 px-4 py-2 bg-yellow-700 text-white rounded-full text-sm font-bold hover:bg-yellow-800 transition">
      <i class="fa-solid fa-user"></i>
      <span class="hidden sm:inline">My Profile</span>
    </a>
    <a href="logout.php"
       class="px-3 py-2 text-yellow-800 hover:text-yellow-900 text-sm font-semibold">
      <i class="fa-solid fa-right-from-bracket"></i>
    </a>
  </div>
</nav>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="flex pt-16">

  <aside class="sidebar w-80 flex-shrink-0 bg-white border-r border-gray-200 p-4">
    <h2 class="text-sm font-extrabold text-gray-500 uppercase tracking-widest mb-4 px-1">
      <i class="fa-solid fa-briefcase mr-2 text-yellow-500"></i>Job Proposals
    </h2>

    <?php if (empty($jobs)): ?>
      <p class="text-gray-400 text-sm text-center mt-10">No open jobs yet.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($jobs as $job): ?>
        <div class="job-card border border-gray-100 rounded-xl p-4 bg-gray-50 cursor-pointer"
             onclick="openJob(<?= $job['id'] ?>)">

          <?php if ($job['domain_name']): ?>
          <span class="inline-block text-xs font-bold text-yellow-700 bg-yellow-100 px-2 py-0.5 rounded-full mb-2">
            <?= htmlspecialchars($job['domain_name']) ?>
          </span>
          <?php endif; ?>

          <h3 class="font-bold text-gray-800 text-sm leading-snug mb-1">
            <?= htmlspecialchars($job['title']) ?>
          </h3>

          <p class="text-xs text-gray-400 mb-2">
            by <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
          </p>

          <?php if ($job['budget_min'] || $job['budget_max']): ?>
          <div class="flex items-center gap-1 text-xs font-semibold text-green-600 mb-2">
            <i class="fa-solid fa-dollar-sign"></i>
            <?php if ($job['budget_type'] === 'hourly'): ?>
              <?= number_format($job['budget_min']) ?>–<?= number_format($job['budget_max']) ?>/hr
            <?php else: ?>
              <?= number_format($job['budget_min']) ?>–<?= number_format($job['budget_max']) ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <p class="text-xs text-gray-500 leading-relaxed mb-3 line-clamp-2">
            <?= htmlspecialchars(substr($job['description'], 0, 100)) ?>...
          </p>

          <?php if ($_SESSION['role'] !== 'client'): ?>
          <button onclick="openJob(<?= $job['id'] ?>)"
                  class="w-full text-xs font-bold text-yellow-700 border-yellow-300 rounded-lg py-1.5 hover:bg-yellow-50 transition">
            Read More & Apply
          </button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>

  <main class="feed-area flex-1 p-6">

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <h2 class="text-lg font-extrabold text-gray-800 mr-2">Explore Projects</h2>
      <form method="GET" class="flex items-center gap-2 ml-auto flex-wrap">
        <select name="domain"
                onchange="this.form.submit()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-600 focus:ring-2 focus:ring-yellow-400 outline-none">
          <option value="0">All Domains</option>
          <?php foreach ($domains as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $filter_domain == $d['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($filter_domain): ?>
          <a href="feed.php" class="text-sm text-gray-400 hover:text-red-500 font-semibold">
            <i class="fa-solid fa-xmark"></i> Clear
          </a>
        <?php endif; ?>
      </form>
    </div>

    <?php if (empty($projects)): ?>
      <div class="flex flex-col items-center justify-center mt-24 text-gray-400">
        <i class="fa-solid fa-folder-open text-5xl mb-4 opacity-30"></i>
        <p class="text-lg font-semibold">No projects yet.</p>
        <p class="text-sm mt-1">Be the first to add one on your profile!</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($projects as $proj): ?>
        <div class="project-card bg-white rounded-2xl border border-gray-100 overflow-hidden">

          <div class="h-40 bg-gradient-to-br from-yellow-100 to-yellow-200 relative overflow-hidden">
            <?php if ($proj['cover_image']): ?>
              <img src="/freelance_platform/uploads/projects/<?= htmlspecialchars($proj['cover_image']) ?>"
                   alt="project cover"
                   class="w-full h-full object-cover">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center">
                <i class="fa-solid fa-image text-yellow-300 text-4xl"></i>
              </div>
            <?php endif; ?>
            <?php if ($proj['domain_name']): ?>
              <span class="absolute top-3 left-3 text-xs font-bold text-yellow-700 bg-white px-2 py-0.5 rounded-full shadow">
                <?= htmlspecialchars($proj['domain_name']) ?>
              </span>
            <?php endif; ?>
          </div>

          <div class="p-4">
            <h3 class="font-extrabold text-gray-800 text-sm mb-1 leading-snug">
              <?= htmlspecialchars($proj['title']) ?>
            </h3>
            <p class="text-xs text-gray-400 mb-3 line-clamp-2 leading-relaxed">
              <?= htmlspecialchars(substr($proj['description'], 0, 110)) ?>...
            </p>

            <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
              <div class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0 overflow-hidden">
                <?php if ($proj['avatar']): ?>
                  <img src="/freelance_platform/uploads/avatars/<?= htmlspecialchars($proj['avatar']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                  <span class="text-white text-xs font-bold">
                    <?= strtoupper(substr($proj['first_name'], 0, 1) . substr($proj['last_name'], 0, 1)) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-gray-700 truncate">
                  <?= htmlspecialchars($proj['first_name'] . ' ' . $proj['last_name']) ?>
                </p>
                <?php if ($proj['freelancer_title']): ?>
                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($proj['freelancer_title']) ?></p>
                <?php endif; ?>
              </div>

              </div>

            <a href="freelancer.php?id=<?= $proj['user_id'] ?>"
               class="mt-3 block w-full text-center text-xs font-bold text-yellow-700 border border-yellow-300 rounded-lg py-2 hover:bg-yellow-50 transition">
              View Profile
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

</div>

<div id="job-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center px-4">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeJob()"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 border-t-4 border-yellow-500 overflow-hidden">
    <div id="job-modal-body" class="p-6 max-h-[80vh] overflow-y-auto">
      <p class="text-gray-400 text-sm text-center py-10">Loading...</p>
    </div>
    <button onclick="closeJob()"
            class="absolute top-4 right-4 text-gray-400 hover:text-black text-xl">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>
</div>

<script>
function openJob(id) {
  document.getElementById('job-modal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  location.href = 'job.php?id=' + id;
}
function closeJob() {
  document.getElementById('job-modal').classList.add('hidden');
  document.body.style.overflow = '';
}
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('open');
}
</script>

</body>
</html>