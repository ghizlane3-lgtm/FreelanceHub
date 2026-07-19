
<?php
session_start();
$title = "FreelanceHub — Find Talent or Work";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.10/dist/dotlottie-wc.js" type="module"></script>
  <style>
    html { scroll-behavior: smooth; }
    .hero-gradient { background: linear-gradient(135deg, #ca8a04 0%, #f59e0b 50%, #fde68a 100%); }
    .card-hover { transition: all 0.3s ease; }
    .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(202,138,4,0.25); }
    .step-card { background: linear-gradient(135deg, #f59e0b, #ca8a04); }
    .step-card:hover { background: linear-gradient(135deg, #ca8a04, #f59e0b); }
    @keyframes fadeInUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
    .fade-in { animation: fadeInUp 0.6s ease both; }
    .fade-in-2 { animation: fadeInUp 0.6s ease 0.15s both; }
    .fade-in-3 { animation: fadeInUp 0.6s ease 0.3s both; }
  </style>
</head>
<body class="bg-gray-50 font-sans">

<nav class="fixed top-0 w-full z-40 bg-yellow-400 shadow-md">
  <div class="max-w-6xl mx-auto px-6 py-4 flex justify-between items-center">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-yellow-700 rounded-full flex items-center justify-center">
        <!-- <i class="fa-solid fa-briefcase text-white text-lg"></i> -->
         <img src="freelane-logo.png" alt="logo" class="bg-yellow-400">
      </div>
      <span class="text-2xl font-extrabold text-yellow-800 tracking-tight">FreelanceHub</span>
    </div>
    <div class="flex items-center gap-3">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="pages/feed.php" class="px-4 py-2 text-yellow-800 font-semibold hover:text-yellow-900">Feed</a>
        <a href="pages/profile.php" class="px-5 py-2 bg-yellow-700 text-white font-bold rounded-full hover:bg-yellow-800 transition">My Profile</a>
      <?php else: ?>
        <a href="pages/login.php" class="px-5 py-2 text-yellow-800 font-semibold hover:underline">Login</a>
        <button onclick="toggleModal()" class="px-5 py-2 bg-yellow-700 text-white font-bold rounded-full hover:bg-yellow-800 transition shadow-md">Join Now</button>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ========== HERO ========== -->
<section class="hero-gradient pt-32 pb-16 px-6">
  <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center gap-10">

    <!-- Left: text + toggle -->
    <div class="flex-1 fade-in">
      <h1 class="text-5xl font-extrabold text-white leading-tight mb-4">
        Where your Dream <br><span class="text-yellow-900">becomes Reality</span>
      </h1>
      <p class="text-yellow-100 text-lg mb-8">A platform built for freelancers, by a freelancer.</p>

      <!-- Role toggle -->
      <div class="flex items-center p-1 bg-yellow-200 rounded-full w-60 relative mb-6 shadow-inner">
        <div id="toggle-bg" class="absolute left-1 w-[112px] h-9 bg-yellow-600 rounded-full transition-transform duration-300 ease-in-out shadow-md"></div>
        <button onclick="switchRole('client')" id="client-btn"
          class="relative z-10 w-1/2 py-2 text-sm font-bold text-white transition-colors duration-300">
          Client
        </button>
        <button onclick="switchRole('freelancer')" id="freelancer-btn"
          class="relative z-10 w-1/2 py-2 text-sm font-bold text-yellow-700 transition-colors duration-300">
          Freelancer
        </button>
      </div>

      <!-- Dynamic text -->
      <div id="hero-text-client" class="text-yellow-50 text-base leading-relaxed">
        Post a job, review proposals, and hire the best talent for your project — all in one place.
      </div>
      <div id="hero-text-freelancer" class="text-yellow-50 text-base leading-relaxed hidden">
        Browse hundreds of job offers matching your skills, apply in one click, and grow your career.
      </div>

      <button onclick="toggleModal()" class="mt-8 px-8 py-3 bg-yellow-900 text-white font-bold rounded-full hover:bg-yellow-800 transition shadow-lg text-lg">
        Get Started — It's Free
      </button>
    </div>

    <!-- Right: lottie -->
    <div class="flex-1 flex justify-center fade-in-2">
      <dotlottie-wc
        src="https://lottie.host/15400161-8f84-42dc-aac4-e5e3ea9ce5f4/VBCtAE2GYh.lottie"
        style="width:360px;height:360px" autoplay loop>
      </dotlottie-wc>
    </div>

  </div>
</section>

<!-- ========== STATS BAR ========== -->
<div class="bg-yellow-700 text-white py-6">
  <div class="max-w-6xl mx-auto grid grid-cols-3 text-center gap-6 px-6">
    <div><p class="text-3xl font-extrabold">1,200+</p><p class="text-yellow-200 text-sm mt-1">Freelancers</p></div>
    <div><p class="text-3xl font-extrabold">840+</p><p class="text-yellow-200 text-sm mt-1">Jobs Posted</p></div>
    <div><p class="text-3xl font-extrabold">98%</p><p class="text-yellow-200 text-sm mt-1">Satisfaction Rate</p></div>
  </div>
</div>

<!-- ========== CATEGORIES ========== -->
<section class="py-20 px-6 bg-white">
  <div class="max-w-6xl mx-auto">
    <h2 class="text-3xl font-extrabold text-center text-gray-800 mb-2">Browse by Category</h2>
    <p class="text-center text-gray-500 mb-12">Find the right talent for every domain</p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
      <?php
        $cats = [
          ['fa-code',         'Web Development'],
          ['fa-mobile-screen','Mobile Dev'],
          ['fa-pen-nib',      'UI / UX Design'],
          ['fa-video',        'Content Creation'],
          ['fa-house',        'Interior Design'],
          ['fa-robot',        'Artificial Intelligence'],
          ['fa-chart-line',   'Data Science'],
          ['fa-language',     'Translation'],
        ];
        foreach ($cats as $cat): ?>
        <div class="card-hover bg-gray-50 border border-gray-200 border-l-4 border-l-yellow-500 rounded-xl p-6 cursor-pointer">
          <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mb-3">
            <i class="fa-solid <?= $cat[0] ?> text-yellow-600 text-lg"></i>
          </div>
          <h3 class="font-bold text-gray-700 text-sm"><?= $cat[1] ?></h3>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ========== HOW IT WORKS ========== -->
<section class="py-20 px-6 bg-gray-50">
  <div class="max-w-6xl mx-auto">

    <!-- Client side -->
    <div id="client-side">
      <h2 class="text-3xl font-extrabold text-center text-gray-800 mb-2">How it works for Clients</h2>
      <p class="text-center text-gray-500 mb-12">Hire top freelancers in 3 simple steps</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php
          $clientSteps = [
            ['fa-file-lines', '01', 'Post a Job', 'Describe your project, set your budget and deadline.'],
            ['fa-users',      '02', 'Hire a Freelancer', 'Review proposals and pick the best profile.'],
            ['fa-circle-check','03', 'Receive Your Work', 'Approve the deliverable and release payment.'],
          ];
          foreach ($clientSteps as $s): ?>
          <div class="step-card card-hover rounded-2xl p-8 text-white shadow-md">
            <span class="text-yellow-200 text-sm font-bold"><?= $s[1] ?></span>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mt-3 mb-4">
              <i class="fa-solid <?= $s[0] ?> text-white text-xl"></i>
            </div>
            <h3 class="text-xl font-extrabold mb-2"><?= $s[2] ?></h3>
            <p class="text-yellow-100 text-sm leading-relaxed"><?= $s[3] ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="text-center mt-10">
        <p class="text-gray-600 mb-4 font-semibold">Ready to find your next freelancer?</p>
        <button onclick="toggleModal()" class="px-8 py-3 bg-yellow-600 text-white font-bold rounded-full hover:bg-yellow-700 transition shadow-md">
          Post a Job — It's Free
        </button>
      </div>
    </div>

    <!-- Freelancer side (hidden by default) -->
    <div id="freelancer-side" class="hidden">
      <h2 class="text-3xl font-extrabold text-center text-gray-800 mb-2">How it works for Freelancers</h2>
      <p class="text-center text-gray-500 mb-12">Build your career in 3 simple steps</p>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php
          $freSteps = [
            ['fa-magnifying-glass','01','Find a Job',     'Browse proposals filtered by your domain and budget.'],
            ['fa-paper-plane',     '02','Convince Client','Send a compelling application and stand out.'],
            ['fa-dollar-sign',     '03','Get Paid',       'Deliver great work and receive secure payment.'],
          ];
          foreach ($freSteps as $s): ?>
          <div class="step-card card-hover rounded-2xl p-8 text-white shadow-md">
            <span class="text-yellow-200 text-sm font-bold"><?= $s[1] ?></span>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mt-3 mb-4">
              <i class="fa-solid <?= $s[0] ?> text-white text-xl"></i>
            </div>
            <h3 class="text-xl font-extrabold mb-2"><?= $s[2] ?></h3>
            <p class="text-yellow-100 text-sm leading-relaxed"><?= $s[3] ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="text-center mt-10">
        <p class="text-gray-600 mb-4 font-semibold">Ready to start your freelance journey?</p>
        <button onclick="toggleModal()" class="px-8 py-3 bg-yellow-600 text-white font-bold rounded-full hover:bg-yellow-700 transition shadow-md">
          Sign Up — It's Free
        </button>
      </div>
    </div>

  </div>
</section>

<!-- ========== FOOTER ========== -->
<footer class="bg-gray-900 text-white py-12 px-6">
  <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-10">
    <div>
      <h3 class="text-lg font-bold mb-5 text-yellow-400">Contact</h3>
      <ul class="space-y-3">
        <li class="flex items-center gap-3 border border-gray-700 rounded-full px-4 py-2 hover:border-yellow-500 hover:bg-yellow-600/10 transition cursor-pointer">
          <i class="fa-solid fa-envelope text-yellow-400"></i> freelance@gmail.com
        </li>
        <li class="flex items-center gap-3 border border-gray-700 rounded-full px-4 py-2 hover:border-yellow-500 hover:bg-yellow-600/10 transition cursor-pointer">
          <i class="fa-solid fa-phone text-yellow-400"></i> +212 666 216 647
        </li>
      </ul>
    </div>
    <div>
      <h3 class="text-lg font-bold mb-5 text-yellow-400">Social Media</h3>
      <ul class="space-y-3">
        <li class="flex items-center gap-3 border border-gray-700 rounded-full px-4 py-2 hover:border-yellow-500 hover:bg-yellow-600/10 transition cursor-pointer">
          <i class="fa-brands fa-instagram text-yellow-400"></i> Instagram
        </li>
        <li class="flex items-center gap-3 border border-gray-700 rounded-full px-4 py-2 hover:border-yellow-500 hover:bg-yellow-600/10 transition cursor-pointer">
          <i class="fa-brands fa-facebook text-yellow-400"></i> Facebook
        </li>
      </ul>
    </div>
    <div>
      <h3 class="text-lg font-bold mb-5 text-yellow-400">Contributors</h3>
      <ul class="space-y-3">
        <li class="flex items-center gap-3 border border-gray-700 rounded-full px-4 py-2 hover:border-yellow-500 hover:bg-yellow-600/10 transition">
          <i class="fa-solid fa-user text-yellow-400"></i> Ghizlane Moulay
        </li>
      </ul>
    </div>
  </div>
  <div class="max-w-6xl mx-auto mt-10 pt-6 border-t border-gray-800 text-center text-gray-500 text-sm">
    &copy; <?= date('Y') ?> FreelanceHub. All rights reserved.
  </div>
</footer>

<!-- ========== REGISTER MODAL ========== -->

<div id="register-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center">
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="toggleModal()"></div>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-8 z-10 border-t-4 border-yellow-500">
    <button onclick="toggleModal()" class="absolute top-4 right-4 text-gray-400 hover:text-black transition">
      <i class="fa-solid fa-xmark text-lg"></i>
    </button>
    <h2 class="text-2xl font-bold mb-1">Join <span class="text-yellow-600">FreelanceHub</span></h2>
    <p class="text-gray-400 text-sm mb-6">Create your free account in seconds</p>

    <?php if (!empty($_GET['error'])): ?>
      <div class="mb-4 p-3 bg-red-50 border-red-200 text-red-600 rounded-lg text-sm">
        <?= htmlspecialchars($_GET['error']) ?>
      </div>
    <?php endif; ?>

    <form action="pages/register.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

      <div class="mb-4">
        <input type="text" name="first_name" placeholder="First name" required class="w-full border-gray-200 p-3 rounded-lg focus:ring-2 focus:ring-yellow-400 outline-none text-sm">
      </div>
      <div class="mb-4">
        <input type="text" name="last_name" placeholder="Last name" required class="w-full border-gray-200 p-3 rounded-lg focus:ring-2 focus:ring-yellow-400 outline-none text-sm">
      </div>
      <div class="mb-4">
        <input type="email" name="email" placeholder="Email address" required class="w-full border-gray-200 p-3 rounded-lg focus:ring-2 focus:ring-yellow-400 outline-none text-sm">
      </div>
      <div class="mb-4 flex items-center border-gray-200 rounded-lg px-3 focus-within:ring-2 focus-within:ring-yellow-400">
        <input type="password" name="password" placeholder="Password" id="modal-password" required class="flex-1 py-3 outline-none text-sm bg-transparent">
        <i id="eye-open" class="fa-solid fa-eye text-gray-400 cursor-pointer" onclick="togglePassword()"></i>
        <i id="eye-closed" class="fa-solid fa-eye-slash text-gray-400 cursor-pointer hidden" onclick="togglePassword()"></i>
      </div>
      <div class="mb-6 flex justify-around items-center border-gray-200 p-3 rounded-lg bg-gray-50">
        <span class="text-sm font-bold text-gray-600">Register as:</span>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" name="role" value="freelancer" class="accent-yellow-600" checked>
          <span class="text-sm font-medium">Freelancer</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" name="role" value="client" class="accent-yellow-600">
          <span class="text-sm font-medium">Client</span>
        </label>
      </div>
      <button type="submit" class="w-full bg-yellow-600 text-white font-bold py-3 rounded-lg hover:bg-yellow-700 transition text-sm">Create Account</button>
      <p class="text-center text-sm text-gray-400 mt-4">
        Already have an account? <a href="pages/login.php" class="text-yellow-600 font-semibold hover:underline">Login</a>
      </p>
    </form>
  </div>
</div>

<!-- JS identique... -->
<script>
function switchRole(role) {
  const bg = document.getElementById('toggle-bg');
  const clientBtn = document.getElementById('client-btn');
  const freBtn = document.getElementById('freelancer-btn');
  const clientSide = document.getElementById('client-side');
  const freSide = document.getElementById('freelancer-side');
  const heroClient = document.getElementById('hero-text-client');
  const heroFre = document.getElementById('hero-text-freelancer');

  if (role === 'freelancer') {
    bg.style.transform = 'translateX(112px)';
    freBtn.classList.replace('text-yellow-700','text-white');
    clientBtn.classList.replace('text-white','text-yellow-700');
    freSide.classList.remove('hidden');
    clientSide.classList.add('hidden');
    heroFre.classList.remove('hidden');
    heroClient.classList.add('hidden');
  } else {
    bg.style.transform = 'translateX(0)';
    clientBtn.classList.replace('text-yellow-700','text-white');
    freBtn.classList.replace('text-white','text-yellow-700');
    clientSide.classList.remove('hidden');
    freSide.classList.add('hidden');
    heroClient.classList.remove('hidden');
    heroFre.classList.add('hidden');
  }
}

function toggleModal() {
  const modal = document.getElementById('register-modal');
  modal.classList.toggle('hidden');
  document.body.style.overflow = modal.classList.contains('hidden') ? '' : 'hidden';
}

function togglePassword() {
  const pw = document.getElementById('modal-password');
  const open = document.getElementById('eye-open');
  const closed = document.getElementById('eye-closed');
  const isHidden = pw.type === 'password';
  pw.type = isHidden ? 'text' : 'password';
  open.classList.toggle('hidden', isHidden);
  closed.classList.toggle('hidden', !isHidden);
}

<?php if (!empty($_GET['show_modal'])): ?>
toggleModal();
<?php endif; ?>
</script>
</body>
</html>
