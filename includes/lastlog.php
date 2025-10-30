<?php
header('Content-Type: text/plain; charset=utf-8');
$logFile = '/app/config/last.log';
if (file_exists($logFile)) {
    readfile($logFile);
} else {
    echo "(no log yet)";
}
