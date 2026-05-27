# 📋 Configuration Guide

## Overview

This application uses a multi-layer configuration system that allows flexible setup for different environments. Settings are loaded in the following priority order:

1. **Environment variables** (.env file)
2. **Configuration file** (config.txt)
3. **Database settings** (Loaded at runtime)
4. **Default constants** (config/Constants.php)

## Configuration Files

### 1. .env File (Environment Variables)

Create a `.env` file in the project root based on `.env.example`:

```bash
cp .env.example .env
```

The `.env` file contains sensitive information and should **never** be committed to version control. It's already listed in `.gitignore`.

**Key Environment Variables:**
- `APP_ENV` - Application environment (production, development, testing)
- `APP_DEBUG` - Enable debug mode (false in production)
- `DB_TYPE` - Database type (sqlite, mysql, postgresql)
- `DB_PATH` - SQLite database path
- `ADMIN_PASSWORD` - Admin login password
- `API_ACCESS_KEY` - API key for external access

### 2. config.txt File (Runtime Settings)

The `config.txt` file contains application settings loaded at startup. It supports:
- **Global Settings** - Site-wide configuration
- **Niche Settings** - Per-niche configuration (with `NICHE_` prefix)
- **RSS/Web Sources** - Content sources for the workflow

Example structure:

```ini
# Global Settings
SITE_TITLE=My Website
ADMIN_PASSWORD=secure-password
DAILY_LIMIT=5
CONTENT_WORKFLOW=rss

# Niche Settings (if using multi-niche)
NICHE_automotive.auto_title_mode=template
NICHE_automotive.auto_title_templates=Year {year} {brand} {model}

# Sources
RSS_SOURCES=https://example.com/feed1,https://example.com/feed2
WEB_SOURCES=https://example.com/page1,https://example.com/page2
```

**Important Notes:**
- Comments start with `#` and are ignored
- Settings are case-insensitive in config.txt but stored uppercase
- Multi-line values are not supported; use semicolons or commas as separators

### 3. config/Constants.php (Default Values)

This file defines all application constants, validation patterns, and limits:

- **Path Constants** - Directory locations
- **Database Limits** - Max record sizes
- **Content Limits** - Min/max lengths for articles, titles, etc.
- **Validation Patterns** - Regex for validation
- **Workflow Types** - Available content workflows

Access constants via:
```php
use Config\Constants;

echo Constants::MAX_ARTICLE_TITLE_LENGTH; // 500
echo Constants::FETCH_TIMEOUT_DEFAULT;    // 12
```

## Common Configuration Tasks

### 1. Change Site Title

Edit `config.txt`:
```ini
SITE_TITLE=New Site Name
```

Or set environment variable:
```bash
export SITE_TITLE="New Site Name"
```

### 2. Set Admin Password

⚠️ **Important**: The password is hashed in the database, not stored as plain text.

In `config.txt`:
```ini
ADMIN_PASSWORD=your-strong-password
```

The application will hash this password when first loaded.

### 3. Configure Auto-Publishing Schedule

```ini
# Publish every 3 hours
AUTO_PUBLISH=1
AUTO_PUBLISH_INTERVAL_MINUTES=180

# Or use seconds (10800 seconds = 3 hours)
AUTO_PUBLISH_INTERVAL_SECONDS=10800
```

### 4. Set Daily Publishing Limit

```ini
# Publish max 5 articles per day
DAILY_LIMIT=5
```

### 5. Configure Content Workflow

```ini
# Use RSS sources for content
CONTENT_WORKFLOW=rss

# Or use web scraping
CONTENT_WORKFLOW=web
```

### 6. Setup Analytics Tracking

```ini
GOOGLE_ANALYTICS_ID=G-XXXXXXXXXX
GOOGLE_TAG_MANAGER_ID=GTM-XXXXXX
META_PIXEL_ID=123456789
```

Valid formats:
- Google Analytics: `G-XXXXXXXXXX` (GA4) or `UA-XXXXXXXXXX` (Universal)
- Google Tag Manager: `GTM-XXXXXX`
- Google Ads: `AW-XXXXXXXXXX`

### 7. Enable Ad Injection

```ini
ADS_ENABLED=true
ADS_INJECTION_MODE=smart
ADS_PARAGRAPH_INTERVAL=4
ADS_MAX_UNITS_PER_ARTICLE=2
ADS_HTML_CODE=<div id="ad-unit"></div>
ADS_LABEL_TEXT=Advertisement
```

### 8. Configure SEO Settings

```ini
SEO_HOME_TITLE=Best Auto Reviews
SEO_HOME_DESCRIPTION=Latest automotive reviews and guides
SEO_DEFAULT_ROBOTS=index,follow
SEO_AUTO_LINK_INTERNAL=true
SEO_AUTO_LINK_MAX_PER_ARTICLE=3
```

### 9. Setup Translation

```ini
AUTO_TRANSLATE_ENABLED=true
AUTO_TRANSLATE_TARGET_LANGUAGE=ar
```

Supported languages:
- `ar` - العربية (Arabic)
- `en` - English
- `fr` - Français
- `de` - Deutsch
- `es` - Español
- `pt` - Português
- `it` - Italiano

