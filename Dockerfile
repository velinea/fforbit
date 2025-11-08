# syntax=docker/dockerfile:1
FROM lscr.io/linuxserver/jellyfin:latest

LABEL maintainer="velinea"
LABEL org.opencontainers.image.source="https://github.com/velinea/fforbit"
LABEL org.opencontainers.image.description="FFOrbit – lightweight FFmpeg web UI with hardware acceleration"

WORKDIR /app

# Remove heavy Jellyfin components
# RUN rm -rf /etc/services.d/jellyfin

# Install PHP (lightweight, no Apache)
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
            vim bc php-cli php-common php-xml php-mbstring && \
    apt-get clean 

# Copy your UI and script
COPY index.php /app/
COPY style.css /app/
COPY fforbit.png /app/
COPY api.php /app/
COPY transcode.sh /app
COPY includes/ /app/includes/
RUN chmod +x /app/transcode.sh

# Create config directory (for logs and defaults)
RUN mkdir -p /app/config && chmod -R 777 /app/config
VOLUME ["/app/config"]

# Stop s6 from trying to start user services
RUN rm -rf /etc/s6-overlay/s6-rc.d/user/contents.d/*

# Clean up to shrink image size
RUN rm -rf /usr/share/doc /usr/share/man /usr/share/locale /tmp/* /var/tmp/*

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD curl -fs http://localhost:8080/health || exit 1
# Reset log on container start
RUN echo "🛰️ FFOrbit ready — no active jobs." > /app/config/last.log

EXPOSE 8080
ENTRYPOINT []
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]

LABEL org.opencontainers.image.title="FFOrbit"
LABEL org.opencontainers.image.description="Simple FFmpeg Web UI."
LABEL org.opencontainers.image.licenses="MIT"
LABEL org.opencontainers.image.url="https://github.com/velinea/fforbit"
LABEL io.unraid.docker.icon="https://raw.githubusercontent.com/velinea/fforbit/main/fforbit/fforbit.png"

