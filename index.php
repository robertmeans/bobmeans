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
  require '_insert-auth.php';
  exit;
}

/* we're logged in! */
require 'homepage.php'; 

