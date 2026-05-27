# 🚀 Nonno Project - Complete Structure

A modern, modular PHP content management system with AI-powered auto-publishing, multi-niche support, and advanced workflow automation.

## 📁 Project Structure

```
nonno/
├── 📄 Root Files
│   ├── index.php                 # Public homepage / front-end entry point
│   ├── api.php                   # REST API endpoint
│   ├── cron.php                  # Scheduled tasks trigger
│   ├── sitemap.php               # XML sitemap generator
│   ├── functions.php             # Global helper functions
│   ├── config.php                # Configuration loader (legacy)
│   ├── config.txt                # Configuration file (runtime settings)
│   ├── composer.json             # PHP dependencies
│   ├── robots.txt                # Search engine directives
│   ├── ads.txt                   # Authorized digital sellers
│   ├── README.md                 # Main documentation
│   ├── .env.example              # Environment template
│   └── .gitignore                # Git exclusions
│
├── 📁 config/
│   ├── ConfigLoader.php          # Configuration loader (new modular system)
│   ├── Constants.php             # Application constants and limits
│   └── README.md                 # Configuration documentation
│
├── 📁 src/
│   ├── Utils.php                 # Utility functions (slugify, escape, etc.)
│   └── NicheManager.php          # Multi-niche content management
│
├── 📁 admin/
│   ├── admin.php                 # Admin panel entry point
│   ├── auth.php                  # Authentication & login handling
│   ├── data.php                  # Data loading & request coordination
│   ├── README.md                 # Admin module documentation
│   │
│   ├── 📁 views/
│   │   ├── dashboard.php         # Main admin interface template
│   │   └── login.php             # Login form template
│   │
│   └── 📁 handlers/
│       ├── password.php          # Admin password change (28 lines)
│       ├── seo-settings.php      # SEO & metadata config (104 lines)
│       ├── scripts-settings.php  # Analytics & tracking scripts (60 lines)
│       ├── ads-settings.php      # Ad injection configuration (66 lines)
│       ├── articles.php          # Article CRUD operations (180 lines)
│       ├── sources.php           # RSS/Web source management (228 lines)
│       ├── smart-sources.php     # Smart source auto-detection (120 lines)
│       ├── workflow.php          # Content workflow selection (29 lines)
│       ├── automation.php        # Pipeline & scheduler config (134 lines)
│       ├── titles.php            # Auto title generation (161 lines)
│       └── settings.php          # Generic setting CRUD (88 lines)
│
├── 📁 public/
│   ├── 📁 css/                   # Stylesheets (Bootstrap, custom)
│   ├── 📁 js/                    # JavaScript files
│   └── 📁 images/                # Images & assets
│
├── 📁 data/
│   └── data.db                   # SQLite database (auto-created)
│
├── 📁 scripts/
│   ├── test_ping.php             # Test search engine pings
│   ├── test_niche.php            # Test niche functionality
│   ├── test_translation.php      # Test translation API
│   ├── export_static.php         # Export articles as static HTML
│   └── test_export.php           # Test export functionality
│
├── 📁 vendor/
│   └── ...                       # Composer dependencies
│       ├── symfony/dom-crawler   # HTML parsing
│       ├── symfony/css-selector  # CSS selector parsing
│       ├── masterminds/html5     # HTML5 parsing
│       └── polyfill-*            # PHP compatibility
│
├── 📄 Public Pages (HTML)
│   ├── about.html                # About page
│   ├── contact.html              # Contact page
│   ├── privacy.html              # Privacy policy
│   └── terms.html                # Terms of service
│
└── 📄 Hidden Files
    ├── .env                      # Environment variables (not committed)
    ├── .git/                     # Git repository
    ├── .github/                  # GitHub workflows
    └── .gitignore                # Git exclusions
```

## 🎯 Core Modules

### 1. Admin Panel (`/admin/`)

The modular admin interface split into focused components:

```
/admin/admin.php → includes auth.php → includes data.php → includes handlers → renders views/dashboard.php
```

**Features:**
- Authentication with rate limiting
- Dashboard with statistics
- Article management (CRUD + bulk)
- Source management (RSS + Web)
- Settings configuration
- SEO management
- Analytics & tracking
- Auto-publishing configuration

**File Breakdown:**
- `admin.php` (16 lines) - Entry point orchestration
- `auth.php` (69 lines) - Login & session management
- `data.php` (286 lines) - Data loading & handler coordination
- `handlers/*` (1196 lines total) - 11 feature handlers
- `views/dashboard.php` (358 lines) - Admin interface template
- `views/login.php` (45 lines) - Login form

**Total: ~2106 lines** (was 3238 in monolithic file)

### 2. Configuration System (`/config/`)

Multi-layer configuration management:

```
Priority: .env → config.txt → Database → Constants
```

**Files:**
- `ConfigLoader.php` - Dynamic configuration loading
- `Constants.php` - Application constants & validation rules
- `README.md` - Configuration guide

### 3. Content Management (`/src/`)

Core business logic:

- `Utils.php` - Utility functions
- `NicheManager.php` - Multi-niche content organization

### 4. Public Site (`/`)

Front-end application:

