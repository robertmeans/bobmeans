<!DOCTYPE html>
<html lang="en">
<!--

  ♥ Hand coded with love by EvergreenBob.com

-->
<head>
  <meta charset="UTF-8">  
  <?php if (WWW === 'dev') { ?>
    <title>Local version</title>
  <?php } else { ?>
    <title>Online version</title>
  <?php } ?>
  <?php /* <title>Budget Allocation Organization</title> */ ?>

  <link rel="icon" type="image/ico" href="_images/favicon.webp">
  <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <meta name="description" content="A place to work on stuff">
  <meta name="format-detection" content="telephone=no">

  <meta property="og:url" content="https://bobmeans.com/" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Big Fat Project" />
  <meta property="og:image" content="https://bobmeans.com/_images/favicon.webp" />
  <meta property="og:image:alt" content="Dolla dolla holla" />
  <meta property="og:description" content="A place to work on stuff" />

  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.1/css/all.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.1/css/v4-shims.css">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="style.css?<?php echo time(); ?>" type="text/css">

  <?php /*
  <script src="_scripts/jquery-3.5.1.min.js"></script>
  <script src="_scripts/jquery_1-12-1_ui_min.js"></script>
  */ ?>

  <?php if (WWW === 'dev') { ?>
    <script src="_scripts/jquery-4.0.0.min.js"></script>
  <?php } else { ?>
    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
  <?php } ?>

</head>
<body <?php if (isset($_SESSION['loggedin'])) { ?>class="lbbc"<?php } ?>>
  <div class="main-wrap"><?php /* .main-wrap open */ ?>