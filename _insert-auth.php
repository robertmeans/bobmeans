<?php 
require_once 'config/initialize.php'; 
require '_includes/header.php'; 
?>
<div class="logincon">
  <div class="landing">
    <div class="msg">
      <p class="titlemsg">Bob's Big Fat Project</p>
      <p>I'm using this as a place to work on a big fat project. Create an account, login, discover how everything breaks and doesn't work and changes and exists as a mystery as to what on earth I'm trying to accomplish. One day it may look like something is coming into focus and the next day I will burn it to the ground. Meanwhile, something's brewing. There really is a project underway here.</p>
    </div>

    <div id="authcon">

    <?php      
    if (isset($_SESSION['reset-token'])) {
      require '_insert-resetpass.php';
    } else {
      require '_insert-login.php'; 
    }
    ?>

    </div><!-- #authcon -->
  </div><!-- landing -->
</div><!-- logincon -->

<?php require '_includes/footer.php'; ?>
