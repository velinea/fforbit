<?php
function start_transcode($path) {
    $log = '/app/config/last.log';
    $videoPath = escapeshellarg($path);
    $cmd = "nohup /app/transcode.sh $videoPath > $log 2>&1 & echo $!";
    $pid = trim(shell_exec($cmd));
    file_put_contents('/app/config/current.pid', $pid);
}

function stop_transcode() {
    $pidFile = '/app/config/current.pid';
    if (!file_exists($pidFile)) {
        echo "<p>⚠️ No active job found.</p>";
        return;
    }

    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid <= 0) {
        echo "<p>⚠️ Invalid PID.</p>";
        return;
    }

    shell_exec("kill -TERM " . escapeshellarg($pid) . " 2>&1");
    echo "<p>⛔ Stopped transcode (PID $pid)</p>";
}
