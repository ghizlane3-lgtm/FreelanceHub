<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title ?? 'FreeLance Platform') ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar">
  <a href="/home.php" class="logo">FreeLance</a>
  <div class="nav-links">
  <?php if (isLoggedIn()): ?>
    <a href="/pages/feed.php">Feed</a>
    <a href="/pages/profile.php">My Profile</a>
    <?php if (isClient()): ?>
      <a href="/pages/post_job.php">Post a Job</a>
    <?php endif; ?>
    <a href="/pages/logout.php">Logout</a>
  <?php else: ?>
    <a href="/pages/login.php">Login</a>
    <a href="/pages/register.php" class="btn-primary">Sign Up</a>
  <?php endif; ?>
  </div>
</nav>
