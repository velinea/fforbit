import fs from "fs";

// helpers
export function parseDuration(tagValue) {
  if (!tagValue) return 0;
  // Support hh:mm:ss(.ms) or hh:mm:ss:fr
  const parts = tagValue.split(":").map(Number);
  if (parts.length < 3) return 0;
  const [h, m, s] = parts;
  return h * 3600 + m * 60 + s;
}

export function getFileSize(path) {
  try {
    const { size } = fs.statSync(path);
    return size;
  } catch (e) {
    console.error("Cannot stat file:", e);
    return 0;
  }
}

export function computeAvgMbps(info, filePath) {
  const video = info.streams.find(s => s.codec_type === "video");
  const size = getFileSize(filePath);

  // 1️⃣ Duration from tags.DURATION
  let duration = parseDuration(video?.tags?.DURATION);

  // 2️⃣ Otherwise from container
  if (!duration && info.format?.duration) {
    duration = Number(info.format.duration);
  }

  // 3️⃣ Compute average bitrate if possible
  if (duration > 0 && size > 0) {
    return (size * 8) / duration / 1e6; // Mb/s
  }

  // 4️⃣ Fallback: use stream bitrate if available
  const streamBr = Number(video?.bit_rate || info.format?.bit_rate || 0);
  return streamBr / 1e6; // already in bits/sec
}


export function autoQuality(avgMbps, codec = "hevc") {
  if (avgMbps < 2) return 19;
  if (avgMbps < 3) return 21;
  if (avgMbps < 4) return 23;
  if (avgMbps < 6) return 25;
  if (avgMbps < 8) return 27;
  return 28;
}
