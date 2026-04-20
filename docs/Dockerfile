### Builder stage: install deps and generate static bundle
FROM node:20-alpine AS builder

WORKDIR /app

ENV NODE_ENV=production

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

### Runtime stage: serve static assets with nginx
FROM nginx:1.27-alpine AS runtime

# Remove default nginx assets to avoid leaking placeholder page
RUN rm -rf /usr/share/nginx/html/*

COPY --from=builder /app/build /usr/share/nginx/html

CMD ["nginx", "-g", "daemon off;"]
