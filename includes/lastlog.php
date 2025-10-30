<?php
header('Content-Type: text/plain; charset=utf-8');
$logFile = '/app/config/last.log';
if (file_exists($logFile) && filesize($logFile) > 0) {
    readfile($logFile);
} else {
    echo "🛰️ FFOrbit ready — no active jobs.\n";
}
