FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:20-alpine AS runtime
WORKDIR /app
# Install a lightweight static server
RUN npm install -g serve
COPY --from=builder /app/build ./build

# Dokploy will point Traefik to this port
EXPOSE 3000
CMD ["serve", "-s", "build", "-l", "3000"]
