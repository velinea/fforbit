<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>FFOrbit Web</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<h1>🎬 FFOrbit – Simple FFmpeg Web UI</h1>

<!-- 🔍 Search -->
<form method="get">
  <fieldset>
    <legend>Search movie</legend>
    <input name="search" size="50" placeholder="Type part of filename…" 
           value="<?=htmlspecialchars($_GET['search'] ?? '')?>">
    <button>🔍 Search</button>
  </fieldset>
</form>

<?php
$selectedPath = $_GET['path'] ?? '';
if (isset($_GET['search']) && $_GET['search'] !== '') {
  $term = escapeshellarg($_GET['search']);
  echo "<h3>Matches for “".htmlspecialchars($_GET['search'])."”:</h3><ul>";
  exec("find /data/media -type f \\( -iname '*.mkv' -o -iname '*.mp4' -o -iname '*.avi' \\) -iname *$term* 2>/dev/null | head -n 50", $out);
  if ($out) {
    foreach ($out as $line) {
      $href = htmlspecialchars($_SERVER['PHP_SELF'])."?path=".urlencode($line);
      echo "<li><a href='$href'>".htmlspecialchars($line)."</a></li>";
    }
  } else {
    echo "<p>No matches found.</p>";
  }
  echo "</ul>";
}
?>

<!-- 🎞 Transcode form -->
<form method="post">
  <fieldset>
    <legend>Transcode movie</legend>
    <label>Path:</label><br>
    <input name="path" size="80" value="<?=htmlspecialchars($selectedPath ?: ($_POST['path'] ?? ''))?>"><br>

    <label>Audio track number:</label><br>
    <input name="track" value="<?=htmlspecialchars($_POST['track'] ?? '0')?>"><br>

    <label>Audio language code:</label><br>
    <input name="lang" value="<?=htmlspecialchars($_POST['lang'] ?? 'eng')?>"><br>

    <label>Transcode video (h265):</label>
    <input type="checkbox" name="video" <?=isset($_POST['video'])?'checked':''?>><br>

    <label>Transcode audio (AAC):</label>
    <input type="checkbox" name="audio" <?=isset($_POST['audio'])?'checked':''?>><br>

    <label>Global quality (lower = better):</label><br>
    <input name="gq" value="<?=htmlspecialchars($_POST['gq'] ?? '23')?>"><br>

    <button type="submit">▶ Run Transcode</button>
  </fieldset>
</form>

<?php
$logFile = '/app/config/history.log'; 
$defaultsFile = '/app/config/defaults.json';
if (file_exists($defaultsFile)) {
    $defaults = json_decode(file_get_contents($defaultsFile), true);
    // Apply defaults to form values
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $path   = escapeshellarg($_POST['path']);
  $track  = escapeshellarg($_POST['track']);
  $lang   = escapeshellarg($_POST['lang']);
  $video  = isset($_POST['video']) ? "y" : "n";
  $audio  = isset($_POST['audio']) ? "y" : "n";
  $gq     = escapeshellarg($_POST['gq']);

  $cmd = "/usr/local/bin/transcode.sh $path $track $lang $video $audio $gq 2>&1";
  echo "<h2>Running:</h2><pre>$cmd</pre>";

  echo "<h2>Output:</h2><pre>";
  ob_flush(); flush();
  passthru($cmd, $status);
  echo "</pre>";

  // Append to history log
  $now = date("Y-m-d H:i:s");
  $entry = "$now | " . $_POST['path'] . " | track:" . $_POST['track'] . " | lang:" . $_POST['lang'] . " | video:$video | audio:$audio | q:$gq | status:" . ($status === 0 ? "✅ OK" : "❌ FAIL") . "\n";
  file_put_contents($logFile, $entry, FILE_APPEND);
}
?>

<!-- 📜 History Log -->
<?php
if (file_exists($logFile)) {
  $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $lines = array_slice(array_reverse($lines), 0, 20); // last 20 entries
  echo "<fieldset><legend>Recent Jobs</legend>";
  echo "<table style='width:100%; border-collapse:collapse;'>";
  echo "<tr><th align='left'>Timestamp</th><th align='left'>File</th><th>Result</th></tr>";
  foreach ($lines as $line) {
    $parts = explode('|', $line);
    echo "<tr>";
    echo "<td style='width:160px;'>".trim($parts[0] ?? '')."</td>";
    echo "<td>".htmlspecialchars(trim($parts[1] ?? '')) ."</td>";
    echo "<td align='center'>".(str_contains($line, 'OK') ? "✅" : "❌")."</td>";
    echo "</tr>";
  }
  echo "</table></fieldset>";
}
?>

<footer>
  <hr>
  <p>FFOrbit Web – Part of the <strong>Orbit</strong> family © 2025</p>
</footer>

</body>
</html>
