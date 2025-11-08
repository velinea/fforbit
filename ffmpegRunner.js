// ffmpegRunner.js
import { spawn } from "child_process";
import path from "path";
import fs from "fs";

export function createRunner({ ffmpegPath, vaapi, job, onData, onExit }) {
  const o = job.opts || {};

  // derive options from probe/UI:
  // - remove extra streams, keep v:0 + chosen a:X
  const keepAudio = Number(o.audioIndex ?? 0);

  // video transcode decision
  const transcodeVideo = o.transcodeVideo ?? true; // UI will set based on probe (skip if hevc)
  const gq = Number(o.globalQuality ?? 29);

  // audio transcode decision
  const transcodeAudio = o.transcodeAudio ?? false; // UI sets to false if AAC already
  const lang = o.audioLang || "eng";

  const baseArgs = [
    "-hide_banner",
    "-i", job.path,
    "-map", "0:v:0",
    "-map", `0:a:${keepAudio}`,
    "-metadata", `title=${folderName(job.path)}`,
    "-metadata:s:a:0", `language=${lang}`
  ];

  const vArgs = transcodeVideo
    ? ["-vf", "vaapi_device", vaapi, "format=nv12,hwupload", "-c:v", "hevc_vaapi", "-rc_mode", "CQP", "-global_quality", String(gq), "-profile:v", "main"]
    : ["-c:v", "copy"];

  const aArgs = transcodeAudio
    ? ["-c:a", "aac", "-ac", "6"]
    : ["-c:a", "copy"];

  // ALWAYS drop extras: no subtitles/images/other streams
  const outPath = tempOut(job.path);
  const args = [...baseArgs, ...vArgs, ...aArgs, "-y", outPath];

  onData(`🎬 ffmpeg ${args.join(" ")}\n`);

  const child = spawn(ffmpegPath, args);

  job._proc = child;

  const onLine = (buf) => {
    const s = buf.toString("utf8");
    s.split(/\r?\n/).forEach(line => line && onData(line));
  };

  child.stdout.on("data", onLine);
  child.stderr.on("data", onLine);

  child.on("close", (code, signal) => {
    // only replace original if output is good (> 1MB)
    try {
      const stats = fs.statSync(outPath);
      if (stats.size > 1_000_000) {
        fs.renameSync(outPath, job.path);
      } else {
        fs.rmSync(outPath, { force: true });
        onData("❌ output invalid/too small — original kept");
      }
    } catch (e) {
      onData(`⚠️ post-processing: ${e}`);
    }
    onExit(code, signal);
  });

  return {
    stop() {
      try { child.kill("SIGTERM"); } catch {}
    }
  };
}

export function ffprobeOnce(ffprobePath, targetPath) {
  return new Promise((resolve, reject) => {
    const args = [
      "-v", "error",
      "-show_entries", "format=duration,size,bit_rate:stream=index,codec_type,codec_name,width,height,pix_fmt,channels,disposition,tags",
      "-of", "json",
      targetPath
    ];
    const child = spawn(ffprobePath, args);
    let out = "", err = "";
    child.stdout.on("data", d => out += d.toString());
    child.stderr.on("data", d => err += d.toString());
    child.on("close", code => {
      if (code === 0) {
        try { resolve(JSON.parse(out)); }
        catch (e) { reject(e); }
      } else {
        reject(err || `ffprobe exit ${code}`);
      }
    });
  });
}

function tempOut(p) {
  const dir = path.dirname(p);
  const base = path.basename(p).replace(/\.(mkv|mp4|avi)$/i, "");
  return path.join(dir, `${base}.fforbit.tmp.mkv`);
}

function folderName(p) {
  return path.basename(path.dirname(p));
}
