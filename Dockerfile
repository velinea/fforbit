# syntax=docker/dockerfile:1
FROM lscr.io/linuxserver/ffmpeg:latest

LABEL org.opencontainers.image.source="https://github.com/<you>/fforbit-php"
WORKDIR /app

# PHP + VAAPI tools on Alpine
RUN apk add --no-cache \
      php81 php81-cli php81-xml php81-mbstring \
      libva-utils intel-media-driver

COPY index.php /app/
COPY transcode.sh /usr/local/bin/transcode.sh
RUN chmod +x /usr/local/bin/transcode.sh

EXPOSE 8080
CMD ["php81", "-d", "expose_php=0", "-S", "0.0.0.0:8080", "-t", "/app"]
