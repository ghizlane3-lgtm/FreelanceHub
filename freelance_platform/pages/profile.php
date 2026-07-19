<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';


$stmt = $pdo->prepare("
    SELECT u.*, fp.id as fp_id, fp.title as fp_title, fp.hourly_rate,
           fp.availability, fp.jobs_completed, fp.portfolio_url,
           fp.description as fp_description
    FROM users u
    LEFT JOIN freelancer_profiles fp ON fp.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { session_destroy(); header('Location: login.php'); exit; }

$fp_id = $user['fp_id'] ?? 0;

// ── Extra data per role ─────────────────
$projects    = [];
$my_apps     = [];
$feedbacks   = [];
$my_proposals = [];

if ($user['role'] === 'freelancer' && $fp_id) {

    // Projects
    $s = $pdo->prepare("
        SELECT p.*, d.name as domain_name FROM projects p
        LEFT JOIN domains d ON p.domain_id = d.id
        WHERE p.freelancer_id = ? ORDER BY p.created_at DESC
    ");
    $s->execute([$fp_id]);
    $projects = $s->fetchAll(PDO::FETCH_ASSOC);

    // My applications
    $s = $pdo->prepare("
        SELECT ja.id, ja.proposed_rate, ja.status, ja.applied_at,
               jp.title, d.name as domain_name
        FROM job_applications ja
        JOIN job_proposals jp ON ja.job_id = jp.id
        LEFT JOIN domains d ON jp.domain_id = d.id
        WHERE ja.freelancer_id = ?
        ORDER BY ja.applied_at DESC
    ");
    $s->execute([$fp_id]);
    $my_apps = $s->fetchAll(PDO::FETCH_ASSOC);

   
}

if ($user['role'] === 'client') {
    $s = $pdo->prepare("
        SELECT jp.*, d.name as domain_name
        FROM job_proposals jp
        LEFT JOIN domains d ON jp.domain_id = d.id
        WHERE jp.client_id = ? ORDER BY jp.created_at DESC
    ");
    $s->execute([$user_id]);
    $my_proposals = $s->fetchAll(PDO::FETCH_ASSOC);
}

// ── POST — save profile ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $bio        = trim($_POST['bio']        ?? '');

    // Avatar upload
    if (!empty($_FILES['avatar']['name'])) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['avatar']['size'] < 2000000) {
            $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $dest     = __DIR__ . '/../uploads/avatars/' . $new_name;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                if ($user['avatar'] && file_exists(__DIR__ . '/../uploads/avatars/' . $user['avatar'])) {
                    unlink(__DIR__ . '/../uploads/avatars/' . $user['avatar']);
                }
                $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$new_name, $user_id]);
                $user['avatar'] = $new_name;
            } else { $error = 'Upload failed.'; }
        } else { $error = 'Invalid file. Max 2MB, jpg/png/webp only.'; }
    }

    $pdo->prepare("UPDATE users SET first_name=?, last_name=?, bio=? WHERE id=?")
        ->execute([$first_name, $last_name, $bio, $user_id]);

    if ($user['role'] === 'freelancer') {
        $fp_title     = trim($_POST['fp_title']     ?? '');
        $hourly_rate  = $_POST['hourly_rate']       ?? null;
        $availability = $_POST['availability']      ?? 'full_time';
        $portfolio    = trim($_POST['portfolio_url']?? '');
        $fp_desc      = trim($_POST['fp_description']?? '');
        $skills_arr   = $_POST['skills']            ?? []; 
        
        $skills_arr = array_unique(array_map('trim', $skills_arr));

        // 1. Mettre à jour ou créer le profil
        if ($fp_id) {
            $pdo->prepare("
                UPDATE freelancer_profiles
                SET title=?, hourly_rate=?, availability=?, portfolio_url=?, description=?
                WHERE user_id=?
            ")->execute([$fp_title, $hourly_rate ?: null, $availability, $portfolio, $fp_desc, $user_id]);
        } else {
            $pdo->prepare("
                INSERT INTO freelancer_profiles (user_id, title, hourly_rate, availability, portfolio_url, description)
                VALUES (?,?,?,?,?,?)
            ")->execute([$user_id, $fp_title, $hourly_rate ?: null, $availability, $portfolio, $fp_desc]);
            $fp_id = $pdo->lastInsertId();
        }

        // 2. Gestion des compétences dans la table de liaison (freelancer_skills)
        if ($fp_id) {
            $pdo->beginTransaction();
            try {
                $del_stmt = $pdo->prepare("DELETE FROM freelancer_skills WHERE freelancer_id = ?");
                $del_stmt->execute([$fp_id]);

                $search_skill = $pdo->prepare("SELECT id FROM skills WHERE LOWER(name) = LOWER(?)");
                $insert_skill = $pdo->prepare("INSERT INTO skills (name) VALUES (?)");
                $link_skill   = $pdo->prepare("INSERT INTO freelancer_skills (freelancer_id, skill_id, level) VALUES (?, ?, ?)");

                foreach ($skills_arr as $skill_name) {
                    if (empty($skill_name)) continue;

                    $search_skill->execute([$skill_name]);
                    $skill = $search_skill->fetch(PDO::FETCH_ASSOC);

                    if ($skill) {
                        $skill_id = $skill['id'];
                    } else {
                        $insert_skill->execute([$skill_name]);
                        $skill_id = $pdo->lastInsertId();
                    }

                    $link_skill->execute([$fp_id, $skill_id, 100]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error updating skills: " . $e->getMessage();
            }
        }
    }

    $_SESSION['full_name'] = $first_name . ' ' . $last_name;
    $success = 'Profile updated successfully!';

    // Reload user data (Ici, $stmt réutilisera la nouvelle requête corrigée du haut !)
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $fp_id = $user['fp_id'] ?? 0;
}

// ── Récupération des compétences pour l'affichage ──
$all_skills  = ['PHP','JavaScript','React','Laravel','MySQL','Tailwind','Vue.js','Node.js','Python','UI/UX','WordPress','Figma','Docker','Git','AWS'];
$user_skills = [];

if ($user['role'] === 'freelancer' && $fp_id) {
    $s_skills = $pdo->prepare("
        SELECT s.name 
        FROM freelancer_skills fs
        JOIN skills s ON fs.skill_id = s.id
        WHERE fs.freelancer_id = ?
    ");
    $s_skills->execute([$fp_id]);
    $user_skills = $s_skills->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — FreelanceHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .tab-btn.active { background:#ca8a04; color:#fff; border-color:#ca8a04; }
    .tab-content    { display:none; }
    .tab-content.active { display:block; }
  </style>
</head>
<body class="bg-gray-50">

<!-- NAVBAR -->
<nav class="fixed top-0 w-full bg-yellow-400 shadow-md z-40 h-16 flex items-center px-6 justify-between">
  <a href="../home.php" class="text-2xl font-extrabold text-yellow-800">FreelanceHub</a>
  <div class="flex items-center gap-4">
    <a href="feed.php" class="text-yellow-900 font-semibold hover:underline">Feed</a>
    <a href="profile.php" class="w-10 h-10 bg-yellow-700 rounded-full flex items-center justify-center text-white overflow-hidden">
      <?php if($user['avatar']): ?>
        <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" class="w-full h-full object-cover">
      <?php else: ?>
        <span class="font-bold text-sm"><?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)) ?></span>
      <?php endif; ?>
    </a>
    <a href="logout.php" class="text-yellow-900 text-sm font-semibold hover:underline">Logout</a>
  </div>
</nav>

<div class="pt-20 pb-12 px-4 max-w-4xl mx-auto">

  <!-- Header card -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6 flex items-center gap-5 flex-wrap">
    <div class="w-20 h-20 rounded-full bg-yellow-400 border-4 border-yellow-200 overflow-hidden flex items-center justify-center flex-shrink-0">
      <?php if($user['avatar']): ?>
        <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" class="w-full h-full object-cover">
      <?php else: ?>
        <?php if(!empty($user['avatar'])): ?>
    <div>
        <img src="uploads/avatars/<?= $user['avatar'] ?>" width="100">
        <br>
        <button type="submit" name="remove_avatar" value="1" 
                style="background:red; color:white; border:none; padding:5px; border-radius:4px;"
                onclick="return confirm('Are you sure?')">
            Remove Avatar
        </button>
    </div>
<?php endif; ?>
        <span class="text-white text-2xl font-extrabold"><?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)) ?></span>
      <?php endif; ?>
    </div>
    <div>
      <h1 class="text-2xl font-extrabold text-gray-800"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h1>
      <?php if(!empty($user['fp_title'])): ?>
        <p class="text-yellow-600 font-semibold"><?= htmlspecialchars($user['fp_title']) ?></p>
      <?php endif; ?>
      <p class="text-sm text-gray-400 mt-1"><?= htmlspecialchars($user['email']) ?></p>
    </div>
  </div>

  <!-- Alerts -->
  <?php if($success): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center gap-2">
      <i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  <?php if($error): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-600 rounded-xl flex items-center gap-2">
      <i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- TABS -->
  <div class="flex gap-2 mb-6 flex-wrap">
    <button onclick="switchTab('edit',this)"   class="tab-btn active text-sm font-bold px-5 py-2 rounded-full border border-yellow-300 transition"><i class="fa-solid fa-pen mr-1"></i>Edit Profile</button>
    <?php if($user['role']==='freelancer'): ?>
    <button onclick="switchTab('projects',this)" class="tab-btn text-sm font-bold px-5 py-2 rounded-full border border-gray-200 text-gray-600 transition hover:border-yellow-300"><i class="fa-solid fa-folder mr-1"></i>Projects (<?= count($projects) ?>)</button>
    <button onclick="switchTab('apps',this)"    class="tab-btn text-sm font-bold px-5 py-2 rounded-full border border-gray-200 text-gray-600 transition hover:border-yellow-300"><i class="fa-solid fa-paper-plane mr-1"></i>Applications (<?= count($my_apps) ?>)</button>
    
    <?php else: ?>
    <button onclick="switchTab('proposals',this)" class="tab-btn text-sm font-bold px-5 py-2 rounded-full border border-gray-200 text-gray-600 transition hover:border-yellow-300"><i class="fa-solid fa-briefcase mr-1"></i>My Jobs (<?= count($my_proposals) ?>)</button>
    <?php endif; ?>
  </div>

  <!-- ===== TAB: EDIT ===== -->
  <div id="tab-edit" class="tab-content active">
    <form method="POST" enctype="multipart/form-data" class="space-y-6">

      <!-- Basic info -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-extrabold text-gray-700 mb-5 flex items-center gap-2"><i class="fa-solid fa-user text-yellow-500"></i> Basic Info</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bio</label>
            <textarea name="bio" rows="3" placeholder="Tell clients about yourself..." class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none resize-none"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Profile Photo</label>
            <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)" class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-yellow-100 file:text-yellow-700 hover:file:bg-yellow-200">
            <img id="avatar-preview" src="" class="mt-2 w-14 h-14 rounded-full object-cover hidden">
          </div>
        </div>
      </div>

      <!-- Freelancer specific -->
      <?php if($user['role'] === 'freelancer'): ?>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-extrabold text-gray-700 mb-5 flex items-center gap-2"><i class="fa-solid fa-briefcase text-yellow-500"></i> Professional Info</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Professional Title</label>
            <input type="text" name="fp_title" value="<?= htmlspecialchars($user['fp_title'] ?? '') ?>" placeholder="e.g. Full Stack Developer" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hourly Rate ($)</label>
            <input type="number" name="hourly_rate" step="0.01" value="<?= htmlspecialchars($user['hourly_rate'] ?? '') ?>" placeholder="25" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Portfolio URL</label>
            <input type="url" name="portfolio_url" value="<?= htmlspecialchars($user['portfolio_url'] ?? '') ?>" placeholder="https://..." class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Availability</label>
            <div class="space-y-1">
              <?php foreach(['full_time'=>'Full Time','part_time'=>'Part Time','not_available'=>'Not Available'] as $v=>$l): ?>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="availability" value="<?= $v ?>" class="accent-yellow-500" <?= ($user['availability']??'full_time')===$v?'checked':'' ?>>
                <span class="text-sm text-gray-600"><?= $l ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="md:col-span-3">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
            <textarea name="fp_description" rows="3" placeholder="Describe your services..." class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none resize-none"><?= htmlspecialchars($user['fp_description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Skills -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-extrabold text-gray-700 mb-2 flex items-center gap-2">
          <i class="fa-solid fa-code text-yellow-500"></i> Skills
          <span id="skill-count" class="text-xs font-normal text-gray-400 ml-1">0/15</span>
        </h2>
        <div id="skills-container" class="flex flex-wrap gap-2 mb-3">
          <?php foreach($all_skills as $skill): ?>
          <label class="cursor-pointer">
            <input type="checkbox" name="skills[]" value="<?= $skill ?>" <?= in_array($skill,$user_skills)?'checked':'' ?> class="peer sr-only skill-cb">
            <div class="px-4 py-2 rounded-full border-2 border-gray-200 text-sm font-medium text-gray-600 peer-checked:bg-yellow-600 peer-checked:border-yellow-600 peer-checked:text-white hover:border-yellow-400 transition select-none">
              <?= $skill ?>
            </div>
          </label>
          <?php endforeach; ?>
          <?php foreach($user_skills as $sk): if(!in_array($sk,$all_skills)&&$sk): ?>
          <label class="cursor-pointer custom-skill-label">
            <input type="checkbox" name="skills[]" value="<?= htmlspecialchars($sk) ?>" checked class="peer sr-only skill-cb">
            <div class="px-4 py-2 rounded-full border-2 border-yellow-600 bg-yellow-600 text-white text-sm font-medium flex items-center gap-1">
              <?= htmlspecialchars($sk) ?> <span class="remove-custom ml-1 hover:text-red-200 cursor-pointer">×</span>
            </div>
          </label>
          <?php endif; endforeach; ?>
        </div>
        <div class="flex gap-2">
          <input type="text" id="custom-skill" placeholder="Add custom skill + Enter" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-yellow-400 outline-none">
          <button type="button" id="add-skill-btn" class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-yellow-700"><i class="fa-solid fa-plus"></i></button>
        </div>
      </div>
      <?php endif; ?>

      <div class="flex gap-3">
        <button type="submit" class="px-8 py-3 bg-yellow-600 text-white font-extrabold rounded-xl hover:bg-yellow-700 transition shadow-md text-sm"><i class="fa-solid fa-floppy-disk mr-2"></i>Save Changes</button>
        <a href="feed.php" class="px-8 py-3 bg-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-300 transition text-sm">Cancel</a>
      </div>
    </form>
  </div>

  <!-- ===== TAB: PROJECTS ===== -->
  <?php if($user['role']==='freelancer'): ?>
  <div id="tab-projects" class="tab-content">
    <div class="flex justify-between items-center mb-4">
      <h2 class="font-extrabold text-gray-700">My Projects</h2>
      <a href="add_project.php" class="px-4 py-2 bg-yellow-600 text-white text-sm font-bold rounded-xl hover:bg-yellow-700 transition">
        <i class="fa-solid fa-plus mr-1"></i>Add Project
      </a>
    </div>
    <?php if(empty($projects)): ?>
      <p class="text-gray-400 text-sm py-10 text-center">No projects yet.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach($projects as $p): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="h-36 bg-yellow-50 flex items-center justify-center overflow-hidden">
            <?php if($p['cover_image']): ?>
              <img src="../uploads/projects/<?= htmlspecialchars($p['cover_image']) ?>" class="w-full h-full object-cover">
            <?php else: ?>
              <i class="fa-solid fa-image text-yellow-200 text-4xl"></i>
            <?php endif; ?>
          </div>
          <div class="p-4">
            <h3 class="font-bold text-gray-800 text-sm mb-1"><?= htmlspecialchars($p['title']) ?></h3>
            <p class="text-xs text-yellow-600 font-semibold mb-1"><?= htmlspecialchars($p['domain_name'] ?? '') ?></p>
            <p class="text-xs text-gray-400 line-clamp-2 mb-3"><?= htmlspecialchars($p['description'] ?? '') ?></p>
            <div class="flex gap-3 text-xs">
              <?php if($p['project_url']): ?>
                <a href="<?= htmlspecialchars($p['project_url']) ?>" target="_blank" class="text-yellow-600 hover:underline font-semibold">View →</a>
              <?php endif; ?>
              <a href="edit_project.php?id=<?= $p['id'] ?>" class="text-blue-500 hover:underline">Edit</a>
              <a href="delete_project.php?id=<?= $p['id'] ?>" onclick="return confirm('Delete?')" class="text-red-500 hover:underline">Delete</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== TAB: APPLICATIONS ===== -->
  <div id="tab-apps" class="tab-content">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
      <h2 class="font-extrabold text-gray-700 mb-5">My Applications</h2>
      <?php if(empty($my_apps)): ?>
        <p class="text-gray-400 text-sm text-center py-8">No applications yet. Browse jobs in the feed!</p>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach($my_apps as $app): ?>
          <div class="flex items-center justify-between p-4 border border-gray-100 rounded-xl">
            <div>
              <h3 class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($app['title']) ?></h3>
              <p class="text-xs text-gray-400 mt-0.5">
                <?= htmlspecialchars($app['domain_name'] ?? '') ?> · Proposed: $<?= number_format($app['proposed_rate']) ?>
                · <?= date('M d, Y', strtotime($app['applied_at'])) ?>
              </p>
            </div>
            <span class="text-xs font-bold px-3 py-1 rounded-full <?=
              $app['status']==='accepted'?'bg-green-100 text-green-700':
              ($app['status']==='rejected'?'bg-red-100 text-red-600':'bg-yellow-100 text-yellow-700') ?>">
              <?= ucfirst($app['status']) ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

 

  <?php else: // CLIENT ?>

  <!-- ===== TAB: MY JOB PROPOSALS (CLIENT) ===== -->
  <div id="tab-proposals" class="tab-content">
    <div class="flex justify-between items-center mb-4">
      <h2 class="font-extrabold text-gray-700">My Job Proposals</h2>
      <a href="post_job.php" class="px-4 py-2 bg-yellow-600 text-white text-sm font-bold rounded-xl hover:bg-yellow-700 transition">
        <i class="fa-solid fa-plus mr-1"></i>Post New Job
      </a>
    </div>
    <?php if(empty($my_proposals)): ?>
      <p class="text-gray-400 text-sm text-center py-10">No jobs posted yet.</p>
    <?php else: ?>
      <div class="space-y-5">
        <?php foreach($my_proposals as $jp):
          $apps_s = $pdo->prepare("
            SELECT ja.*, u.first_name, u.last_name, u.avatar, fp.title as fp_title, fp.hourly_rate
            FROM job_applications ja
            JOIN freelancer_profiles fp ON ja.freelancer_id = fp.id
            JOIN users u ON fp.user_id = u.id
            WHERE ja.job_id = ? ORDER BY ja.applied_at DESC
          ");
          $apps_s->execute([$jp['id']]);
          $apps = $apps_s->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
          <div class="flex justify-between items-start mb-2">
            <div>
              <h3 class="font-bold text-gray-800"><?= htmlspecialchars($jp['title']) ?></h3>
              <p class="text-xs text-yellow-600 font-semibold"><?= htmlspecialchars($jp['domain_name'] ?? '') ?></p>
            </div>
            <span class="text-xs font-bold px-3 py-1 rounded-full bg-green-100 text-green-700"><?= ucfirst($jp['status']) ?></span>
          </div>
          <p class="text-sm text-gray-500 mb-2">Budget: $<?= number_format($jp['budget_min']) ?> – $<?= number_format($jp['budget_max']) ?> (<?= $jp['budget_type'] ?>)</p>
          <p class="text-xs text-gray-400 mb-4"><?= count($apps) ?> application(s)</p>

          <?php if(!empty($apps)): ?>
          <div class="border-t pt-4 space-y-3">
            <?php foreach($apps as $app): ?>
            <div class="bg-gray-50 rounded-xl p-3 flex items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-yellow-300 overflow-hidden flex items-center justify-center flex-shrink-0">
                  <?php if($app['avatar']): ?>
                    <img src="../uploads/avatars/<?= htmlspecialchars($app['avatar']) ?>" class="w-full h-full object-cover">
                  <?php else: ?>
                    <span class="text-white font-bold text-xs"><?= strtoupper(substr($app['first_name'],0,1).substr($app['last_name'],0,1)) ?></span>
                  <?php endif; ?>
                </div>
                <div>
                  <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($app['first_name'].' '.$app['last_name']) ?></p>
                  <p class="text-xs text-gray-500"><?= htmlspecialchars($app['fp_title'] ?? '') ?> · $<?= $app['hourly_rate'] ?>/hr · Proposed: $<?= number_format($app['proposed_rate']) ?></p>
                  <p class="text-xs text-gray-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($app['cover_letter'] ?? '') ?></p>
                </div>
              </div>
              <div class="flex flex-col items-end gap-2 flex-shrink-0">
                <span class="text-xs font-bold px-2 py-0.5 rounded-full <?=
                  $app['status']==='accepted'?'bg-green-100 text-green-700':
                  ($app['status']==='rejected'?'bg-red-100 text-red-600':'bg-yellow-100 text-yellow-700') ?>">
                  <?= ucfirst($app['status']) ?>
                </span>
                <?php if($app['status']==='pending'): ?>
                <form method="POST" action="handle_application.php" class="flex gap-1">
                  <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                  <button name="action" value="accept" class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700">Accept</button>
                  <button name="action" value="reject" class="text-xs bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600">Reject</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); });
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

