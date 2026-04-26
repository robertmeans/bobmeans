<?php if (file_exists("_errors.txt")) { $fileExists = true; } else { $fileExists = false; } ?>

<?php if (bob() && ($fileExists && filesize("_errors.txt") > 0)) { ?>
  <div class="phperror on" data-role="error-notification">
    <a class="per" href="_errors.txt" target="_blank"><i class="fas far fa-exclamation-circle"></i></a>
  </div>
 <?php } else { ?>
  <div class="phperror" data-role="error-notification"></div> 
<?php } ?>

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

            $(".phperror").addClass("on");
            $('div[data-role=error-notification]').html('<a class="per" href="_errors.txt" target="_blank"><i class="fas far fa-exclamation-circle"></i></a>');

            $("div[data-role=error-reset]").removeClass("err-off");
            $("div[data-role=error-reset]").addClass("err-on");
            $("div[data-role=error-reset]").html('<div class="del-err"><i class="far fas fa-minus-circle"></i> Reset Errors</div>');

            console.log("File is not empty");
          } else {

            $(".phperror").removeClass("on");
            $('div[data-role=error-notification]').html('');

            $("div[data-role=error-reset]").removeClass('err-on');
            $("div[data-role=error-reset]").addClass("err-off");
            $("div[data-role=error-reset]").html('');

            console.log("File is empty or does not exist");
          }
        }
      });
    }

    $(document).ready(function() {
      setInterval(function() {
        checkFileExists(error_location);
        // console.log('yo');
      }, 3000);

      $(document).on('click','div[data-role=error-reset]', function(e) { 
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
            // console.log(response);
            if(response) {
              // console.log(response);
              if(response['popup_signal'] == 'ok') {
                var url = window.location.href;
                $('#themepopupurl').val(url);

                setTimeout(function() {
                  $("#theme-options").fadeIn(500);
                  }, 750);

                // $("#theme-options").show(); /* for testing/dev */

              } else {
                /* do nothing */
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