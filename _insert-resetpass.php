<?php // require_once 'config/initialize.php'; ?>

      <h1>Create New Password</h1>

      <?php require '_includes/auth-comm.php'; ?>

      <div id="formcon">

        <form id="resetpass-form" class="auth-form" method="post">

          <input type="hidden" name="resetpass" value="resetpass">
          <input id="showPassword" type="password" class="txtfield" name="password" placeholder="Password" autoFocus>
          <input id="showConf" type="password" class="txtfield" name="passwordConf" placeholder="Confirm password">
          
          <div class="showpassword-wrap"> 
            <div class="showSignupPass"><i class="far fa-eye"></i> Show Password</div>
          </div>

          <div id="toggle-btn">
            <button type="submit" class="processing-btn" id="resetpass-btn">
              <span>Reset Password</span>
            </button>
          </div> 

          <div class="fpwd">
            <p>Think you remembered it? <a class="log-form">Try again</a></p>
          </div>
        </form>

      </div><!-- #formcon -->

