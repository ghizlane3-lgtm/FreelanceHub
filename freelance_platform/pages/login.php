<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// Already logged in → feed
if (isLoggedIn()) {
    header('Location: feed.php');
    exit;
}

$error = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT id, first_name, last_name, password_hash, role FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . '' . $user['last_name'];

            header('Location: feed.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
            $old_email = htmlspecialchars($email);
        }
    }
}

$title = 'Login — FreelanceHub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous"/>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4">

<div class="w-full max-w-md">
  <div class="text-center mb-8">
    <a href="../home.php" class="inline-flex items-center gap-3">
      <div class="w-12 h-12 bg-yellow-600 rounded-full flex items-center justify-center shadow-md">
        <i class="fa-solid fa-briefcase text-white text-xl"></i>
      </div>
      <span class="text-3xl font-extrabold text-yellow-700">FreelanceHub</span>
    </a>
  </div>

  <div class="bg-white rounded-2xl shadow-xl border-t-4 border-yellow-500 p-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-1">Welcome back</h1>
    <p class="text-gray-400 text-sm mb-6">Login to your account</p>

    <?php if ($error): ?>
    <div class="mb-5 flex items-center gap-3 p-3 bg-red-50 border-red-200 text-red-600 rounded-lg text-sm">
      <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="space-y-4">
      <div>
        <label class="block text-sm font-semibold text-gray-600 mb-1">Email</label>
        <div class="flex items-center border-gray-200 rounded-lg px-3 focus-within:ring-2 focus-within:ring-yellow-400 bg-white">
          <i class="fa-solid fa-envelope text-gray-300 mr-3"></i>
          <input type="email" name="email" value="<?= $old_email ?>" placeholder="you@example.com" required class="flex-1 py-3 outline-none text-sm bg-transparent text-gray-700">
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-600 mb-1">Password</label>
        <div class="flex items-center border-gray-200 rounded-lg px-3 focus-within:ring-2 focus-within:ring-yellow-400 bg-white">
          <i class="fa-solid fa-lock text-gray-300 mr-3"></i>
          <input type="password" name="password" id="password" placeholder="••••" required class="flex-1 py-3 outline-none text-sm bg-transparent text-gray-700">
          <i id="eye-open" class="fa-solid fa-eye text-gray-300 cursor-pointer hover:text-gray-500" onclick="togglePw()"></i>
          <i id="eye-closed" class="fa-solid fa-eye-slash text-gray-300 cursor-pointer hover:text-gray-500 hidden" onclick="togglePw()"></i>
        </div>
      </div>

      <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 rounded-lg transition text-sm mt-2 shadow-md">Login</button>
    </form>

    <div class="flex items-center my-6 gap-3">
      <div class="flex-1 border-t border-gray-100"></div>
      <span class="text-xs text-gray-400">or</span>
      <div class="flex-1 border-t border-gray-100"></div>
    </div>

    <p class="text-center text-sm text-gray-500">
      Don't have an account?
      <a href="../home.php?show_modal=1" class="text-yellow-600 font-bold hover:underline ml-1">Sign up free</a>
    </p>
  </div>

  <p class="text-center mt-6">
    <a href="../home.php" class="text-sm text-gray-400 hover:text-gray-600">
      <i class="fa-solid fa-arrow-left mr-1"></i> Back to homepage
    </a>
  </p>
</div>

<script>
function togglePw() {
  const pw = document.getElementById('password');
  const open = document.getElementById('eye-open');
  const closed = document.getElementById('eye-closed');
  if (pw.type === 'password') {
    pw.type = 'text';
    open.classList.add('hidden');
    closed.classList.remove('hidden');
  } else {
    pw.type = 'password';
    open.classList.remove('hidden');
    closed.classList.add('hidden');
  }
}
</script>
</body>
</html>