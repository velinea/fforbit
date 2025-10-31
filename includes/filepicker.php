<?php
// Adjust this to your actual mount:
$root = '/data/media/Downloads/complete/Movies';

// Build a safe command:
// - Use escapeshellarg for the root path
// - Match typical containers: .mkv, .mp4, .avi
// - Sort by mtime (newest first) via GNU find's -printf
$rootArg = escapeshellarg($root);
$cmd = "find $rootArg -type f \\( -iname '*.mkv' -o -iname '*.mp4' -o -iname '*.avi' \\) "
     . "-printf '%T@ %p\\n' | sort -nr | cut -d' ' -f2- | head -n 20";

$lines = trim(shell_exec($cmd));
$files = $lines ? explode("\n", $lines) : [];

if (!$files) {
    echo "<div>(no movies found)</div>";
    // Uncomment for quick debugging:
    // echo "<pre>CMD:\n$cmd\n\nOUTPUT:\n$lines</pre>";
    exit;
}

foreach ($files as $f) {
    $name = basename($f);
    echo "<div class='pickable' data-path='" . htmlspecialchars($f, ENT_QUOTES) . "' "
       . "style='padding:0.3rem; cursor:pointer; border-bottom:1px solid #30363d;'>🎬 "
       . htmlspecialchars($name) . "</div>";
}
