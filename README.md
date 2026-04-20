<!-- PROJECT LOGO -->
<br />
<div align="center">
    <img src="images/colibri_logo.png" alt="Logo" width="400" height="400">
</div>

<!-- ABOUT THE PROJECT -->
## About The Project

Dealing with RSS feed is messy and unconsistent. Colibri aims to offer a simple but consistent JSON API to build around RSS feed.

How ?:
* We fetch from a list of RSS feeds every 4 hours and save the latest posts
* We generate incosistant field like description with AI agents
* We expose everything through a versionned REST API so your apps don't break

Make sure to check out the [docs](https://colibri-rss.com) !

<!-- ROADMAP -->
## Roadmap

- [ ] Grow the list of registered feed
- [ ] Add support for user facing feed registration

See the [open issues](https://github.com/maxbrt/colibri_php) for a full list of proposed features (and known issues).

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

See [the official docs](https://colibri-rss.com) for contribution guidelines.

<!-- LICENSE -->
## License

Distributed under the MIT License. See [LICENSE](./LICENSE) for more information.
