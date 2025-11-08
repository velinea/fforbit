// server.js
import express from "express";
import { createRunner, ffprobeOnce } from "./ffmpegRunner.js";
import fs from "fs";
import path from "path";

const app = express();
app.use(express.json());
app.use(express.static("public"));

const FFMPEG = "/usr/bin/ffmpeg";
const FFPROBE = "/usr/bin/ffprobe";
const MEDIA_ROOTS = [
  "/data/media/Downloads/complete/Movies",
  "/data/media/Movies",
  "./test"
];

let nextId = 1;
const queue = []; // {id, path, opts, state, logBuffer, clients: Set(res)}
let current = null;

/**
 * Runs the next job in the queue.
 * The `job` parameter passed to `createRunner` should be an object with the following structure:
 * {
 *   id: number,
 *   path: string,
 *   opts: object,
 *   state: string,
 *   logBuffer: Array<string>,
 *   clients: Set<ServerResponse>,
 *   stop?: Function
 * }
 * This object represents a media processing job and is used by the runner to track its state and communicate with clients.
 */

function runNext() {
  if (current || queue.length === 0) return;
  current = queue.shift();
  current.state = "running";

  const runner = createRunner({
    ffmpegPath: FFMPEG,
    vaapi: "/dev/dri/renderD128",
    job: current,
    onData: line => broadcast(current, line),
    onExit: (code, signal) => {
      broadcast(current, `\n🏁 finished (code=${code}, sig=${signal})`);
      // flush and close all SSE clients
      closeClients(current);
      current = null;
      runNext();
    }
  });

  current.stop = () => runner.stop();
}

// ---- SSE helpers ----
function broadcast(job, line) {
  job.logBuffer.push(line);
  for (const res of job.clients) {
    res.write(`data: ${line.replace(/\n/g, "")}\n\n`);
  }
  // avoid unbounded memory
  if (job.logBuffer.length > 5000) job.logBuffer.splice(0, 4000);
}
function closeClients(job) {
  for (const res of job.clients) try { res.end(); } catch {}
  job.clients.clear();
}

// ---- SEARCH (fuzzy-ish) ----
// finds after 3+ chars, across configured roots, newest first
app.get("/api/search", async (req, res) => {
  const q = (req.query.q || "").trim();
  if (q.length < 3) return res.json([]);
  // use find via shell to leverage the filesystem efficiently
  const child_process = await import("child_process");
  const { exec } = child_process.default;
  const patterns = ["*.mkv", "*.mp4", "*.avi"];
  const roots = MEDIA_ROOTS.filter(r => fs.existsSync(r));
  if (!roots.length) return res.json([]);

  const cmds = roots.map(root =>
    `find ${JSON.stringify(root)} -type f \\( ${patterns.map(p => `-iname '${p}'`).join(" -o ")} \\) -printf '%T@ %p\\n'`
  );
  exec(cmds.join(" ; ") + " | sort -nr | cut -d' ' -f2-", (err, stdout) => {
    if (err) return res.json([]);
    const all = stdout.split("\n").filter(Boolean);
    const lower = q.toLowerCase();
    // very simple fuzzy: contains, ignoring case; rank by filename match closeness
    const hits = all
      .map(p => ({ p, name: path.basename(p) }))
      .filter(x => x.name.toLowerCase().includes(lower))
      .slice(0, 60);
    res.json(hits);
  });
});

// ---- PROBE (single shot) ----
app.get("/api/probe", async (req, res) => {
  const p = (req.query.path || "").trim();
  if (!p) return res.status(400).json({ error: "no path" });

  try {
    const info = await ffprobeOnce(FFPROBE, p);
    // summarize what UI needs
    const v = info.streams.find(s => s.codec_type === "video") || {};
    const aStreams = info.streams.filter(s => s.codec_type === "audio");
    const format = info.format || {};

    const audioList = aStreams.map((s, idx) => ({
      index: s.index,
      codec: s.codec_name,
      channels: s.channels,
      lang: (s.tags && (s.tags.language || s.tags.LANGUAGE)) || "und",
      default: s.disposition && s.disposition["default"] === 1
    }));

    res.json({
      format: {
        duration: Number(format.duration || 0),
        size: Number(format.size || 0),
        bit_rate: Number(format.bit_rate || 0)
      },
      video: {
        codec: v.codec_name,
        width: v.width,
        height: v.height,
        pix_fmt: v.pix_fmt
      },
      audio: audioList
    });
  } catch (e) {
    res.status(500).json({ error: "probe failed", detail: String(e) });
  }
});

// ---- ENQUEUE ----
app.post("/api/enqueue", (req, res) => {
  const { path: p, opts } = req.body || {};
  if (!p) return res.status(400).json({ error: "no path" });

  const id = nextId++;
  const job = {
    id,
    path: p,
    opts: opts || {},
    state: "queued",
    logBuffer: [],
    clients: new Set()
  };
  queue.push(job);
  runNext();
  res.json({ id, state: job.state });
});

// ---- STOP ----
app.post("/api/stop/:id", (req, res) => {
  const id = Number(req.params.id);
  if (current && current.id === id && current.stop) {
    current.stop();
    return res.json({ ok: true });
  }
  // allow cancelling queued job
  const idx = queue.findIndex(j => j.id === id);
  if (idx >= 0) {
    queue.splice(idx, 1);
    return res.json({ ok: true, cancelled: true });
  }
  res.status(404).json({ error: "job not found/running" });
});

// ---- QUEUE STATUS ----
app.get("/api/queue", (req, res) => {
  res.json({
    running: current ? { id: current.id, path: current.path, state: current.state } : null,
    queued: queue.map(j => ({ id: j.id, path: j.path, state: j.state }))
  });
});

// ---- SSE LOG STREAM ----
app.get("/api/log/:id", (req, res) => {
  const id = Number(req.params.id);
  const job = (current && current.id === id) ? current : queue.find(j => j.id === id);
  if (!job) return res.status(404).end();

  res.writeHead(200, {
    "Content-Type": "text/event-stream",
    "Cache-Control": "no-cache",
    Connection: "keep-alive"
  });

  // send history first
  for (const line of job.logBuffer) {
    res.write(`data: ${line.replace(/\n/g, "")}\n\n`);
  }
  job.clients.add(res);

  req.on("close", () => {
    job.clients.delete(res);
  });
});

const PORT = process.env.PORT || 5000;
app.listen(PORT, () => console.log(`FFOrbit API listening on :${PORT}`));
