<?php
$root = '/data/media/Movies';
$files = array_slice(array_filter(
    explode("\n", trim(shell_exec("find " . escapeshellarg($root) . " -type f -iregex '.*\\.(mkv|mp4|avi)' -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-")))
), 0, 20);

if (empty($files)) {
    echo "<div>(no movies found)</div>";
    exit;
}

foreach ($files as $f) {
    $name = basename($f);
    echo "<div class='pickable' data-path='" . htmlspecialchars($f, ENT_QUOTES) . "' style='padding:0.3rem; cursor:pointer; border-bottom:1px solid #30363d;'>🎬 $name</div>";
}
?>