- `index.php` - Homepage / article listing
- `api.php` - REST API for external access
- `cron.php` - Scheduled task trigger
- `functions.php` - Global helpers
- Static pages: about.html, contact.html, privacy.html, terms.html

## ⚙️ Configuration

### Quick Start

```bash
# 1. Copy environment template
cp .env.example .env

# 2. Edit environment settings
nano .env

# 3. Update config.txt for application settings
nano config.txt

# 4. Create data directory
mkdir -p data
chmod 755 data

# 5. Access admin panel
# http://yoursite.com/admin/admin.php
```

### Key Settings

**config.txt example:**
```ini
SITE_TITLE=My Website
ADMIN_PASSWORD=secure-password
DAILY_LIMIT=5
CONTENT_WORKFLOW=rss
AUTO_PUBLISH=1
MIN_WORDS=3000
```

See [config/README.md](config/README.md) for complete configuration guide.

## 🗄️ Database Schema

**Tables:**
- `articles` - Published content
- `rss_sources` - RSS feed sources
- `web_sources` - Website scraping sources
- `niche_sources` - Niche-specific sources (multi-niche)
- `settings` - Key-value configuration storage
- `page_visits` - Analytics tracking
- `queue` - Publishing queue (if scheduled publishing)

## 🔧 Development Guide

### Adding a New Admin Feature

1. Create handler file: `/admin/handlers/my-feature.php`
2. Include handler in `/admin/data.php` POST section
3. Add corresponding form in `/admin/views/dashboard.php`
4. Add configuration keys to `config.txt` if needed

Example handler structure:
```php
<?php
// /admin/handlers/my-feature.php

if ($requestMethod === 'POST' && $request === 'my_feature_action') {
    // Validate CSRF token
    if (!verifyCSRFToken($csrf)) {
        setFlash('Invalid security token', 'danger');
    } else {
        // Your business logic here
        setSetting('my_setting', $_POST['value']);
        setFlash('Setting updated successfully', 'success');
    }
}
```

### Adding a New Admin Page

1. Create view file: `/admin/views/my-page.php`
2. Create data loader logic
3. Include in admin.php navigation
4. Add navigation button in dashboard.php

### Using Configuration

```php
<?php
use Config\ConfigLoader;
use Config\Constants;

// Load all config
ConfigLoader::load();

// Get setting with default
$siteTitle = ConfigLoader::get('SITE_TITLE', 'My Site');

// Use constants
$maxTitle = Constants::MAX_ARTICLE_TITLE_LENGTH; // 500
```

## 📚 Documentation

- **[Configuration Guide](config/README.md)** - All settings & configuration options
- **[Admin Module](admin/README.md)** - Admin panel structure & features
- **[API Documentation](docs/API.md)** - REST API endpoints (if present)
- **[Security Guide](docs/SECURITY.md)** - Security best practices (if present)

## 🔐 Security Features

- ✅ CSRF token protection on all forms
- ✅ Password hashing with `password_hash()`
- ✅ Prepared SQL statements (SQL injection prevention)
- ✅ Input validation & sanitization
- ✅ Session-based authentication
- ✅ Admin password rate limiting (5 attempts, 60s lockout)
- ✅ Optional API key lock
- ✅ XSS protection via `e()` function

## 🚀 Deployment

### Production Checklist

- [ ] Copy `.env.example` to `.env` and set secure values
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Ensure `data/` directory exists and is writable
- [ ] Set strong `ADMIN_PASSWORD` in `config.txt`
- [ ] Configure SSL certificate
- [ ] Set up automated backups
- [ ] Configure cron job for `cron.php`
- [ ] Review and secure all settings in admin panel

### Cron Job Setup

```bash
# Run auto-publishing every 15 minutes
*/15 * * * * curl -s https://yoursite.com/cron.php > /dev/null 2>&1
```

## 📊 Statistics

- **Total lines of PHP code:** ~5000+
- **Admin module:** 2106 lines (11 focused handler files)
- **Configuration system:** 300+ lines
- **Modular handlers:** 11 separate features
- **Database tables:** 7
- **API endpoints:** 15+
- **Admin features:** 15+

## 🔄 Workflow Overview

1. **User accesses** `/admin/admin.php`
2. **Auth check** - Redirects to login if needed
3. **Data loading** - Aggregates stats and config
4. **POST handling** - Routes to appropriate handler
5. **View rendering** - Displays dashboard with updated data
6. **Frontend** - Users visit `index.php` for content

## 🤝 Contributing

When adding new features:

1. ✅ Follow the modular handler pattern
2. ✅ Add configuration keys to `config/Constants.php`
3. ✅ Include inline documentation
4. ✅ Validate all inputs
5. ✅ Use prepared statements for database
6. ✅ Add CSRF protection to forms

## 📝 License

See LICENSE file for details.

## 🆘 Support

For issues or questions:
1. Check [Configuration Guide](config/README.md)
2. Review [Admin Module Documentation](admin/README.md)
3. Check admin panel error messages
4. Review PHP error logs

---

**Last Updated:** May 2026
**Version:** 2.0 (Modular)
**PHP Version:** 7.4+
