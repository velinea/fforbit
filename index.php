<?php require_once __DIR__ . '/includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FFOrbit</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
  <img src="fforbit.png" alt="FFOrbit Logo" id="logo" height="64">
  <h1>FFOrbit</h1>
  </header>
  <?php include __DIR__ . '/includes/status.php'; ?>
  <?php include __DIR__ . '/includes/search-form.php'; ?>
  <?php include __DIR__ . '/includes/transcode-controls.php'; ?>
  <?php include __DIR__ . '/includes/log.php'; ?>

  <footer>FFOrbit · Orbit Family Project · <?= date('Y') ?></footer>
</body>
</html>
