<?php
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['path'])) {
    start_transcode($_POST['path']);
}
if (isset($_GET['stop'])) {
    stop_transcode();
}
?>
<section id="transcode">
  <h2>Transcode</h2>
  <form method="post">
      <input type="text" name="path" placeholder="/data/media/Movies/...">
      <button type="submit" class="start">🚀 Start</button>
  </form>

  <form method="get">
      <button type="submit" name="stop" value="1" class="stop">⛔ Stop</button>
  </form>
</section>
