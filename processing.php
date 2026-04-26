<?php
require_once 'config/initialize.php';

if (is_post_request()) { /* closes at very btm of page */


/* BEGIN: sign up */
if (isset($_POST['sign_up_process_routine'])) {

  require_once 'controllers/emailController.php';

  $signal = '';
  $msg = '';
  $li = '';
  $class = '';
  $password_txt = '';
  $msg_txt = '';

  if (isset($_POST['signup'])) {
    $username = trim($_POST['username'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConf = $_POST['passwordConf'] ?? '';

    if (WWW === 'dev') { usleep($t); }

    $addError = function ($text) use (&$signal, &$msg, &$li, &$class) {
      $signal = 'bad';
      $msg = '<span class="login-txt"><img src="_images/try-again.png"></span>';
      $li .= "<li>{$text}</li>";
      $class = 'red';
    };

    if ($username === '') {
      $addError('Please enter a username');
    }

    if ($username !== '' && strlen($username) > 16) {
      $addError('Keep Username 16 characters or less');
    }

    if ($username !== '' && strpos($username, ',') !== false) {
      $addError('Sorry, you can\'t have a comma in your Username.');
    }

    if ($email === '') {
      $addError('Email required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $addError('Email is invalid');
    }

    if ($password === '') {
      $addError('Password required');
    } else {
      if (strlen($password) <= 3) {
          $addError('Password needs at least 4 characters');
      }

      if (strlen($password) > 50) {
          $addError('Keep your password under 50 characters');
      }

      if ($passwordConf === '') {
          $addError('Confirm password');
      } elseif ($password !== $passwordConf) {
          $addError('Passwords don\'t match. (Note: Passwords are case sensitive.)');
      }
    }

    if ($password === '' && $passwordConf !== '') {
      $addError('Slow down - Type same password in both fields');
    }


    /* in case you ever want to prevent multiple usernames that are the same */
    /*
    if ($li === '') {
      $stmt = $pdo_db->prepare("SELECT user_id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
      $stmt->execute([$username]);

      if ($stmt->fetch()) {
        $addError('That username is already taken');
      }
    }
    */

    if ($li === '') {
      $stmt = $pdo_db->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
      $stmt->execute([$email]);

      if ($stmt->fetch()) {
        $addError('Email already exists');
      }
    }

    if ($li === '') {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $token = bin2hex(random_bytes(50));
      $verified = 0;

      try {
        $stmt = $pdo_db->prepare("
          INSERT INTO users (username, email, verified, token, password)
          VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $verified, $token, $hashedPassword]);

        if (WWW !== 'dev') {
          sendVerificationEmail($username, $email, $token);
        } else {
          usleep($t);
        }

        $signal = 'ok';

      } catch (PDOException $e) {
        $addError('Database error: failed to register. Please try again later. There are issues on the server that are being worked on.');
      }
    }
  }

  echo json_encode([
      'signal' => $signal,
      'msg' => $msg,
      'li' => $li,
      'class' => $class,
      'password_txt' => $password_txt,
      'msg_txt' => $msg_txt
  ]);
} /* 'sign_up_process_routine' */




/* BEGIN Key: 'login_routine' */
if (isset($_POST['login_routine'])) {

  $msg = '';
  $li = '';
  $class = '';
  $password_txt = '';
  $msg_txt = '';
  $count = '';

  if (isset($_POST['login'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    if (WWW === 'dev') { usleep($t); }

    // validation
    if (empty($username)) {
      $signal = 'bad';
      $msg = '<span class="login-txt"><img src="_images/try-again.png"></span>';
      $li .= '<li class="no-count">Username or email required</li>';
      $class = 'red';
    }

    if (empty($password)) {
      $signal = 'bad';
      $msg = '<span class="login-txt"><img src="_images/try-again.png"></span>';
      $li .= '<li class="no-count">Please enter your password</li>';
      $class = 'red';
    }

    if ($li === '') {

      // Check for duplicate usernames first
      $stmt = $pdo_db->prepare("
          SELECT user_id
          FROM users
          WHERE LOWER(username) = LOWER(?)
          LIMIT 2
      ");
      $stmt->execute([$username]);
      $matchingUsers = $stmt->fetchAll();

      if (count($matchingUsers) > 1) {
          $signal = 'bad';
          $msg = '<span class="login-txt"><img src="_images/try-again.png"></span>';
          $li .= '<li class="no-count">There are multiple users with the username "' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '". Please use your email address to login.</li>';
          $class = 'orange';
      } else {

          // Look up by either email or username
          $stmt = $pdo_db->prepare("
              SELECT user_id, username, role, email, token, password, verified
              FROM users
              WHERE LOWER(email) = LOWER(?)
                 OR LOWER(username) = LOWER(?)
              LIMIT 1
          ");
          $stmt->execute([$username, $username]);
          $user = $stmt->fetch();

          if (!$user) {
              $signal = 'bad';
              $msg = '<span class="login-txt"><img src="_images/try-again.png"></span>';
              $li .= '<li class="no-count">That user does not exist</li>';
              $class = 'red';

          } elseif (!password_verify($password, $user['password'])) {
              $signal = 'bad';
              $msg = '<span class="login-txt"><img src="_images/try-again.png"></span>';
              $li .= '<li class="count">Wrong Username/Password combination. (Note: Passwords are case sensitive.)</li>';
              $class = 'red';
              $count = 'on';

          } else {
            // Login success
            // NOTE: you're setting session vars before verified = 1 (maybe worth attention)
            $_SESSION['id']       = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['email']    = $user['email'];
            $_SESSION['verified'] = $user['verified'];
            $_SESSION['token']    = $user['token'];

            unset($_SESSION['login-hold']); /* prevents login on refresh if just verified */

            if ((int)$user['verified'] === 0) {
              $signal = 'bad';
              $msg = '<span class="login-txt"><img src="_images/login.png"></span>';
              $li .= '<li class="no-count">Email has not been verified</li>';
              $class = 'blue';
            } else {
            if (isset($_POST['remember_me'])) { 
              $token = $user['token']; 
              setcookie('token', $token, time() + (1825 * 24 * 60 * 60), '/'); 
            }

              /* local testing */
              if (WWW === 'dev') {
                usleep($t);
              }

              $signal = 'ok';
          }
        }
      }
    }
  }
  $data = array(
    'signal' => $signal,
    'msg' => $msg,
    'li' => $li,
    'class' => $class,
    'password_txt' => $password_txt,
    'msg_txt' => $msg_txt,
    'count' => $count
  );
  echo json_encode($data);

} /* 'login_routine' */




/* BEGIN: forgot password */
if (isset($_POST['forgot_password_routine'])) {

    require_once 'controllers/emailController.php';

    $signal = '';
    $msg = '';
    $li = '';
    $class = '';

    if (isset($_POST['forgotpass'])) {

      $email = strtolower(trim($_POST['email'] ?? ''));

      $addError = function ($text) use (&$signal, &$msg, &$li, &$class) {
        $signal = 'bad';
        $msg = '<span class="login-txt">Try again</span>';
        $li .= "<li>{$text}</li>";
        $class = 'red';
      };

      if ($email === '') {
        usleep($t);
        $addError('Please enter the email address you used to create an account here and I\'ll send you a reset link.');
      } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        usleep($t);
        $addError('Email is invalid');
      } else {
        $stmt = $pdo_db->prepare("
          SELECT username, token
          FROM users
          WHERE LOWER(email) = LOWER(?)
          LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
          if (WWW === 'pro') {
            sendPasswordResetLink($user['username'], $email, $user['token']);
          } else {
            usleep($t);
          }

          $signal = 'ok';
          $msg = '<span class="login-txt">Help on the way!</span>';
          $li .= '<div class="successmessage">Please check your email (can take a minute or two).<br><br><a class="log-form">Show login</a></div>';
          $class = 'green';
        } else {
          if (WWW === 'dev') { usleep($t); }
          $addError('There is no user here with that email address.');
        }
      }
    }

  echo json_encode([
      'signal' => $signal,
      'msg' => $msg,
      'li' => $li,
      'class' => $class
  ]);
} /* 'forgot_password_routine' */










/* BEGIN: reset password */
if (isset($_POST['reset_password_routine'])) {

  $signal = '';
  $msg = '';
  $li = '';
  $class = '';

  if (isset($_POST['resetpass'])) {

    $password = $_POST['password'] ?? '';
    $passwordConf = $_POST['passwordConf'] ?? '';

    $addError = function ($text, $errorMsg = null, $errorClass = 'red') use (&$signal, &$msg, &$li, &$class) {
      $signal = 'bad';
      $msg = $errorMsg ?? '<span class="login-txt"><img src="_images/try-again.png"></span>';
      $li .= "<li>{$text}</li>";
      $class = $errorClass;
    };

    if ($password === '' || $passwordConf === '') {
      $addError('Password required');
    }

    if ($password !== '' && strlen($password) <= 3) {
      $addError('Password needs at least 4 characters');
    }

    if ($password !== '' && strlen($password) > 50) {
      $addError('Keep your password under 50 characters');
    }

    if ($password !== '' && $passwordConf !== '' && $password !== $passwordConf) {
      $addError('Passwords don\'t match. Note: passwords are case sensitive.');
    }

    if ($li === '') {
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $email = strtolower($_SESSION['email'] ?? '');
      $resetToken = $_SESSION['reset-token'] ?? null;

      if ($email === '' || !$resetToken) {
        $addError('Your reset session is missing or expired. Please start over.');
      } else {
        try {
          $newToken = bin2hex(random_bytes(50));

          $stmt = $pdo_db->prepare("
            UPDATE users
            SET password = ?, token = ?
            WHERE LOWER(email) = LOWER(?)
              AND token = ?
            LIMIT 1
          ");
          $stmt->execute([$hashedPassword, $newToken, $email, $resetToken]);

          if ($stmt->rowCount() < 1) {
            $addError('Your reset session is invalid or expired. Please start over.');
          } else {
            unset($_SESSION['reset-token']);

            $_SESSION['login-message'] = "Your password was changed successfully. You can now login with your new credentials.";
            $_SESSION['alert-class'] = "green";

            $signal = 'ok';
          }

        } catch (PDOException $e) {
            $signal = 'bad';
            $msg = '<span>No bueno</span>';
            $li .= '<li>There\'s been a problem</li>';
            $class = 'red';
        }
      }
    }
  }

  echo json_encode([
      'signal' => $signal,
      'msg' => $msg,
      'li' => $li,
      'class' => $class
  ]);
} /* 'reset_password_routine' */














}