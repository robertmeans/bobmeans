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

verify_loggedin();

/* we're logged in! */
require 'homepage.php'; 

