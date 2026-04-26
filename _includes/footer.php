<footer>
  
</footer>

<?php 
if (!isset($_SESSION['loggedin'])) { ?>
<script src="_scripts/auth-scripts.js?<?php echo time(); ?>"></script> 
<?php } ?>
<script src="_scripts/scripts.js?<?php echo time(); ?>"></script>
<?php if (WWW === 'dev') { ?>
<script src="http://localhost:35729/livereload.js"></script>
<?php } ?>

</body>
</html>