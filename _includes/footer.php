  <footer>
    
  </footer>

  </div><?php /* .main-wrap close */ ?>

<?php require '_includes/app_modal.php'; ?>

<?php 
if (!isset($_SESSION['loggedin'])) { ?>
  <script src="_scripts/auth-scripts.js?<?php echo time(); ?>"></script> 
<?php } ?>

  <script src="_scripts/scripts.js?<?php echo time(); ?>"></script>

<?php 
  if (isset($is_modal_page) && modal_page($layout_context, $is_modal_page)) { 
  require '_includes/app_modal.php';
  ?>
  <script src="_scripts/app-modal.js"></script>
<?php } /* array is in config/initialize.php */ ?>

<?php if (WWW === 'dev') { ?>
  <script src="http://localhost:35729/livereload.js"></script>
<?php } ?>


</body>
</html>