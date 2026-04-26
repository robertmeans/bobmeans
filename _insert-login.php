      <h1>Login</h1>

      <?php require '_includes/auth-comm.php'; ?>

      <div id="formcon">

        <form id="login-form" class="auth-form" method="post">
          <input type="hidden" name="login" value="login"><?php /* processing key */ ?>

          <input type="text" class="txtfield" id="username" name="username" placeholder="Username or Email" autoFocus>       
          <input type="password" class="txtfield login-pswd" id="password" name="password" placeholder="Password">
          <div class="showpassword-wrap"> 
            <div class="showPass">
              <i class="far fa-eye"></i> Show password
            </div>
          </div>
          <div class="reme">
              <input class="remebox" type="checkbox" name="remember_me" id="remember_me"> 
              <label class="remelabel" for="remember_me">
                Remember me
              </label>
          </div>
          <div id="toggle-btn">
            <button type="submit" class="processing-btn" id="login-btn">
              <span>Login</span>
            </button>
          </div>
          <div class="fpwd">
            <p>No account? <a class="create-form">Create one</a></p>
            <p><a class="forgot-form">Forgot your Password?</a></p>
          </div>
        </form>

      </div><!-- #formcon -->