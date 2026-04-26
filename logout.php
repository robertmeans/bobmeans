<?php
require_once 'config/initialize.php';

class Util {       
  public function clearAuthCookie() {
    if (isset($_COOKIE['token'])) {
      setcookie('token', '', time() - 3600, '/');
      unset($_COOKIE['token']);
    }
  }
}

$util = new Util();

// Clear session
$_SESSION = [];
session_unset();
session_destroy();

// Clear remember-me cookie
$util->clearAuthCookie();

header('Location:' . WWW_ROOT);
exit();
?>