window.signupSubmitting = false;
window.loginSubmitting = false;
window.forgotpassSubmitting = false;
window.resetpassSubmitting = false;

$(document).ready(function() {

  // Load auth partials
  $(document).on('click', '.create-form', function(e) {
    e.preventDefault();
    $("#authcon").load("_insert-signup.php", function() {
      setTimeout(function() {
        $("#username").focus();
      }, 10);
    });
  });

  $(document).on('click', '.log-form', function(e) {
    e.preventDefault();
    $("#authcon").load("_insert-login.php", function() {
      setTimeout(function() {
        $("#username").focus();
      }, 10);
    });
  });

  $(document).on('click', '.forgot-form', function(e) {
    e.preventDefault();
    $("#authcon").load("_insert-forgotpass.php", function() {
      setTimeout(function() {
        $("#username").focus();
      }, 10);
    });
  });

  // Show/hide login password
  $(document).on('click', '.showPass', function(e) {
    e.preventDefault();

    var x = document.getElementById("password");
    if (!x) return;

    $(this).toggleClass("showPassOn");

    if (x.type === "password") {
      x.type = "text";
      $(this).html('<i class="far fa-eye-slash"></i> Hide password');
    } else {
      x.type = "password";
      $(this).html('<i class="far fa-eye"></i> Show password');
    }
  });

  // Show/hide signup/reset passwords
  $(document).on('click', '.showSignupPass', function(e) {
    e.preventDefault();

    var x = document.getElementById('showPassword');
    var y = document.getElementById('showConf');

    if (!x && !y) return;

    $(this).toggleClass('showPassOn');

    var makeText = x && x.type === 'password';

    if (x) x.type = makeText ? 'text' : 'password';
    if (y) y.type = makeText ? 'text' : 'password';

    $(this).html(
      makeText
        ? '<i class="far fa-eye-slash"></i> Hide passwords'
        : '<i class="far fa-eye"></i> Show passwords'
    );
  });

  // Signup
  $(document).on('submit', '#signup-form', function(e) {
    e.preventDefault();

    if (window.signupSubmitting) return;
    window.signupSubmitting = true;

    var serializedData = $(this).serialize();
    var customData = { sign_up_process_routine: 'key' };

    $.ajax({
      dataType: "json",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),

      beforeSend: function() {
        $('#login-alert').removeClass();
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
        if (response) {
          if (response['signal'] == 'ok') {
            $('#authcon').html('<div class="successmessage">Success! Check your email for a verification link. Give it a minute or two and check your spam folder if you don\'t see it in your inbox.<br><br><a class="log-form">Show login</a></div>');
          } else {
            $('#login-alert').addClass('show ' + response['class']);
            $('#errors').html(response['li']);
            $('#toggle-btn').html('<button type="submit" class="processing-btn" id="signup-btn"><span class="login-txt">Try again</span></button>');
          }
        }
      },

      error: function() {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" class="processing-btn" id="signup-btn"><span>Try again</span></button>');
      },

      complete: function() {
        window.signupSubmitting = false;
      }
    });
  });

  // Login
  var login_attempts = 1;

  $(document).on('submit', '#login-form', function(e) {
    e.preventDefault();

    if (window.loginSubmitting) return;
    window.loginSubmitting = true;

    var current_loc = window.location.href;
    var list = $('li').attr('class');

    if ((typeof list !== 'undefined') && (list.indexOf('no-count') === -1)) {
      login_attempts += 1;
    }

    var serializedData = $(this).serialize();
    var customData = { login_routine: 'key' };

    $.ajax({
      dataType: "json",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),

      beforeSend: function() {
        $('#login-alert').removeClass();
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
        if (response) {
          if (response.signal === 'ok') {
            if (current_loc.indexOf("localhost") > -1) {
              window.location.replace("http://localhost/bobmeans");
            } else {
              window.location.replace("https://bobmeans.com/o");
            }
          } else {
            $('#session-msg').html('');
            $('#login-alert').addClass('show ' + response.class);

            if ((response.count === 'on') && login_attempts >= 3) {
              $('#errors').html(response.li + '<li>You\'ve entered the wrong password ' + login_attempts + ' times now. Don\'t forget, you can always <a class="forgot-form resetlink">reset</a> your password.</li>');
            } else {
              $('#errors').html(response.li);
            }

            $('#toggle-btn').html('<button type="submit" class="processing-btn" id="login-btn"><span>Try again</span></button>');
          }
        }
      },

      error: function() {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" class="processing-btn" id="login-btn"><span>Try again</span></button>');
      },

      complete: function() {
        window.loginSubmitting = false;
      }
    });
  });

  // Forgot password
  $(document).on('submit', '#forgotpass-form', function(e) {
    e.preventDefault();

    if (window.forgotpassSubmitting) return;
    window.forgotpassSubmitting = true;

    var serializedData = $(this).serialize();
    var customData = { forgot_password_routine: 'key' };

    $.ajax({
      dataType: "json",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),

      beforeSend: function() {
        $('#login-alert').removeClass();
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
        if (response) {
          if (response.signal === 'ok') {
            $('#login-alert').addClass('show ' + response.class);
            $('#login-alert').html(response.li);
            $('#toggle-btn').html('<div class="processing-btn processing"><span>Help on the way!</span></div>');
          } else {
            $('#login-alert').addClass('show ' + response.class);
            $('#errors').html(response.li);
            $('#toggle-btn').html('<button type="submit" class="processing-btn" id="forgotpass-btn"><span>Try again</span></button>');
          }
        }
      },

      error: function() {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" class="processing-btn" id="forgotpass-btn"><span>Try again</span></button>');
      },

      complete: function() {
        window.forgotpassSubmitting = false;
      }
    });
  });

  // Reset password
  $(document).on('submit', '#resetpass-form', function(e) {
    e.preventDefault();

    if (window.resetpassSubmitting) return;
    window.resetpassSubmitting = true;

    var current_loc = window.location.href;
    var serializedData = $(this).serialize();
    var customData = { reset_password_routine: 'key' };

    $.ajax({
      dataType: "json",
      url: "processing.php",
      type: "POST",
      data: serializedData + '&' + $.param(customData),

      beforeSend: function() {
        $('#login-alert').removeClass();
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
        if (response) {
          if (response.signal === 'ok') {
            if (current_loc.indexOf("localhost") > -1) {
              window.location.replace("http://localhost/bobmeans");
            } else {
              window.location.replace("https://bobmeans.com/o");
            }
          } else {
            $('#login-alert').addClass('show ' + response.class);
            $('#errors').html(response.li);
            $('#toggle-btn').html('<button type="submit" class="processing-btn" id="resetpass-btn"><span>Try again</span></button>');
          }
        }
      },

      error: function() {
        $('#login-alert').addClass('show red');
        $('#errors').html('<li>Something went wrong. Please try again.</li>');
        $('#toggle-btn').html('<button type="submit" class="processing-btn" id="resetpass-btn"><span>Try again</span></button>');
      },

      complete: function() {
        window.resetpassSubmitting = false;
      }
    });
  });

});