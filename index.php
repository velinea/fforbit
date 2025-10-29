<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>FFOrbit Web</title>
<style>
body { font-family: sans-serif; margin: 2em; background:#111; color:#ddd; }
a { color:#9cf; text-decoration:none; }
a:hover { text-decoration:underline; }
input, button { padding:.4em; font-size:1em; margin:.2em 0; }
pre { background:#000; color:#0f0; padding:1em; overflow-x:auto; }
fieldset { border:1px solid #333; margin-top:1.5em; padding:1em; }
</style>
</head>
<body>

<h1>🎬 FFOrbit – Simple FFmpeg Web UI</h1>

<!-- 🔍 Search box -->
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
  passthru($cmd);
  echo "</pre>";
}
?>

</body>
</html>
