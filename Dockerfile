# syntax=docker/dockerfile:1

# Base image: Jellyfin for working ffmpeg + hw accel stack
FROM linuxserver/jellyfin:latest

LABEL maintainer="velinea"
LABEL org.opencontainers.image.source="https://github.com/velinea/fforbit"

# Disable the Jellyfin service from starting automatically
# Remove init references but keep ffmpeg binaries
RUN rm -f /etc/s6-overlay/s6-rc.d/user/contents.d/svc-jellyfin \
    /etc/s6-overlay/s6-rc.d/init-jellyfin-config \
    /etc/s6-overlay/s6-rc.d/init-jellyfin-video || true

# Install Node.js + npm (use Node 20 LTS)
RUN apt-get update && \
    apt-get install -y --no-install-recommends curl ca-certificates && \
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs vim && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Create app directory
WORKDIR /app

# Copy app source
COPY package*.json ./
RUN npm install --omit=dev

COPY . .

# Set default envs
ENV NODE_ENV=production \
    TMP_DIR=/app/config/tmp \
    PORT=5002

# Expose the port your Node app uses
EXPOSE 5002

# Ensure tmp exists
RUN mkdir -p /app/config/tmp

# Use node entrypoint
CMD ["node", "server.js"]


LABEL org.opencontainers.image.title="FFOrbit"
LABEL org.opencontainers.image.description="Simple FFmpeg Web UI."
LABEL org.opencontainers.image.licenses="MIT"
LABEL org.opencontainers.image.url="https://github.com/velinea/fforbit"
LABEL io.unraid.docker.icon="https://raw.githubusercontent.com/velinea/fforbit/main/fforbit/fforbit.png"

