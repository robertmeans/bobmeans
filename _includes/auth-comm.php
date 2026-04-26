      <div id="session-msg">
        <?php if(isset($_SESSION['login-message'])) { ?>
        <div class="alert <?php echo $_SESSION['alert-class']; ?>">

          <?php
            /* if (isset($_SESSION['username'])) { echo 'Welcome, ' . $_SESSION['username'] . '!<br>'; } */
            echo $_SESSION['login-message'];
            unset($_SESSION['login-message']);
            unset($_SESSION['alert-class']); 
          ?>
        </div><!-- .alert -->
        <?php } ?>
      </div>

      <div id="login-alert">
        <ul id="errors"></ul>
      </div>