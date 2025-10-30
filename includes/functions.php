<?php
$logFile = '/app/config/last.log';
if (!file_exists($logFile) || filesize($logFile) === 0) {
    file_put_contents($logFile, "🛰️ FFOrbit ready — no active jobs.\n");
}

function start_transcode($path) {
    $path = trim($path);
    if ($path === '') {
        echo "<p>⚠️ No video file selected.</p>";
        return;
    }

    $videoPath = escapeshellarg($path);
    $logFile = '/app/config/last.log';

    // Clear previous log
    file_put_contents($logFile, "🎬 Starting new transcode for $path\n", LOCK_EX);

    // Use bash -c so backgrounding and environment work properly under PHP
    $cmd = "bash -c 'nohup /bin/bash /app/transcode.sh $videoPath >> $logFile 2>&1 &'";

    shell_exec($cmd);

    // Give nohup a moment to start, then locate the PID
    usleep(300000); // 0.3 seconds
    $pid = trim(shell_exec("pgrep -n -f " . escapeshellarg("transcode.sh $path")));

    if ($pid) {
        file_put_contents('/app/config/current.pid', $pid);
        file_put_contents($logFile, "✅ Transcode started (PID $pid)\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "❌ Could not find running PID\n", FILE_APPEND);
        echo "<p>⚠️ Failed to detect running PID.</p>";
    }
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
