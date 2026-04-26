<?php

function show_login() {
  if (!isset($_SESSION['verified']) || isset($_SESSION['login-hold'])) {
    return true;
  } else { 
    return false; 
  }  
}



function bob() { /* President = 99 | There should be only 1 President */
  if (isset($_SESSION['role']) && $_SESSION['role'] == 99) {
    return true;
  } else { return false; }
}

function u($string="") {
  return urlencode($string);
}

function h($string="") {
  return htmlspecialchars($string ?? '');
}

function is_post_request() {
  return $_SERVER['REQUEST_METHOD'] == 'POST';
}

function is_get_request() {
  return $_SERVER['REQUEST_METHOD'] == 'GET';
}

function display_errors($errors=array()) {
  $output = '';
  if(!empty($errors)) {
    $output .= "<div class=\"errors\">";
    $output .= "Please fix the following errors:";
    $output .= "<ul>";
    foreach($errors as $error) {
      $output .= "<li>" . h($error) . "</li>";
    }
    $output .= "</ul>";
    $output .= "</div>";
  }
  return $output;
}