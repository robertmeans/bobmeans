      <h1>Create Account</h1>

      <?php require '_includes/auth-comm.php'; ?>

      <div id="formcon">

        <form id="signup-form" class="auth-form" method="post">

          <input type="hidden" name="signup">

          <input class="txtfield" type="text" id="username" name="username" placeholder="Username" autofocus>
          <input type="email" class="txtfield" name="email" placeholder="Email address" required>
          <input type="password" id="showPassword" class="txtfield" name="password" placeholder="Password">
          <input type="password" id="showConf" class="txtfield" name="passwordConf" placeholder="Confirm password">

          <div class="showpassword-wrap"> 
            <div class="showSignupPass">
              <i class="far fa-eye"></i> Show Passwords
            </div>
          </div>

          <div id="toggle-btn">
            <button type="submit" class="processing-btn" id="signup-btn">
              <span class="login-txt">Join</span>
            </button>
          </div>

          <div class="fpwd">
            <p class="btm-p">Already a member? <a class="log-form">Sign in</a></p>
          </div>

        </form>

      </div><!-- #formcon -->