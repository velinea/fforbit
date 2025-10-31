<?php
// -----------------------------------------------------------------------------
// FFOrbit lightweight API: start / stop / status / tail
// -----------------------------------------------------------------------------
header('Content-Type: text/plain; charset=utf-8');

$logFile = '/app/config/last.log';
$pidFile = '/app/config/current.pid';
$action  = $_GET['action'] ?? '';

switch ($action) {
    // -------------------------------------------------------------------------
    // START
    // -------------------------------------------------------------------------
    case 'start':
        $path = trim($_GET['path'] ?? '');
        if ($path === '') {
            http_response_code(400);
            echo "❌ No video path provided.";
            exit;
        }

        // Reset log and old PID
        file_put_contents($logFile, "🎬 Starting new transcode for $path\n");
        @unlink($pidFile);

        // Run script in background and capture PID
        $cmd = "bash -c 'nohup /bin/bash /app/transcode.sh " . escapeshellarg($path)
             . " >> $logFile 2>&1 & echo $!'";
        $pid = trim(shell_exec($cmd));

        if (ctype_digit($pid)) {
            file_put_contents($pidFile, $pid);
            echo "✅ Started PID $pid";
        } else {
            echo "❌ Failed to start (output: $pid)";
        }
        break;

    // -------------------------------------------------------------------------
    // STOP
    // -------------------------------------------------------------------------
    case 'stop':
        if (!file_exists($pidFile)) {
            echo "⚠️ No active PID.";
            exit;
        }
        $pid = trim(file_get_contents($pidFile));
        if ($pid && file_exists("/proc/$pid")) {
            shell_exec("kill -TERM " . escapeshellarg($pid));
            echo "⛔ Stopped PID $pid";
        } else {
            echo "⚠️ PID $pid not active.";
        }
        @unlink($pidFile);
        break;

    // -------------------------------------------------------------------------
    // STATUS
    // -------------------------------------------------------------------------
    case 'status':
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && file_exists("/proc/$pid")) {
                echo "running:$pid";
                exit;
            }
        }
        echo "idle";
        break;

    // -------------------------------------------------------------------------
    // LOG (optional for AJAX)
    // -------------------------------------------------------------------------
    case 'log':
        if (file_exists($logFile)) {
            readfile($logFile);
        } else {
            echo "🛰️ FFOrbit ready — no active jobs.\n";
        }
        break;

    default:
        http_response_code(400);
        echo "Unknown action";
}
