#!/bin/bash
set -euo pipefail

usage() {
  echo "Usage: $0 /full/path/to/video"
  exit 1
}

[ $# -eq 1 ] || usage

# --- Ask basic options -------------------------------------------------------
read -n1 -p "Transcode video [y/N]: " TR
TR=${TR:-N}
VIDEOMAP="-c:v copy"

echo
read -n1 -p "Original language audio track [0]: " ATRACK
ATRACK=${ATRACK:-0}
echo
read -n3 -p "Audio language code [eng]: " LANG
LANG=${LANG:-eng}
echo
read -n1 -p "Transcode audio [y/N]: " AAC
AAC=${AAC:-N}
AUDIOMAP="-c:a copy"
[[ "$AAC" == "y" ]] && AUDIOMAP="-c:a aac -ac 6"
echo

# --- Path setup --------------------------------------------------------------
FILE=$(basename "$1")
MOVIE=$(basename "$(dirname "$1")")
EXT="${FILE##*.}"

# --- Analyze source bitrate --------------------------------------------------
SRC_BITRATE_RAW=$(/usr/lib/jellyfin-ffmpeg/ffprobe -v error \
  -select_streams v:0 -show_entries stream=bit_rate \
  -of default=noprint_wrappers=1:nokey=1 "$1" || echo 0)
SRC_MBPS=$(awk "BEGIN {printf \"%.1f\", $SRC_BITRATE_RAW / 1000000}")
echo "📊 Source bitrate: ${SRC_MBPS} Mb/s"

# Suggest global_quality to reach ≈3 Mb/s output
if (( $(echo "$SRC_MBPS <= 4" | bc -l) )); then
  SUGQ=23
elif (( $(echo "$SRC_MBPS <= 8" | bc -l) )); then
  SUGQ=25
elif (( $(echo "$SRC_MBPS <= 12" | bc -l) )); then
  SUGQ=27
elif (( $(echo "$SRC_MBPS <= 20" | bc -l) )); then
  SUGQ=29
else
  SUGQ=31
fi
echo "💡 Suggested global quality: $SUGQ (target ≈ 3 Mb/s output)"

if [[ "$TR" == "y" ]]; then
  read -n2 -p "Global quality [$SUGQ]: " GQ
  GQ=${GQ:-$SUGQ}
  VIDEOMAP="-vf format=nv12,hwupload \
    -c:v hevc_vaapi -rc_mode CQP -global_quality $GQ -profile:v main"
fi
echo

# --- Temp handling -----------------------------------------------------------
TMPDIR=${TMPDIR:-/tmp}
mkdir -p "$TMPDIR"
TMPFILE=$(mktemp --suffix=".$EXT" "$TMPDIR/fforbit.XXXXXX")
trap 'rm -f "$TMPFILE"' EXIT

echo "🎬 Transcoding: $MOVIE"
echo "Temp file: $TMPFILE"
echo

# --- Run ffmpeg --------------------------------------------------------------
/usr/lib/jellyfin-ffmpeg/ffmpeg -vaapi_device /dev/dri/renderD128 \
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
echo "✅ Average output bitrate: ${BITRATE_MBPS} Mb/s"

# --- Replace original --------------------------------------------------------
mv -f "$TMPFILE" "$1"
chown 99:100 "$1"
chmod a+w "$1"

echo "🏁 Transcode complete: $1"
