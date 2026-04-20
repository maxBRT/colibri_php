---
id: contribute-sources
sidebar_position: 2
title: Add a Feed Source
description: How to add a new RSS feed source to Colibri.
---

Colibri aggregates posts from various RSS sources. If you know of a quality source that should be included, follow the guidelines below.

## Source Criteria

Before suggesting a source, ensure it meets these criteria:

- **Active**: Publishes new content regularly (at least monthly)
- **RSS Available**: Provides a valid RSS or Atom feed
- **Relevant**: Content aligns with Colibri's focus areas
- **Quality**: Original content, not just aggregators

## Submission Process

To add a new source:

1. **Find the RSS feed URL** - Usually found at `/feed`, `/rss`, or via the site's footer
2. **Verify the feed** - Use a feed validator to ensure it's properly formatted
3. **Open an issue** - Create a GitHub issue with:
   - Source name
   - RSS feed URL
   - Category suggestion
   - Brief description of content

## Technical Details

Sources are stored in the `sources` database table with the following fields:

- `name` - Display name of the source
- `url` - Website URL
- `feed_url` - RSS/Atom feed URL
- `category_id` - Associated category
- `is_active` - Whether the source is currently fetched

The `FetchRssJob` runs every 4 hours to pull new posts from active sources.

## Questions?

Open an issue on GitHub and tag it with `source-request`.
