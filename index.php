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
<div id="contact-wrapper" class="cf">
  <img class="click-box-01 bob-twirl" src="_images/bob-means.jpg">

  <div id="button-wrap">
      <button id="button-box-01" class="click-box-01"><i class="far fa-comments" aria-hidden="true"></i>&nbsp;&nbsp; chit chat</button>

      <button id="button-box-02" class="click-box-02"><i class="fas fa-feather-alt" aria-hidden="true"></i>&nbsp;&nbsp; manifesto</button>
  </div><!-- #button-wrap -->

  <div id="box-01" class="foo">
      <form id="contactForm">  
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
            <li id="msg"></li>
            <li>
              <input id="send" type="submit" class="display" value="Send">
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
<!-- <script src="http://localhost:35729/livereload.js"></script>     -->
</body>
</html>