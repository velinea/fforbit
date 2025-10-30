#!/bin/bash
set -euo pipefail

usage() {
  echo "Usage: $0 /full/path/to/video"
  exit 1
}

# --- Check if interactive ----------------------------------------------------
INTERACTIVE=false
if [ -t 0 ]; then
  INTERACTIVE=true
fi

# --- Temp handling -----------------------------------------------------------
TMPDIR="/app/config/tmp"
mkdir -p "$TMPDIR"
TMPFILE="$TMPDIR/$(basename "$1").$$.tmp.mkv"

# --- Argument check ----------------------------------------------------------
if $INTERACTIVE; then
  [ $# -eq 1 ] || usage
fi

# --- Helper for prompts ------------------------------------------------------
ask() {
  local prompt=$1 default=$2 var
  if $INTERACTIVE; then
    read -rp "$prompt [$default]: " var
    echo "${var:-$default}"
  else
    echo "$default"
  fi
}

# --- Sanity checks -----------------------------------------------------------
VIDEO_CODEC=$(/usr/lib/jellyfin-ffmpeg/ffprobe -v error -select_streams v:0 \
  -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 "$1" | head -n1)

AUDIO_CODEC=$(/usr/lib/jellyfin-ffmpeg/ffprobe -v error -select_streams a:0 \
  -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 "$1" | head -n1)

FILE_SIZE=$(stat -c%s "$1")
MIN_SIZE=$((300 * 1024 * 1024))   # 300 MB threshold, configurable later

if [[ "$VIDEO_CODEC" == "hevc" ]]; then
  echo "ℹ️  Video already H.265 — skipping video transcode."
  TR="n"
fi

if [[ "$AUDIO_CODEC" == "aac" ]]; then
  echo "ℹ️  Audio already AAC — skipping audio transcode."
  AAC="n"
fi

if (( FILE_SIZE < MIN_SIZE )); then
  echo "⚠️  File smaller than expected ($((FILE_SIZE/1024/1024)) MB)."
  if $INTERACTIVE; then
    read -n1 -p "Continue anyway? [y/N]: " ANS
    [[ "${ANS:-N}" == "y" ]] || exit 0
  else
    echo "Proceeding (non-interactive mode)."
  fi
fi


# --- Collect options ---------------------------------------------------------
TR=$(ask "Transcode video (y/N)" "y")
ATRACK=$(ask "Original language audio track" "0")
LANG=$(ask "Audio language code" "eng")
AAC=$(ask "Transcode audio (y/N)" "n")

VIDEOMAP="-c:v copy"
AUDIOMAP="-c:a copy"
[[ "$AAC" == "y" ]] && AUDIOMAP="-c:a aac -ac 6"

# --- Paths -------------------------------------------------------------------
FILE=$(basename "$1")
MOVIE=$(basename "$(dirname "$1")")
EXT="${FILE##*.}"

# --- Analyze source bitrate --------------------------------------------------
SRC_BITRATE_RAW=$(/usr/lib/jellyfin-ffmpeg/ffprobe -v error \
  -select_streams v:0 -show_entries stream=bit_rate \
  -of default=noprint_wrappers=1:nokey=1 "$1" || echo 0)
SRC_MBPS=$(awk "BEGIN {printf \"%.1f\", $SRC_BITRATE_RAW / 1000000}")
echo "📊 Source bitrate: ${SRC_MBPS} Mb/s"

# Suggest global_quality (≈3 Mb/s target)
if (( $(echo "$SRC_MBPS <= 4" | bc -l) )); then SUGQ=23
elif (( $(echo "$SRC_MBPS <= 8" | bc -l) )); then SUGQ=25
elif (( $(echo "$SRC_MBPS <= 12" | bc -l) )); then SUGQ=27
elif (( $(echo "$SRC_MBPS <= 20" | bc -l) )); then SUGQ=29
else SUGQ=31; fi

if [[ "$TR" == "y" ]]; then
  GQ=$(ask "Global quality" "$SUGQ")
  VIDEOMAP="-vf format=nv12,hwupload -c:v hevc_vaapi \
    -rc_mode CQP -global_quality $GQ -profile:v main"
else
  GQ=$SUGQ
fi

echo "💡 Using global_quality=$GQ (suggested $SUGQ)"


# --- Start --------------------------------------------------------------------
echo "🎬 Transcoding: $MOVIE"
echo

# --- Run ffmpeg --------------------------------------------------------------
echo $$ > /app/config/current.pid
/usr/lib/jellyfin-ffmpeg/ffmpeg -hide_banner -vaapi_device /dev/dri/renderD128 \
  -i "$1" \
  -metadata Title="$MOVIE" -metadata Comment="" \
  -metadata:s:a:0 language=$LANG \
  -map 0:v:0 -map 0:a:$ATRACK $AUDIOMAP $VIDEOMAP \
  "$TMPFILE"

# --- Analyze result bitrate --------------------------------------------------
BITRATE_RAW=$(/usr/lib/jellyfin-ffmpeg/ffprobe -v error \
  -select_streams v:0 -show_entries stream=bit_rate \
  -of default=noprint_wrappers=1:nokey=1 "$TMPFILE" || echo 0)
BITRATE_MBPS=$(awk "BEGIN {printf \"%.2f\", $BITRATE_RAW / 1000000}")
echo "✅ Output bitrate: ${BITRATE_MBPS} Mb/s"

# --- Replace original --------------------------------------------------------
if [ -s "$TMPFILE" ]; then
  mv -f "$TMPFILE" "$1"
else
  echo "❌ Temp file invalid or zero bytes — original not replaced."
  rm -f "$TMPFILE"
  exit 1
fi

# --- Finalize ----------------------------------------------------------------
chown 99:100 "$1"
chmod a+w "$1"

echo "🏁 Transcode complete: $1"
