<?php
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['path'])) {
    start_transcode($_POST['path']);
}
if (isset($_GET['stop'])) {
    stop_transcode();
}
?>
<section id="transcode">
  <h2>Transcode</h2>

  <div style="display:flex; gap:0.5rem; justify-content:center;">
    <input type="text" id="videoPath" placeholder="/data/media/Movies/...">
    <button id="startButton" class="start" disabled>🚀 Start</button>
    <button id="stopButton" class="stop">⛔ Stop</button>
  </div>
</section>

<script>
const pathInput  = document.getElementById('videoPath');
const startBtn   = document.getElementById('startButton');
const stopBtn    = document.getElementById('stopButton');
const logText    = document.getElementById('logText');

function updateButtonState() {
  startBtn.disabled = (pathInput.value.trim() === '');
}
pathInput.addEventListener('input', updateButtonState);
updateButtonState();

// --- Start ---------------------------------------------------------------
startBtn.addEventListener('click', () => {
  const path = pathInput.value.trim();
  if (!path) return;
  fetch('api.php?action=start&path=' + encodeURIComponent(path))
    .then(r => r.text()).then(msg => {
      appendLog(msg + "\n");
      refreshStatus();
    });
});

// --- Stop ---------------------------------------------------------------
stopBtn.addEventListener('click', () => {
  fetch('api.php?action=stop')
    .then(r => r.text()).then(msg => {
      appendLog(msg + "\n");
      refreshStatus();
    });
});

// --- Helpers -------------------------------------------------------------
function appendLog(line) {
  if (!logText) return;
  logText.textContent += line;
  logText.scrollTop = logText.scrollHeight;
}

function refreshStatus() {
  fetch('api.php?action=status')
    .then(r => r.text())
    .then(txt => {
      const statusEl = document.getElementById('status-text');
      if (statusEl) {
        if (txt.startsWith('running')) {
          const pid = txt.split(':')[1];
          statusEl.textContent = '🟢 Running (PID ' + pid + ')';
        } else {
          statusEl.textContent = '🔴 Idle';
        }
      }
    });
}
setInterval(refreshStatus, 5000);

// --- Simple file picker ------------------------------------------------------
document.getElementById('browseButton').addEventListener('click', () => {
  fetch('includes/filepicker.php')
    .then(r => r.text())
    .then(html => {
      picker.innerHTML = html;
      picker.style.display = 'block';
    });
});

// Delegate clicks from picker list
picker.addEventListener('click', (e) => {
  if (e.target.matches('.pickable')) {
    pathInput.value = e.target.dataset.path;
    picker.style.display = 'none';
    updateButtonState();
  }
});
</script>
