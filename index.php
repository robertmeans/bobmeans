<!DOCTYPE html>
<html lang="en">
<!--
  Author:       Evergreen Bob
  Contact:      robert@robertmeans.com
  Comments:     You look very nice today! :)
-->
<head>
	<meta charset="UTF-8">
	<meta http-equiv="cache-control" content="no-cache">
	<meta http-equiv="pragma" content="no-cache">
	<meta http-equiv="expires" content="0">
	
	<title>Bob Means | Evergreen, Colorado</title>
	<link rel="icon" type="image/ico" href="_images/favicon.ico">
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<meta name="description" content="Bob, Robert, Bobby, Rob... I'm him">
	<meta name="format-detection" content="telephone=no">

    <meta property="og:url" content="http://www.bobmeans.com" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Bob Means | Evergreen, Colorado" />
    <meta property="og:image" content="http://www.bobmeans.com/_images/bob-means.jpg?<?php echo time(); ?>" />
    <meta property="og:image:alt" content="Bob Means" />
    <meta property="og:description" content="Bob, Robert, Bobby, Rob... I'm him" />

	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous">
	<link href='https://fonts.googleapis.com/css?family=Montserrat|Lato' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" href="style.css?<?php echo time(); ?>" type="text/css">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.3/jquery-ui.min.js"></script>
	<script src='https://www.google.com/recaptcha/api.js'></script>
    <script src="js/preload.js?<?php echo time(); ?>"></script>

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-140046709-5"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'UA-140046709-5');
    </script>

