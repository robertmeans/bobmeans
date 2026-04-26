<?php

/* note: this is a $_GET bc it's not being posted via a form */
if (isset($_GET['filename_of_errors'])) {
  $filename = $_GET['filename_of_errors'];

  if (file_exists($filename) && filesize($filename) > 0) {
    echo "File is not empty";
  } else {
    echo "File is empty or does not exist";
  }
  exit;
}

/* to prevent 'Headers already sent' error, initialize.php needs to be down here */
require_once 'config/initialize.php';

if (is_post_request()) { /* closes at very bottom of page */

/* BEGIN: reset errors (me only) */
if (isset($_POST['process_reset_errors'])) {
  $filename = "_errors.txt";
  if (unlink($filename)) {
    echo 'ok';
    } else {
      echo 'nope';
    }
  }
}