### 10. Configure Auto-Title Generation

```ini
# Mode: template (uses variables) or list (fixed titles)
NICHE_automotive.auto_title_mode=template

# Templates with variables
NICHE_automotive.auto_title_templates=Year {year} {brand} {model}\nBest {brand} Cars {year}\n{brand} {model} Review

# Components (when using template mode)
NICHE_automotive.auto_title_brands=Honda,Toyota,BMW
NICHE_automotive.auto_title_models=Civic,Accord,CR-V
NICHE_automotive.auto_title_modifiers=Best,Top,Review,Guide
```

Variables available in templates:
- `{year}` - Current or calculated year
- `{brand}` - Brand/manufacturer
- `{model}` - Model name
- `{modifier}` - Adjective (Best, Top, etc.)
- `{audience}` - Target audience
- `{angle}` - Content angle

## Database Configuration

### SQLite (Default)

```ini
DB_TYPE=sqlite
DB_PATH=data/data.db
```

Ensure the `data/` directory exists and is writable:
```bash
mkdir -p data
chmod 755 data
```

### MySQL

```ini
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nonno
DB_USER=root
DB_PASSWORD=password
```

### PostgreSQL

```ini
DB_TYPE=postgresql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=nonno
DB_USER=postgres
DB_PASSWORD=password
```

## Security Settings

### API Lock

Restrict API access with a key:
```ini
API_LOCK_ENABLED=true
API_ACCESS_KEY=your-secret-api-key-here
```

### Exclude IPs from Auto-Publishing

Prevent certain IPs from triggering auto-publish:
```ini
VISIT_EXCLUDED_IPS=192.168.1.1;10.0.0.0/8;127.0.0.1
```

### Session Timeout

```ini
ADMIN_SESSION_TIMEOUT=3600
```

### Login Rate Limiting

```ini
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_DURATION=60
```

## Performance Tuning

### Increase Cache TTL

```ini
URL_CACHE_TTL_SECONDS=3600
```

Higher values = fewer database queries, but stale data longer.

### Adjust Fetch Settings

```ini
FETCH_TIMEOUT_SECONDS=20
FETCH_RETRY_ATTEMPTS=5
FETCH_RETRY_BACKOFF_MS=500
QUEUE_BATCH_SIZE=10
```

### Page Visit Cleanup

```ini
PAGE_VISITS_MAX_RECORDS=200000
```

## Using ConfigLoader in Code

```php
<?php
use Config\ConfigLoader;

// Load all configuration
ConfigLoader::load();

// Get a setting
$siteTitle = ConfigLoader::get('SITE_TITLE', 'Default Title');
$dailyLimit = ConfigLoader::get('DAILY_LIMIT', 5);

// Get boolean settings
$autoPublish = ConfigLoader::get('AUTO_PUBLISH', true);

// Get all settings
$allSettings = ConfigLoader::all();

// Set a value at runtime
ConfigLoader::set('WORKFLOW_STATUS', 'running');
```

## Validation Rules

All configuration values are validated based on their type:

| Setting | Type | Min | Max | Default |
|---------|------|-----|-----|---------|
| MIN_WORDS | integer | 0 | 50000 | 3000 |
| DAILY_LIMIT | integer | 1 | 1000 | 5 |
| FETCH_TIMEOUT_SECONDS | integer | 3 | 45 | 12 |
| WORKFLOW_BATCH_SIZE | integer | 1 | 50 | 8 |
| ADS_PARAGRAPH_INTERVAL | integer | 2 | 10 | 4 |
| URL_CACHE_TTL_SECONDS | integer | 60 | 86400 | 900 |

## Environment-Specific Configurations

### Development

```ini
APP_ENV=development
APP_DEBUG=true
DB_TYPE=sqlite
DB_PATH=data/data.db
FETCH_TIMEOUT_SECONDS=30
```

### Production

```ini
APP_ENV=production
APP_DEBUG=false
DB_TYPE=mysql
FETCH_TIMEOUT_SECONDS=12
ADMIN_SESSION_TIMEOUT=3600
SESSION_SECURE_COOKIE=true
```

### Testing

```ini
APP_ENV=testing
DB_TYPE=sqlite
DB_PATH=data/test.db
AUTO_PUBLISH=false
LOGIN_MAX_ATTEMPTS=100
```

## Troubleshooting

### Settings Not Loading

1. Check `.env` file exists and is readable
2. Verify `config.txt` syntax (no spaces around `=`)
3. Check file permissions: `chmod 644 .env config.txt`
4. Clear any cache files if applicable

### Admin Password Not Working

1. Verify `ADMIN_PASSWORD` in config.txt or .env
2. Check database password table for hash
3. Run database initialization script if needed

### API Requests Failing

1. Verify `API_LOCK_ENABLED` is false, or
2. Provide correct `API_ACCESS_KEY` in header
3. Check rate limiting isn't blocking requests

## See Also

- [Database Setup](./database.md)
- [Security Guide](../docs/SECURITY.md)
- [API Documentation](../docs/API.md)
