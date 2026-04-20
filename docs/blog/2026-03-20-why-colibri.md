---
slug: why-colibri
title: Why I built Colibri
authors: max
tags: [colibri, story]
---

Where did the idea for colibri came from and why I built it.

<!-- truncate -->

Let’s be real: RSS is cool but it's a little rough. The idea of only subsribing to sources you like is awesome in theory, but in pratice we are far from the seemless experience that the big attention sucking algorithim offer.

I built Colibri because RSS should feel automagic. It’s designed to do the heavy lifting so you can integrate feeds easily in your applications.

### The Problem: Information Overload
Standard RSS feeds are often a mess. Some provide the full article, while others offer only an html snippet. Some update every five minutes, others once a month. If you're a developer trying to integrate these into an app—like a stock tracker or a dev dashboard, you end up writing a dozen different parsers and still receiving noisy data. Worse yet, adding your tenth feed is often as much work as adding the first. As developers, we want to build well-oiled data machines that are lean, modular, and reusable.

### The Solution: A Consistent, Enriched API
Colibri acts as a middleman. It does three things very well:

1. **Normalization:** It polls your sources every 4 hours and turns fragmented XML/Atom/JSON feeds into a single, predictable JSON schema.
2. **AI Enrichment:** This is the "secret sauce." Instead of showing a cut-off sentence, Colibri uses an LLM to generate a concise, meaningful description of the post.
3. **Open Source & Free:** You can use our hosted version or spin up your own instance with Docker. If you want to add features or sources, its up to you !

### Why "Colibri"?
Colibris (hummingbirds) are fast, precise, and they move from source to source to extract that sweet sweet nectar. That’s exactly how we want our data pipeline to feel.

### What’s Next?
We are just getting started. Our [API Documentation](/docs/api/categories) is live, and we’re looking for contributors to help us refine our AI prompting and add support for more "tricky" non-standard sources.

If you’re tired of fighting with raw XML and want a clean stream of data for your next project, check us out on GitHub.
