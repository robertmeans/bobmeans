$(document).ready(function() { /* closes with: 'docready close' */

  $(document).on('click','.create-form', function() {
    $("#authcon").load("_insert-signup.php", function() {
        setTimeout(function() {
            $("#username").focus();
        }, 10);
    });
  });
  $(document).on('click','.log-form', function() {
    $("#authcon").load("_insert-login.php", function() {
        setTimeout(function() {
            $("#username").focus();
        }, 10);
    });
  });
  $(document).on('click','.forgot-form', function() {
    $("#authcon").load("_insert-forgotpass.php", function() {
        setTimeout(function() {
            $("#username").focus();
        }, 10);
    });
  });

});

/* show passwords */
$(document).on('click', '.showPass', function(e) {
  var x = document.getElementById("password");

  $(this).toggleClass("showPassOn");

  if ($.trim($(this).html()) === '<i class="far fa-eye-slash"></i> Hide password') {
      $(this).html('<i class="far fa-eye"></i> Show password');
      x.type = "password";
  } else {
      $(this).html('<i class="far fa-eye-slash"></i> Hide password');
      x.type = "text";
  }
  return false;
});

$(document).on('click', '.showSignupPass', function(e) {  
  var x = document.getElementById('showPassword');
  var y = document.getElementById('showConf');
  $(this).toggleClass('showPassOn');

  if ($.trim($(this).html()) === '<i class="far fa-eye-slash"></i> Hide passwords') {
      $(this).html('<i class="far fa-eye"></i> Show passwords');
      x.type = "password";
      y.type = "password";
  } else {
      $(this).html('<i class="far fa-eye-slash"></i> Hide passwords');
      x.type = "text";
      y.type = "text";
  }
  return false;
});

// signup begin
window.signupSubmitting = false; 

$(document).ready(function() {

  $(document).on('submit','#signup-form', function(e) {
    e.preventDefault();

    if (window.signupSubmitting) return;
    window.signupSubmitting = true;

    var current_loc = window.location.href;

    var serializedData = $('#signup-form').serialize();
    var customData = { sign_up_process_routine: 'key' }; // can make comma separated array here

    $.ajax({
      dataType: "JSON",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),
      beforeSend: function(xhr) {
        $('#login-alert').removeClass(); // reset class every click
        $('#errors').html('');
        // $('#toggle-btn').html('<div id="signup-btn" class="processing"><span>Holup</span></div>');
        $('#toggle-btn').html(
          '<div class="processing-btn processing">' +
            '<span>Wait a sec</span>' +
            '<span class="dot-loader" aria-hidden="true">' +
              '<span></span><span></span><span></span>' +
            '</span>' +
          '</div>'
        );

      },
      success: function(response) {
        if(response) {
          if(response['signal'] == 'ok') {

            $('#authcon').html('<div class="successmessage">Success! Check your email for a verificaion link. Give it a minute or two and check your spam folder if you don\'t see it in your inbox.<br><br><a class="log-form">Show login</a></div>');

          } else {
            $('#login-alert').addClass('show ' + response['class']);
            $('#errors').html(response['li']);
            $('#toggle-btn').html('<button type="submit" id="signup-btn"><span class="login-txt">Try again</span></button>');
          }
        } 
      },
      error: function(response) {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" id="signup-btn"><span>Try again</span></button>');
      }, 
      complete: function() {
        window.signupSubmitting = false;
      }
    })

  });

}); // $(document).ready signup end

// login begin
window.loginSubmitting = false; 

