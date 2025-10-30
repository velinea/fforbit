<?php
$pidFile = '/app/config/current.pid';
$status = '🔴 Idle';

if (file_exists($pidFile)) {
    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid > 0) {
        // Check if the process exists (ps -p PID)
        $exists = shell_exec("ps -p $pid -o pid= 2>/dev/null");
        if (!empty($exists)) {
            $status = '🟢 Running (PID ' . $pid . ')';
        } else {
            $status = '🟡 Stale PID (' . $pid . ')';
        }
    }
}
?>
<div id="status-line" style="text-align:center; margin-bottom:1rem; font-weight:600;">
  Status: <span id="status-text"><?= htmlspecialchars($status) ?></span>
</div>

<script>
function refreshStatus() {
  fetch('includes/status.php?t=' + Date.now())
    .then(r => r.text())
    .then(html => {
      document.getElementById('status-line').outerHTML = html;
    });
}
setInterval(refreshStatus, 5000);
</script>
