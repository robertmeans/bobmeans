<?php 
require_once 'config/initialize.php'; 
$layout_context = 'auth'; /* this is what keeps bkg-color #fff on login */

require '_includes/header.php'; 
?>
<div class="logincon">
  <div class="landing">
    <div class="msg">
      <p class="titlemsg">Bob's Budget Buffer</p>
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