$(document).ready(function() { 

  var login_attempts = 1;
  $(document).on('submit','#login-form', function(e) {
    e.preventDefault();

    if (window.loginSubmitting) return;
    window.loginSubmitting = true;

    var current_loc = window.location.href;
    var list = $('li').attr('class');

    if ( (typeof list !== 'undefined') && (list.indexOf('no-count') === -1) ) {
      login_attempts += 1;
    } else {
      login_attempts += 0;
    }

    var serializedData = $('#login-form').serialize();
    var customData = { login_routine: 'key' }; // can make comma separated array here

    $.ajax({
      dataType: "JSON",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),
      beforeSend: function(xhr) {
        $('#login-alert').removeClass(); // reset class every click
        $('#errors').html('');
        $('#toggle-btn').html(
          '<div class="processing-btn processing">' +
            '<span>Wait a sec</span>' +
            '<span class="dot-loader" aria-hidden="true">' +
              '<span></span><span></span><span></span>' +
            '</span>' +
          '</div>'
        );

      },
      success: function(response) {
        if(response) {
          if(response['signal'] == 'ok') {

            if (current_loc.indexOf("localhost") > -1) {
              window.location.replace("http://localhost/bobmeans");
            } else {
              window.location.replace("https://bobmeans.com");
            }

          } else {
            $('#session-msg').html('');
            $('#login-alert').addClass('show ' + response['class']);

            if ((response['count'] == 'on') && login_attempts >= 3) {
              $('#errors').html(response['li'] + '<li>You\'ve entered the wrong password ' + login_attempts + ' times now. Don\'t forget, you can always <a class="forgot-form resetlink">reset</a> your password.</li>');
            } else {
              $('#errors').html(response['li']);
            }

            $('#toggle-btn').html('<button type="submit" id="login-btn"><span>Try again</span></button>');
          }
        } 
      },
      error: function(response) {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" id="login-btn"><span>Try again</span></button>');
      },
      complete: function() {
        window.loginSubmitting = false;
      }
    })
  }); 

}); /* docready close */
// login end

// forgot password (start reset process) begin
window.forgotpassSubmitting = false;    

$(document).ready(function() {

  $(document).on('submit','#forgotpass-form', function(e) {
    e.preventDefault();

    if (window.forgotpassSubmitting) return;
    window.forgotpassSubmitting = true;

    const serializedData = $('#forgotpass-form').serialize();
    const customData = { forgot_password_routine: 'key' }; // can make comma separated array here

    $.ajax({
      dataType: "JSON",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),
      beforeSend: function() {
        $('#login-alert').removeClass(); // reset class every click
        $('#errors').html('');
        $('#toggle-btn').html(
          '<div class="processing-btn processing">' +
            '<span>Wait a sec</span>' +
            '<span class="dot-loader" aria-hidden="true">' +
              '<span></span><span></span><span></span>' +
            '</span>' +
          '</div>'
        );

      },
      success: function(response) {
        if(response) {
          if(response['signal'] == 'ok') {
            $('#login-alert').addClass('show ' + response['class']);
            $('#login-alert').html(response['li']);
            $('#toggle-btn').html('<div class="processing-btn processing"><span>Help on the way!</span></div>');

          } else {
            $('#login-alert').addClass('show ' + response['class']);
            $('#errors').html(response['li']);
            $('#toggle-btn').html('<button type="submit" id="forgotpass-btn"><span>Try again</span></button>');
          }
        } 
      },
      error: function(response) {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" id="forgotpass-btn"><span>Try again</span></button>');
      }, 
      complete: function() {
        window.forgotpassSubmitting = false;
      }
    })
  });
}); // $(document).ready forgot password (start reset process) end

// reset password begin
window.resetpassSubmitting = false;

$(document).ready(function() {

  $(document).on('submit','#resetpass-form', function(e) {
    e.preventDefault();

    if (window.resetpassSubmitting) return;
    window.resetpassSubmitting = true;

    var current_loc = window.location.href;

    var serializedData = $('#resetpass-form').serialize();
    var customData = { reset_password_routine: 'key' }; // can make comma separated array here

    $.ajax({
      dataType: "JSON",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),
      beforeSend: function(xhr) {
        $('#login-alert').removeClass(); // reset class every click
        $('#errors').html('');
        // $('#toggle-btn').html('<div id="forgotpass-btn" class="processing"><span>Holup</span></div>');
        $('#toggle-btn').html(
          '<div class="processing-btn processing">' +
            '<span>Wait a sec</span>' +
            '<span class="dot-loader" aria-hidden="true">' +
              '<span></span><span></span><span></span>' +
            '</span>' +
          '</div>'
        );

      },
      success: function(response) {
        if(response) {
          if(response['signal'] == 'ok') {

            if (current_loc.indexOf("localhost") > -1) {
              window.location.replace("http://localhost/bobmeans");
            } else {
              window.location.replace("https://bobmeans.com");
            }

          } else {
            $('#login-alert').addClass('show ' + response['class']);
            $('#errors').html(response['li']);
            $('#toggle-btn').html('<button type="submit" id="resetpass-btn"><span>Try again</span></button>');
          }
        } 
      },
      error: function(response) {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" id="resetpass-btn"><span>Try again</span></button>');
      }, 
      complete: function() {
        window.resetpassSubmitting = false;
      }
    })

  });

}); // $(document).ready forgot password end