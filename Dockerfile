# Multi-stage build for a lean production image.
# Uses Node 22 LTS (with --experimental-sqlite flag needed for built-in SQLite).
# Upgrade to Node 24+ to drop the flag once it reaches LTS.

FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --omit=dev

# ── Runtime image ─────────────────────────────────────────────────────────────
FROM node:22-alpine
WORKDIR /app

# Create data directory with correct ownership
RUN mkdir -p /data && chown node:node /data

COPY --from=deps /app/node_modules ./node_modules
COPY src/ ./src/
COPY public/ ./public/
COPY package.json ./

# Run as non-root
USER node

ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=3000 \
    DB_PATH=/data/tasks.db

EXPOSE 3000

# Mount /data as a volume to persist the SQLite file across container restarts
VOLUME ["/data"]

CMD ["node", "--experimental-sqlite", "src/server.js"]
