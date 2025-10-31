<section id="log">
  <h2>Live Log</h2>
  <pre id="logText">(waiting...)</pre>
</section>

<script>
function refreshLog() {
  fetch('api.php?action=log&t=' + Date.now())
    .then(r => r.text())
    .then(txt => {
      const logEl = document.getElementById('logText');
      logEl.textContent = txt;
      logEl.scrollTop = logEl.scrollHeight;
    });
}
setInterval(refreshLog, 2000);
window.onload = refreshLog;
</script>
