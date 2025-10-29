#!/bin/bash
# Usage: transcode.sh /path/to/video track lang transcode_video transcode_audio quality
# Example: transcode.sh /data/media/Movie.mkv 0 eng y n 23

set -e

FILE="$1"
ATRACK="${2:-0}"
LANG="${3:-eng}"
TR_VIDEO="${4:-n}"
TR_AUDIO="${5:-n}"
GQ="${6:-23}"

VIDEOMAP="-c:v copy"
if [ "$TR_VIDEO" = "y" ]; then
  VIDEOMAP="-vf format=nv12,hwupload -c:v hevc_vaapi -rc_mode CQP -global_quality $GQ -profile:v main"
fi

AUDIOMAP="-c:a copy"
if [ "$TR_AUDIO" = "y" ]; then
  AUDIOMAP="-c:a aac -ac 6"
fi

EXT="${FILE##*.}"
TMP="/data/media/tmp/tmp.$EXT"
MOVIE=$(basename "$(dirname "$FILE")")

echo "🎬 Transcoding: $FILE"
echo "Audio track: $ATRACK  Language: $LANG"
echo "Video: $TR_VIDEO  Audio: $TR_AUDIO  Quality: $GQ"

mkdir -p /data/media/tmp
[ -f "$TMP" ] && rm "$TMP"

/usr/lib/jellyfin-ffmpeg/ffmpeg -vaapi_device /dev/dri/renderD128 \
  -i "$FILE" -metadata Title="$MOVIE" -metadata Comment="" \
  -metadata:s:a:0 language="$LANG" \
  -map 0:v:0 -map 0:a:$ATRACK $AUDIOMAP $VIDEOMAP "$TMP"

mv "$TMP" "$FILE"
chown 99:100 "$FILE"
chmod a+w "$FILE"
echo "✅ Done: $FILE"
