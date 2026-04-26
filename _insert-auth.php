<?php 
require_once 'config/initialize.php'; 
require '_includes/header.php'; 

require '_includes/header-auth.php'; 

if (isset($_SESSION['reset-token'])) {
  require '_insert-resetpass.php';
} else {
  require '_insert-login.php'; 
}

require '_includes/footer-auth.php'; 

