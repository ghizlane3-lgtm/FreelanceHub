<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: ../home.php');
    exit;
}

$viewer_id          = $_SESSION['user_id'];
$freelancer_user_id = (int)($_GET['id'] ?? 0);

if(!$freelancer_user_id) { header('Location: feed.php'); exit; }

$s = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'freelancer'");
$s->execute([$freelancer_user_id]);
$user = $s->fetch(PDO::FETCH_ASSOC);
if(!$user) { header('Location: feed.php'); exit; }

$s = $pdo->prepare("SELECT * FROM freelancer_profiles WHERE user_id = ?");
$s->execute([$freelancer_user_id]);
$fp = $s->fetch(PDO::FETCH_ASSOC);
if(!$fp) { header('Location: feed.php'); exit; }

$fp_id = $fp['id'];

// Domains
$s = $pdo->prepare("SELECT d.name FROM freelancer_domains fd JOIN domains d ON fd.domain_id = d.id WHERE fd.freelancer_id = ?");
$s->execute([$fp_id]);
$domains = $s->fetchAll(PDO::FETCH_COLUMN);

//  Récupération des compétences depuis la table intermédiaire 
$s_skills = $pdo->prepare("
    SELECT s.name 
    FROM freelancer_skills fs
    JOIN skills s ON fs.skill_id = s.id
    WHERE fs.freelancer_id = ?
");
$s_skills->execute([$fp_id]);
$skills = $s_skills->fetchAll(PDO::FETCH_COLUMN);

// Projects — avec $pdo->prepare
$s2 = $pdo->prepare("SELECT p.*, d.name as domain_name FROM projects p LEFT JOIN domains d ON p.domain_id = d.id WHERE p.freelancer_id = ? ORDER BY p.created_at DESC");
$s2->execute([$fp_id]);
$projects = $s2->fetchAll(PDO::FETCH_ASSOC);



$avg_rating = 0;
if(!empty($feedbacks)) {
    $avg_rating = round(array_sum(array_column($feedbacks, 'rating')) / count($feedbacks), 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?> — FreelanceHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .tab-btn.active { background:#ca8a04; color:#fff; border-color:#ca8a04; }
    .tab-content { display:none; }
    .tab-content.active { display:block; }
  </style>
</head>
<body class="bg-gray-100">

<nav class="fixed top-0 w-full bg-yellow-400 shadow-md z-40 h-16 flex items-center px-6 justify-between">
  <a href="feed.php" class="flex items-center gap-2 text-yellow-800 font-bold hover:text-yellow-900">
    <i class="fa-solid fa-arrow-left"></i> Back to Feed
  </a>
  <span class="text-xl font-extrabold text-yellow-800">FreelanceHub</span>
  <a href="profile.php" class="text-sm text-yellow-800 font-semibold hover:underline">My Profile</a>
</nav>

<div class="pt-20 max-w-4xl mx-auto px-4 pb-12">

  <!-- Profile Header -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <div class="flex items-center gap-6 flex-wrap">
      <div class="w-24 h-24 rounded-full bg-yellow-400 border-4 border-yellow-200 flex items-center justify-center overflow-hidden flex-shrink-0">
        <?php if($user['avatar']): ?>
          <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" class="w-full h-full object-cover">
        <?php else: ?>
          <span class="text-white text-3xl font-extrabold"><?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)) ?></span>
        <?php endif; ?>
      </div>
      <div class="flex-1">
        <h1 class="text-2xl font-extrabold text-gray-800"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h1>
        <?php if($fp['title']): ?>
          <p class="text-yellow-600 font-semibold mt-0.5"><?= htmlspecialchars($fp['title']) ?></p>
        <?php endif; ?>
        <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-400">
          <?php if($user['location']): ?>
            <span><i class="fa-solid fa-location-dot mr-1"></i><?= htmlspecialchars($user['location']) ?></span>
          <?php endif; ?>
          <span><i class="fa-solid fa-briefcase mr-1"></i><?= $fp['jobs_completed'] ?> jobs completed</span>
          <?php if($fp['hourly_rate']): ?>
            <span class="text-green-600 font-bold"><i class="fa-solid fa-dollar-sign mr-1"></i><?= number_format($fp['hourly_rate'],0) ?>/hr</span>
          <?php endif; ?>
          <span class="font-semibold <?= $fp['availability']==='full_time'?'text-green-500':($fp['availability']==='part_time'?'text-yellow-500':'text-gray-400') ?>">
            <?= $fp['availability']==='full_time'?'● Available':($fp['availability']==='part_time'?'◐ Part-time':'○ Not available') ?>
          </span>
        </div>
        
      </div>
      <?php if($_SESSION['role'] === 'client'): ?>
        <a href="post_job.php" class="px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white font-extrabold rounded-xl transition shadow-md text-sm flex-shrink-0">
          <i class="fa-solid fa-handshake mr-2"></i>Hire
        </a>
      <?php endif; ?>
    </div>
    <?php if($user['bio']): ?>
      <p class="mt-5 pt-5 border-t border-gray-100 text-sm text-gray-500 leading-relaxed"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
    <?php endif; ?>
    <?php if(!empty($domains)): ?>
      <div class="flex flex-wrap gap-2 mt-4">
        <?php foreach($domains as $d): ?>
          <span class="text-xs font-bold text-yellow-700 bg-yellow-100 px-3 py-1 rounded-full"><?= htmlspecialchars($d) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div class="flex gap-2 mb-6 flex-wrap">
    <button onclick="switchTab('skills',this)" class="tab-btn active text-sm font-bold px-5 py-2 rounded-full border border-yellow-300 transition"><i class="fa-solid fa-code mr-1"></i>Skills (<?= count($skills) ?>)</button>
    <button onclick="switchTab('projects',this)" class="tab-btn text-sm font-bold px-5 py-2 rounded-full border border-gray-200 text-gray-600 transition hover:border-yellow-300"><i class="fa-solid fa-folder mr-1"></i>Projects (<?= count($projects) ?>)</button>
    
  </div>

  <!-- TAB: SKILLS -->
  <div id="tab-skills" class="tab-content active">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
      <h2 class="font-extrabold text-gray-700 mb-5">Skills & Expertise</h2>
      <?php if(empty($skills)): ?>
        <p class="text-gray-400 text-sm text-center py-8">No skills listed yet.</p>
      <?php else: ?>
        <div class="flex flex-wrap gap-3">
          <?php foreach($skills as $sk): ?>
            <span class="px-4 py-2 bg-yellow-100 text-yellow-800 text-sm font-bold rounded-full border border-yellow-200">
              <?= htmlspecialchars($sk) ?>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TAB: PROJECTS -->
  <div id="tab-projects" class="tab-content">
    <?php if(empty($projects)): ?>
      <div class="bg-white rounded-2xl border border-gray-100 p-10 text-center text-gray-400">
        <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-30"></i><p>No projects yet.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach($projects as $p): ?>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition">
            <div class="h-40 bg-yellow-50 flex items-center justify-center overflow-hidden">
              <?php if($p['cover_image']): ?>
                <img src="../uploads/projects/<?= htmlspecialchars($p['cover_image']) ?>" class="w-full h-full object-cover">
              <?php else: ?>
                <i class="fa-solid fa-image text-yellow-200 text-4xl"></i>
              <?php endif; ?>
            </div>
            <div class="p-4">
              <?php if($p['domain_name']): ?>
                <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-bold"><?= htmlspecialchars($p['domain_name']) ?></span>
              <?php endif; ?>
              <h3 class="font-bold text-gray-800 text-sm mt-2 mb-1"><?= htmlspecialchars($p['title']) ?></h3>
              <p class="text-xs text-gray-400 leading-relaxed"><?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 100)) ?>...</p>
              <?php if($p['project_url']): ?>
                <a href="<?= htmlspecialchars($p['project_url']) ?>" target="_blank" class="mt-2 inline-flex items-center gap-1 text-xs text-yellow-600 font-semibold hover:underline">
                  <i class="fa-solid fa-arrow-up-right-from-square"></i> View Live
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- TAB: FEEDBACKS -->
  <div id="tab-feedback" class="tab-content">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
      <h2 class="font-extrabold text-gray-700 mb-6">Client Reviews</h2>
      <?php if(empty($feedbacks)): ?>
        <p class="text-gray-400 text-sm text-center py-8">No reviews yet.</p>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach($feedbacks as $fb): ?>
          <div class="p-4 border border-gray-100 rounded-xl">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-9 h-9 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
                <span class="text-white text-xs font-bold"><?= strtoupper(substr($fb['first_name'],0,1).substr($fb['last_name'],0,1)) ?></span>
              </div>
              <div class="flex-1">
                <p class="text-sm font-bold text-gray-700"><?= htmlspecialchars($fb['first_name'].' '.$fb['last_name']) ?></p>
                <div class="flex gap-0.5">
                  <?php for($i=1;$i<=5;$i++): ?>
                    <i class="fa-solid fa-star text-xs <?= $i<=$fb['rating']?'text-yellow-400':'text-gray-200' ?>"></i>
                  <?php endfor; ?>
                </div>
              </div>
              <span class="text-xs text-gray-400"><?= date('M d, Y', strtotime($fb['created_at'])) ?></span>
            </div>
            <?php if($fb['comment']): ?>
              <p class="text-sm text-gray-500 leading-relaxed pl-12"><?= htmlspecialchars($fb['comment']) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.classList.add('text-gray-600'); });
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
  btn.classList.remove('text-gray-600');
}
</script>
</body>
</html>