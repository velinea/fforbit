# syntax=docker/dockerfile:1
FROM lscr.io/linuxserver/jellyfin:latest

LABEL maintainer="velinea"
LABEL org.opencontainers.image.source="https://github.com/velinea/fforbit-php"

WORKDIR /app

# Remove heavy Jellyfin components
RUN apt-get /etc/services.d/jellyfin

# Install PHP (lightweight, no Apache)
RUN apt-get update && \
    apt-get install -y --no-install-recommends vim php-cli php-common php-xml php-mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy your UI and script
COPY index.php /app/
COPY transcode.sh /usr/local/bin/transcode.sh
RUN chmod +x /usr/local/bin/transcode.sh

# Strip docs and man pages for smaller image
RUN rm -rf /usr/share/doc /usr/share/man /usr/share/locale /tmp/* /var/tmp/*

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