function previewAvatar(input) {
  const preview = document.getElementById('avatar-preview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
    reader.readAsDataURL(input.files[0]);
  }
}

// Skills
const container = document.getElementById('skills-container');
const countEl   = document.getElementById('skill-count');

function updateCount() {
  if(!countEl) return;
  const n = container.querySelectorAll('input[type=checkbox]:checked').length;
  countEl.textContent = n + '/15';
}

document.querySelectorAll('.skill-cb').forEach(cb => cb.addEventListener('change', updateCount));

document.querySelectorAll('.remove-custom').forEach(btn => {
  btn.addEventListener('click', () => { btn.closest('label').remove(); updateCount(); });
});

document.getElementById('add-skill-btn')?.addEventListener('click', addCustomSkill);
document.getElementById('custom-skill')?.addEventListener('keypress', e => { if(e.key==='Enter'){ e.preventDefault(); addCustomSkill(); } });

function addCustomSkill() {
  const input = document.getElementById('custom-skill');
  const name  = input.value.trim();
  if(!name || name.length > 30) return;
  const exists = [...container.querySelectorAll('input[type=checkbox]')].some(cb => cb.value.toLowerCase()===name.toLowerCase());
  if(exists) { input.value=''; return; }
  if(container.querySelectorAll('input[type=checkbox]:checked').length >= 15) { alert('Max 15 skills'); return; }
  const label = document.createElement('label');
  label.className = 'cursor-pointer custom-skill-label';
  label.innerHTML = `<input type="checkbox" name="skills[]" value="${name}" checked class="peer sr-only skill-cb">
    <div class="px-4 py-2 rounded-full border-2 border-yellow-600 bg-yellow-600 text-white text-sm font-medium flex items-center gap-1">
      ${name} <span class="remove-custom ml-1 hover:text-red-200 cursor-pointer">×</span>
    </div>`;
  label.querySelector('.remove-custom').addEventListener('click', () => { label.remove(); updateCount(); });
  label.querySelector('.skill-cb').addEventListener('change', updateCount);
  container.appendChild(label);
  input.value = '';
  updateCount();
}

updateCount();
</script>
</body>
</html>