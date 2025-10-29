# syntax=docker/dockerfile:1
FROM php:8.3-fpm-alpine:latest

LABEL org.opencontainers.image.source="https://github.com/velinea/fforbit-php"
WORKDIR /app

# PHP + VAAPI tools on Alpine
RUN apk add --no-cache \
#      php81 php81-cli php81-xml php81-mbstring 
      libva libdrm ffmpeg

COPY index.php /app/
COPY transcode.sh /usr/local/bin/transcode.sh
RUN chmod +x /usr/local/bin/transcode.sh

EXPOSE 8080
CMD ["php83", "-d", "expose_php=0", "-S", "0.0.0.0:8080", "-t", "/app"]
