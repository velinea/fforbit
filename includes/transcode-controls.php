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

  <form id="transcodeForm" method="post">
    <div style="display:flex; gap:0.5rem; justify-content:center;">
      <input type="text" id="videoPath" name="path" placeholder="/data/media/Movies/...">
      <button type="button" id="browseButton">📂</button>
      <button type="submit" id="startButton" class="start" disabled>🚀 Start</button>
    </div>
  </form>

  <form method="get" id="stopForm">
      <button type="submit" name="stop" value="1" class="stop">⛔ Stop</button>
  </form>

  <div id="picker" style="display:none; background:#161b22; border:1px solid #30363d; border-radius:8px; padding:0.5rem; margin-top:0.5rem; max-height:200px; overflow:auto;"></div>
</section>

<script>
// --- Enable/disable Start button --------------------------------------------
const pathInput = document.getElementById('videoPath');
const startBtn = document.getElementById('startButton');
const picker = document.getElementById('picker');

function updateButtonState() {
  const value = pathInput.value.trim();
  startBtn.disabled = (value === '');
  startBtn.style.opacity = startBtn.disabled ? '0.5' : '1';
  startBtn.style.cursor = startBtn.disabled ? 'not-allowed' : 'pointer';
}
updateButtonState();
pathInput.addEventListener('input', updateButtonState);

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
