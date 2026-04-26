<?php
require_once 'config/initialize.php';

if (isset($_GET['token'])) {
  $token = $_GET['token'];
  verifyUser($token);
}

if (isset($_GET['reset-token'])) {
  $resetToken = $_GET['reset-token'];
  resetPassword($resetToken);
}

if (show_login()) {
  require '_includes/header.php';
  require '_insert-auth.php';
  require '_includes/footer.php';
  exit;
}

/* we're logged in! */
require '_includes/header.php';
require '_includes/nav.php'; ?>

<div class="hmmsg">
  <a href="logout.php">Logout</a>
</div>

<?php require '_includes/footer.php'; ?>