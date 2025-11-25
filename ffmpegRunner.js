// ffmpegRunner.js
import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';

const CONFIG_DIR = process.env.CONFIG_DIR || '/app/config';
const TMP_DIR = process.env.TMP_DIR || '/data/media/Downloads/tmp';

export function createRunner({ ffmpegPath, vaapi, job, onData, onExit }) {
  const o = job.opts || {};
  job.stoppedByUser = false;
  // derive options from probe/UI:
  // - remove extra streams, keep v:0 + chosen a:X
  const keepAudio = Number(o.audioIndex ?? 0);

  // video transcode decision
  const transcodeVideo = o.transcodeVideo ?? true; // UI will set based on probe (skip if hevc)
  const gq = Number(o.globalQuality ?? 25);

  // audio transcode decision
  const transcodeAudio = o.transcodeAudio ?? false; // UI sets to false if AAC already
  const lang = o.audioLang || 'eng';

  const baseArgs = [
    '-hide_banner',
    '-vaapi_device',
    vaapi,
    '-i',
    job.path,
    '-map',
    '0:v:0',
    '-map',
    `0:a:${keepAudio}`,
    '-metadata',
    `title=${folderName(job.path)}`,
    '-metadata:s:a:0',
    `language=${lang}`,
  ];

  const vArgs = transcodeVideo
    ? [
        '-vf',
        'format=nv12,hwupload',
        '-c:v',
        'hevc_vaapi',
        '-rc_mode',
        'CQP',
        '-global_quality',
        String(gq),
        '-profile:v',
        'main',
      ]
    : ['-c:v', 'copy'];

  const aArgs = transcodeAudio ? ['-c:a', 'aac', '-ac', '6'] : ['-c:a', 'copy'];

  // ALWAYS drop extras: no subtitles/images/other streams
  const outPath = tempOut(job.path);
  const args = [...baseArgs, ...vArgs, ...aArgs, '-y', outPath];

  onData(`🎬 ffmpeg ${args.join(' ')}\n`);

  const child = spawn(ffmpegPath, args);

  job._proc = child;

  const onLine = buf => {
    const s = buf.toString('utf8');
    s.split(/\r?\n/).forEach(line => line && onData(line));
  };

  child.stdout.on('data', onLine);
  child.stderr.on('data', onLine);

  child.on('close', (code, signal) => {
    try {
      if (job.stoppedByUser) {
        // User cancelled → never keep partial output
        fs.rmSync(outPath, { force: true });
        onData('⏹️ cancelled by user — partial output discarded');
      } else {
        // Normal ffmpeg exit → your original logic
        const stats = fs.statSync(outPath);
        if (stats.size > 1_000_000) {
          try {
            fs.renameSync(outPath, job.path);
          } catch (e) {
            fs.copyFileSync(outPath, job.path);
            fs.rmSync(outPath);
          }
          fixPermissions(job.path);
        } else {
          fs.rmSync(outPath, { force: true });
          onData('❌ output invalid/too small — original kept');
        }
      }
    } catch (e) {
      onData(`⚠️ post-processing: ${e}`);
    }

    onExit(code, signal);
  });

  return {
    stop() {
      // NEW: mark job as user-cancelled
      job.stoppedByUser = true;

      try {
        child.kill('SIGTERM');
      } catch {}
    },
  };
}

export function ffprobeOnce(ffprobePath, targetPath) {
  return new Promise((resolve, reject) => {
    const args = [
      '-v',
      'error',
      '-show_entries',
      'stream=index,codec_type,codec_name,bit_rate,duration,channels:stream_tags',
      '-of',
      'json',
      targetPath,
    ];
    const child = spawn(ffprobePath, args);
    let out = '',
      err = '';
    child.stdout.on('data', d => (out += d.toString()));
    child.stderr.on('data', d => (err += d.toString()));
    child.on('close', code => {
      if (code === 0) {
        try {
          resolve(JSON.parse(out));
        } catch (e) {
          reject(e);
        }
      } else {
        reject(err || `ffprobe exit ${code}`);
      }
    });
  });
}

function tempOut(p) {
  const extension = path.extname(p);
  const base = path.basename(p, extension);
  return path.join(TMP_DIR, `${base}.fforbit.tmp.${extension}`);
}

function folderName(p) {
  return path.basename(path.dirname(p));
}
async function fixPermissions(path) {
  await fs.promises.chown(path, 99, 100); // nobody:users
  await fs.promises.chmod(path, 0o666); // rw-rw-rw-
}
