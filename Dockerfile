# syntax=docker/dockerfile:1
FROM php:8.3-apache

LABEL maintainer="velinea"
LABEL org.opencontainers.image.source="https://github.com/velinea/fforbit-php"

# Install ffmpeg and bash
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg bash && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy your FFmpeg wrapper script
COPY transcode.sh /usr/local/bin/transcode.sh
RUN chmod +x /usr/local/bin/transcode.sh

# Copy the web UI
COPY index.php /var/www/html/

# Apache will serve from port 80
EXPOSE 80

# Default entrypoint
CMD ["apache2-foreground"]
