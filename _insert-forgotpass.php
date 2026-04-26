
      <h1>Reset Password</h1>

      <?php require '_includes/auth-comm.php'; ?>

      <div id="formcon">

        <form id="forgotpass-form" class="auth-form" method="post">

          <input type="hidden" name="forgotpass">
          <input type="email" id="username" class="txtfield" name="email" placeholder="Enter your email">

          <div id="toggle-btn">
            <button type="submit" class="processing-btn" id="forgotpass-btn">
              <span>Send reset link</span>
            </button>
          </div>

          <div class="fpwd">
            <p>Think you remembered it? <a class="log-form">Try again</a></p>
          </div>

        </form>

      </div><!-- #formcon -->

