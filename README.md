# Colibri RSS

A REST API that bridges the gap between traditional RSS feeds and modern application requirements.

## About

While RSS remains the backbone of open-form content on the web, its implementation is often fragmented and inconsistent. Colibri offers a consistent, unified interface that allows developers to integrate feeds or specific posts from various sources directly into their applications without the headache of managing multiple XML formats or custom parsers.

By handling the heavy lifting of polling and content normalization, Colibri lets you focus on building features, not infrastructure.

## Features

- **Automated Fetching**: High-frequency synchronization engine polls registered sources every 4 hours
- **Intelligent Parsing**: Normalizes feeds into a single, predictable JSON schema
- **AI-Powered Enrichment**: Uses LLMs to analyze content and generate concise, meaningful descriptions
- **REST API**: Clean, documented endpoints for sources, posts, and categories
- **Pagination**: Built-in pagination for posts endpoint

## Quick Start

```bash
# Clone the repository
git clone https://github.com/maxBRT/colibri_php.git
cd colibri_php

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure SQLite in .env:
# DB_CONNECTION=sqlite

# Run migrations
php artisan migrate

# Seed sources (optional)
php artisan db:seed

# Start the server
php artisan serve
```

## API Endpoints

- `GET /api/v1/categories` - List all unique categories
- `GET /api/v1/sources` - List all RSS sources (optionally filter by category)
- `GET /api/v1/posts` - List posts with pagination (optionally filter by source)
- `GET /api/health` - Health check

## Documentation

Full API documentation is available in the [docs](https://colibri-rss.com). 

## Development

```bash
# Run tests
php artisan test --compact

# Check code style
vendor/bin/pint
```

See [the official docs](https://colibri-rss.com) for contribution guidelines.

## License

MIT License
