const $ = sel => document.querySelector(sel);
const results = $("#results");
const options = $("#options");
const queueDiv = $("#queue");
const logSec = $("#logSec");
const logPre = $("#log");
let picked = null;
let currentJobId = null;
let es = null;

function debounce(fn, ms) {
  let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

$("#search").addEventListener("input", debounce(async (e) => {
  const q = e.target.value.trim();
  results.innerHTML = "";
  if (q.length < 3) return;
  const res = await fetch(`/api/search?q=${encodeURIComponent(q)}`).then(r=>r.json());
  if (!res.length) { results.textContent = "(no matches)"; return; }
  results.innerHTML = res.map(x => `<div class="hit" data-path="${x.p}">🎬 ${x.name}</div>`).join("");
}, 300));

results.addEventListener("click", async (e) => {
  const el = e.target.closest(".hit");
  if (!el) return;
  const p = el.dataset.path;
  picked = { path: p };
  $("#picked").textContent = p;

  // probe once and show options
  const info = await fetch(`/api/probe?path=${encodeURIComponent(p)}`)
    .then(r=>r.json());
  // set defaults based on probe
  // video: skip if hevc or bitrate already low
  $("#transVideo").checked = !(info.video && (info.video.codec === "hevc" || info.format.avgMbps < 3));
  // audio: show tracks
  const aSel = $("#audioIndex");
  aSel.innerHTML = (info.audio || []).map((a,i) =>
    `<option value="${a.mapIndex}" ${a.default?'selected':''}>
    #${a.mapIndex} ${a.codec||''} ${a.channels||''}ch ${a.lang||'und'}${a.default?' (default)':''}</option>`
  ).join("");
  // if selected audio is already AAC, uncheck transAudio
  const def = (info.audio || [])[aSel.selectedIndex || 0];
  $("#transAudio").checked = !(def && (def.codec === "aac" || def.codec === "ac3" || def.codec === "eac3"));
  $("#audioLang").value = (def && def.lang) || "eng";

  const fmt = info.format;
  const cq = fmt.suggestedCQ;
  const avg = fmt.avgMbps?.toFixed(2);

  // Show info text
  document.getElementById("bitrateInfo").textContent =
    `🎞 ${avg} Mb/s  →  Suggested GQ ${cq}`;

  // Set slider / input automatically
  const cqInput = document.getElementById("gq");
  if (cqInput) cqInput.value = cq;

  // Color-code efficiency
  const c = avg < 2 ? "limegreen" :
            avg < 4 ? "gold" :
            avg < 6 ? "orange" : "crimson";
  cqInput.style.borderColor = c;

  options.style.display = "";
});

$("#enqueue").addEventListener("click", async () => {
  if (!picked) return;
  const body = {
    path: picked.path,
    opts: {
      audioIndex: Number($("#audioIndex").value),
      audioLang: $("#audioLang").value.trim() || "eng",
      transcodeVideo: $("#transVideo").checked,
      transcodeAudio: $("#transAudio").checked,
      globalQuality: Number($("#gq").value || 29)
    }
  };
  const res = await fetch("/api/enqueue", { method:"POST", headers:{ "Content-Type": "application/json" }, body: JSON.stringify(body) }).then(r=>r.json());
  currentJobId = res.id;
  startLogSSE(res.id);
  refreshQueue();
});

$("#stop").addEventListener("click", async () => {
  if (!currentJobId) return;
  await fetch(`/api/stop/${currentJobId}`, { method:"POST" });
});

async function refreshQueue() {
  const q = await fetch("/api/queue").then(r=>r.json());
  queueDiv.innerHTML = `
    <div>${q.running ? `#${q.running.id} – ${q.running.path}` : ""}</div>
    <ul>${q.queued.map(j => `<li>#${j.id} – ${j.path}</li>`).join("") || "<li></li>"}</ul>
  `;
}
setInterval(refreshQueue, 2000);
refreshQueue();

function startLogSSE(id) {
  if (es) es.close();
  es = new EventSource(`/api/log/${id}`);
  logSec.style.display = "";
  logPre.textContent = "";
  es.onmessage = (ev) => {
    logPre.textContent += ev.data + "\n";
    logPre.scrollTop = logPre.scrollHeight;
  };
  es.onerror = () => { /* stream will end when job finishes; hide if needed */ };
}