</head>
<body>
<div class="preload"></div>
<div id="wrapper">
    <div id="wrapper-position">
    <?php
        function post_captcha($user_response) {
        $fields_string = '';
        $fields = array(
            'secret' => '6LfUQaoUAAAAANfHVccXQOixq-OeaR0-hz3bOyjX',
            'response' => $user_response
        );
        foreach($fields as $key=>$value)
        $fields_string .= $key . '=' . $value . '&';
        $fields_string = rtrim($fields_string, '&');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }
    // Call the function post_captcha
    $res = post_captcha($_POST['g-recaptcha-response']);
    if (!$res['success']) {

        // What happens when the CAPTCHA wasn't checked - Fallback validation
        // echo '<p style="color: red; padding: 10px; border: 1px solid red; background-color: white; float: left;"><b>Submission Unsuccessful</b><br />Please refresh and make sure you check the security CAPTCHA box.</p><br>';
        // All error checking is handled on the front end. No need for this.
    } else {
        echo '<div id="success-wrap"><span class="success-msg">Your message was sent successfully!</span></div>'; ?>
    <?php
        error_reporting(E_ALL ^ E_NOTICE);

        // set a variable to hold g-recaptcha-response so you can 
        // leave it out of the email body when message is composed
        if (isset($_POST['g-recaptcha-response'])) { 
            $captcha = $_POST['g-recaptcha-response'];
        }

        $my_email = "robert@robertmeans.com";

        // to let visitor fill in the "from" field leave string below empty 
        $from_email = "";

        $errors = array();

        if (count($_COOKIE)) {
            foreach(array_keys($_COOKIE) as $value) {
                unset($_REQUEST[$value]);
            }
        }

        if (isset($_REQUEST['email']) && !empty($_REQUEST['email'])) {
            $_REQUEST['email'] = trim($_REQUEST['email']);
            if (substr_count($_REQUEST['email'],"@") != 1 || stristr($_REQUEST['email']," ") || stristr($_REQUEST['email'],"\\") || stristr($_REQUEST['email'],":")) {
                $errors[] = "Email address is invalid";
            } else {
                $exploded_email = explode("@",$_REQUEST['email']);
                if (empty($exploded_email[0]) || strlen($exploded_email[0]) > 64 || empty($exploded_email[1])) {
                    $errors[] = "Email address is invalid";
                } else {
                    if (substr_count($exploded_email[1],".") == 0) {
                        $errors[] = "Email address is invalid";
                    } else {
                        $exploded_domain = explode(".",$exploded_email[1]);
                        if (in_array("",$exploded_domain)) {
                            $errors[] = "Email address is invalid";
                        } else {
                            foreach ($exploded_domain as $value) {
                                if (strlen($value) > 63 || !preg_match('/^[a-z0-9-]+$/i',$value)) {
                                    $errors[] = "Email address is invalid"; 
                                    break;
                                }
                            }
                        }
                    }
                }
            }

        }

        if (!(isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) && stristr($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST']))) {
            $errors[] = "There are many other scripts out there that are much easier to hijack. Please leave this one alone.";
        }

        function recursive_array_check_blank($element_value) {
            global $set;

            if (!is_array($element_value)) { 
                if (!empty($element_value)) {
                    $set = 1;
                }
            } else {
                foreach($element_value as $value) {
                    if($set) {
                        break;
                    } recursive_array_check_blank($value);
                }
            }
        }

        recursive_array_check_blank($_REQUEST);

        if (!$set) {
            $errors[] = "<script>alert('\\n\\nYou cannot submit a blank form.');window.location.replace('index.php');</script>";
        }

        unset($set);

        if (count($errors)){
            foreach($errors as $value){
                print "$value<br>";
            } exit;
        }

        if (!defined("PHP_EOL")){
            define("PHP_EOL", strtoupper(substr(PHP_OS,0,3) == "WIN") ? "\r\n" : "\n");
        }

        function build_message($request_input){
            if (!isset($message_output)) {
                $message_output ="";
            } if (!is_array($request_input)) {
                $message_output = $request_input;
            } else {
                foreach($request_input as $key => $value) {
                    // check that the key of the $_POST variable is not the
                    // g-recaptcha-response before adding it to the message
                    if ($key != 'g-recaptcha-response') {

                        if(!empty($value)) {
                            if (!is_numeric($key)) {
                                $message_output .= str_replace("_"," ",ucfirst($key)).": ".build_message($value).PHP_EOL.PHP_EOL;
                            } else {
                                $message_output .= build_message($value).", ";
                            }
                        }
                    }
                }   
            } return rtrim($message_output,", ");
        }

        $message = build_message($_REQUEST);
        $message = $message . PHP_EOL.PHP_EOL."".PHP_EOL."";
        $message = stripslashes($message);
        $subject = "Message From BobMeans.com Website";
        $subject = stripslashes($subject);

        if ($from_email) {
            $headers = "From: " . $from_email;
            $headers .= PHP_EOL;
            $headers .= "Reply-To: " . $_REQUEST['email'];
            } else {
                $from_name = "";
                if (isset($_REQUEST['name']) && !empty($_REQUEST['name'])) {
                    $from_name = stripslashes($_REQUEST['name']);
                }

            $headers = "From: {$from_name} <{$_REQUEST['email']}>"."\r\n";
            /* BCC if needed */
            // $headers .= "BCC: robert@evergreenwebdesign.com\r\n";

            }

            mail($my_email,$subject,$message,$headers);
        // must exit the else statement so it does not print the form again
        // break;
        }
    ?>
<div id="contact-wrapper" class="cf">
        <img class="click-box-01 bob-twirl" src="_images/bob-means.jpg">

        <div id="button-wrap">
            <button id="button-box-01" class="click-box-01"><i class="far fa-comments" aria-hidden="true"></i>&nbsp;&nbsp; chit chat</button>

            <button id="button-box-02" class="click-box-02"><i class="fas fa-feather-alt" aria-hidden="true"></i>&nbsp;&nbsp; manifesto</button>
        </div><!-- #button-wrap -->

        <div id="box-01" class="foo">
            <form action="" method="post" id="contactForm" onSubmit="return validateEmail(document.forms[0].email.value);">  
                <p>Lay it on me.</p>
                <ul>
                  <li>
                    <label class="text" for="name">What's your name?</label>
                    <input required name="name" type="text" id="name" tabindex="10" />
                  </li>
                  <li>
                    <label class="text" for="email">Email</label>
                    <input name="email" type="email" id="email" tabindex="20" />
                  </li>
                  <li>
                    <label class="text" for="comments">Message</label>
                    <textarea required name="comments" id="comments" tabindex="30"></textarea>
                  </li>
                  <li>
                    <div class="g-recaptcha" data-theme="dark" data-callback="recaptchaCallback" data-sitekey="6LfUQaoUAAAAALGmoQMRWpY0R2PDUfjA9f1yi-e3"></div>
                  </li>
                  <li>
                    <button id="confirm" disabled>Check Captcha above to enable Send</button>
                    <button id="send" class="display" disabled>Send</button>
                  </li>
                </ul> 
            </form>
        </div><!-- #box-01 -->

        <div id="box-02" class="text-left">
            <div class="fb-align">
                <a class="fb-follow" href="https://www.facebook.com/evergreenbob" target="_blank"><img class="fb-logo" src="_images/facebook.png" alt="Facebook"></a><p>DBA <a class="linko" href="http://www.evergreenwebdesign.com" target="_blank" style="color:#55e239;">Evergreen Web Design</a></p>
            </div><!-- .fb-align -->
            <p>Mission Statement &amp; Focus Areas of Guiding Mantra Principles for Service Manifest and Trancendent Value Priorities:</p>
            <ol>
                <li>Building Websites That Work</li>
                <li>Fixing Ones That Don't</li>
            </ol>
            <p class="phone-ewd">Call: <a href="tel:(303)%20932-7483">(303) WEBSITE</a></p>
        </div><!-- #box-02 -->

    </div><!-- #contact-wrapper -->
    </div><!-- #wrapper-position -->
</div><!-- #wrapper -->

<script src="js/scripts.js?<?php echo time(); ?>"></script>
<script src="http://localhost:35729/livereload.js"></script>    
</body>
</html>