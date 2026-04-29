<?php if (file_exists("_errors.txt")) { $fileExists = true; } else { $fileExists = false; } ?>

<nav class="navigation">

  <?php if (bob() && ($fileExists && filesize("_errors.txt") > 0)) { ?>

    <div class="phperror-tab on" data-role="error-notification">
      <a class="per" href="_errors.txt" target="_blank"><i class="fas far fa-exclamation-circle"></i></a>
    </div>

   <?php } else { ?>

    <div class="phperror-tab" data-role="error-notification">&nbsp;</div>

   <?php } ?>

  <div class="top-nav">
    <div class="menu-basket">
      <div class="bar-box">
        <span class="bars"></span>
      </div>
    </div>
  </div>

</nav>
<div id="side-nav-bkg">
  <div id="side-nav" class="sidenav">

    <div id="sidenav-wrapper">

      <a href="logout.php" class="logout" onclick="closeNav();"><i class="fas far fa-power-off"></i> Logout</a>

      <?php if (bob()) { ?>
        <?php if ($fileExists && filesize("_errors.txt") > 0) { ?>
          <div class="phperror-link on" data-role="error-reset">
            <i class="far fas fa-minus-circle"></i> Reset Errors
          </div>
        <?php } else { ?>
          <div class="phperror-link" data-role="error-reset">&nbsp;</div>
        <?php } ?>
      <?php } ?>

    </div>

  </div>
</div>

<?php /* for my eyes only - error reporting */ 
if (bob()) { ?>
  <script> 
    var error_location = "_errors.txt";

    function checkFileExists(error_location) {

      $.ajax({
        url: "_error-checking.php",
        data: { filename_of_errors: "_errors.txt" }, // Replace with your actual filename
        success: function(response) {
          if (response === "File is not empty") {

            /* turn everything on */
            $(".phperror-tab").addClass("on");
            $(".phperror-tab").html('<a class="per" href="_errors.txt" target="_blank"><i class="fas far fa-exclamation-circle"></i></a>');

            $(".phperror-link").addClass("on");
            $(".phperror-link").html('<i class="far fas fa-minus-circle"></i> Reset Errors');

          }
        }
      });
    }

    $(document).ready(function() {
      setInterval(function() {
        checkFileExists(error_location);
        // console.log('yo');
      }, 3000);

      $('#side-nav').on('click', '[data-role="error-reset"]', function(e) { 
        // console.log('you clicked error-reset');
        e.preventDefault();
        e.stopPropagation();

        $.ajax({
          dataType: "JSON",
          url: "_error-checking.php",
          type: "POST",
          data: {
            process_reset_errors: 'key'
          },
          success: function(response) {
            if(response) {

              if(response['signal'] == 'ok') {
                //alert('yoyoyoyoyo');
                $(".phperror-tab").removeClass("on");
                $(".phperror-tab").html('&nbsp;');

                $(".phperror-link").removeClass("on");
                $(".phperror-link").html('&nbsp;');

              } else {
                //console.log('file not deleted');
              }
            } 
          },
          error: function() {

          }, 
          complete: function() {

          }
        });
      });    
    });
  </script>
<?php } ?>