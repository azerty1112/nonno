<?php

function normalizeConfigKey($key) {
    return strtolower(str_replace(['-', ' '], '_', trim((string)$key)));
}

function parseConfigList($value, array $separators = [',']) {
    $value = trim((string)$value);
    if ($value === '') {
        return [];
    }

    $pattern = '/\s*(?:' . implode('|', array_map('preg_quote', $separators)) . ')\s*/';
    $items = preg_split($pattern, $value);
    return array_values(array_filter(array_map('trim', (array)$items), static fn($item) => $item !== ''));
}

function loadConfigTxt($path) {
    if (!is_file($path) || !is_readable($path)) {
        return ['settings' => [], 'niches' => [], 'sources' => ['rss' => [], 'web' => []]];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $settings = [];
    $niches = [];
    $sources = ['rss' => [], 'web' => []];

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if (str_starts_with($key, 'NICHE_')) {
            if (!preg_match('/^NICHE_([A-Z0-9_]+)_(.+)$/', $key, $matches)) {
                continue;
            }
            $slug = str_replace('_', '-', strtolower($matches[1]));
            $field = strtolower($matches[2]);
            if (!isset($niches[$slug])) {
                $niches[$slug] = ['slug' => $slug];
            }

            if (in_array($field, ['rss_sources', 'web_sources', 'brands', 'models', 'seo_modifiers', 'audience_segments', 'angles', 'templates', 'fixed_titles'], true)) {
                $niches[$slug][$field] = parseConfigList($value, [',', '|']);
            } else {
                $niches[$slug][$field] = $value;
            }
        } else {
            $normalized = normalizeConfigKey($key);
            if (in_array($normalized, ['rss_sources', 'web_sources'], true)) {
                $type = $normalized === 'rss_sources' ? 'rss' : 'web';
                $sources[$type] = parseConfigList($value, [',', '|']);
                $settings[$normalized] = implode('\n', $sources[$type]);
            } else {
                $settings[$normalized] = $value;
            }
        }
    }

    return ['settings' => $settings, 'niches' => $niches, 'sources' => $sources];
}

function applyConfigSettings(PDO $pdo, array $settings) {
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    foreach ($settings as $key => $value) {
        if ($key === 'admin_password') {
            $key = 'admin_password_hash';
            $value = password_hash($value, PASSWORD_DEFAULT);
        }
        $stmt->execute([$key, $value]);
    }
}

function syncConfigNiches(PDO $pdo, array $niches) {
    $insertNicheStmt = $pdo->prepare("INSERT INTO niches (slug, name, description) VALUES (?, ?, ?) ON CONFLICT(slug) DO UPDATE SET name = excluded.name, description = excluded.description");
    $getNicheIdStmt = $pdo->prepare("SELECT id FROM niches WHERE slug = ? LIMIT 1");
    $insertNicheSourceStmt = $pdo->prepare("INSERT INTO niche_sources (niche_id, type, url) VALUES (?, ?, ?) ON CONFLICT(niche_id, type, url) DO NOTHING");
    $updateSettingStmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    
    foreach ($niches as $slug => $nicheData) {
        $name = trim((string)($nicheData['name'] ?? '')) ?: ucwords(str_replace('-', ' ', $slug));
        $description = trim((string)($nicheData['description'] ?? ''));
        $insertNicheStmt->execute([$slug, $name, $description]);

        $getNicheIdStmt->execute([$slug]);
        $nicheId = (int)$getNicheIdStmt->fetchColumn();
        if ($nicheId <= 0) {
            continue;
        }

        foreach (['rss_sources' => 'rss', 'web_sources' => 'web'] as $field => $type) {
            if (isset($nicheData[$field])) {
                $pdo->prepare("DELETE FROM niche_sources WHERE niche_id = ? AND type = ?")->execute([$nicheId, $type]);
                foreach ((array)$nicheData[$field] as $url) {
                    $url = trim((string)$url);
                    if ($url !== '') {
                        $insertNicheSourceStmt->execute([$nicheId, $type, $url]);
                    }
                }
            }
        }

        $nicheSettingsMap = [
            'brands' => 'auto_title_brands',
            'models' => 'auto_title_models',
            'seo_modifiers' => 'auto_title_modifiers',
            'audience_segments' => 'auto_title_audiences',
            'angles' => 'auto_title_angles',
            'templates' => 'auto_title_templates',
            'fixed_titles' => 'auto_title_fixed_titles',
            'mode' => 'auto_title_mode',
            'min_year_offset' => 'auto_title_min_year_offset',
            'max_year_offset' => 'auto_title_max_year_offset',
        ];
        foreach ($nicheSettingsMap as $field => $settingKey) {
            if (isset($nicheData[$field])) {
                $insertValue = in_array($field, ['brands', 'models', 'seo_modifiers', 'audience_segments', 'angles', 'templates', 'fixed_titles'], true)
                    ? implode("\n", (array)$nicheData[$field])
                    : (string)$nicheData[$field];
                $updateSettingStmt->execute(['niche.' . $slug . '.' . $settingKey, $insertValue]);
            }
        }
    }
}

function syncConfigSources(PDO $pdo, array $sources) {
    if (isset($sources['rss']) && is_array($sources['rss'])) {
        $rssStmt = $pdo->prepare("INSERT OR IGNORE INTO rss_sources (url) VALUES (?)");
        foreach ($sources['rss'] as $url) {
            $url = trim((string)$url);
            if ($url !== '') {
                $rssStmt->execute([$url]);
            }
        }
    }
    if (isset($sources['web']) && is_array($sources['web'])) {
        $webStmt = $pdo->prepare("INSERT OR IGNORE INTO web_sources (url) VALUES (?)");
        foreach ($sources['web'] as $url) {
            $url = trim((string)$url);
            if ($url !== '') {
                $webStmt->execute([$url]);
            }
        }
    }
}

function getConfigFileFingerprint($path) {
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    return sha1_file($path);
}

function loadConfigFileIfChanged(PDO $pdo, $path) {
    $fingerprint = getConfigFileFingerprint($path);
    if ($fingerprint === null) {
        return ['settings' => [], 'niches' => [], 'sources' => ['rss' => [], 'web' => []]];
    }

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'config_txt_fingerprint' LIMIT 1");
    $stmt->execute();
    $storedFingerprint = $stmt->fetchColumn();

    if ($storedFingerprint === $fingerprint) {
        return ['settings' => [], 'niches' => [], 'sources' => ['rss' => [], 'web' => []]];
    }

    $configFile = loadConfigTxt($path);
    applyConfigSettings($pdo, $configFile['settings']);
    syncConfigNiches($pdo, $configFile['niches']);
    syncConfigSources($pdo, $configFile['sources']);
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES ('config_txt_fingerprint', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    $stmt->execute([$fingerprint]);

    return $configFile;
}

$initialConfigFile = loadConfigTxt(__DIR__ . '/config.txt');

define('DB_FILE', __DIR__ . '/data/data.db');
define('SITE_TITLE', $initialConfigFile['settings']['site_title'] ?? 'AutoCar Niche');
define('PASSWORD_HASH', '$2y$12$iFCL8jqvoVMbZBcRy3wY..IUJNTqFcIfNAtUZRKiY4pFSspOevkHi'); // admin123

function db_connect() {
    if (!file_exists(dirname(DB_FILE))) mkdir(dirname(DB_FILE), 0777, true);
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    return $pdo;
}

// إنشاء الجداول عند أول تشغيل
$pdo = db_connect();
$pdo->exec("CREATE TABLE IF NOT EXISTS articles (
    id INTEGER PRIMARY KEY,
    title TEXT UNIQUE,
    slug TEXT UNIQUE,
    content TEXT,
    image TEXT,
    image2 TEXT,
    excerpt TEXT,
    published_at TEXT,
    category TEXT,
    niche_id INTEGER DEFAULT 1,
    translated_title TEXT,
    translated_content TEXT,
    orig_language TEXT
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS rss_sources (id INTEGER PRIMARY KEY, url TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS web_sources (id INTEGER PRIMARY KEY, url TEXT)");
// Niches support: separate niches and their sources
$pdo->exec("CREATE TABLE IF NOT EXISTS niches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT DEFAULT ''
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS niche_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    niche_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('rss','web')),
    url TEXT NOT NULL,
    UNIQUE(niche_id, type, url),
    FOREIGN KEY(niche_id) REFERENCES niches(id) ON DELETE CASCADE
)");

loadConfigFileIfChanged($pdo, __DIR__ . '/config.txt');

// Default niches are seeded by the NicheManager class if available.
if (class_exists('App\\NicheManager')) {
    \App\NicheManager::seedDefaults();
}


// Tags system for better SEO and filtering
$pdo->exec("CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    description TEXT DEFAULT '',
    post_count INTEGER DEFAULT 0
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS article_tags (
    article_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY(article_id, tag_id),
    FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
)");

// Article ratings and engagement metrics
$pdo->exec("CREATE TABLE IF NOT EXISTS article_stats (
    article_id INTEGER PRIMARY KEY,
    views INTEGER DEFAULT 0,
    clicks INTEGER DEFAULT 0,
    avg_rating REAL DEFAULT 0,
    rating_count INTEGER DEFAULT 0,
    shares INTEGER DEFAULT 0,
    updated_at INTEGER DEFAULT 0,
    FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS article_ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    visitor_hash TEXT NOT NULL,
    created_at INTEGER DEFAULT 0,
    UNIQUE(article_id, visitor_hash),
    FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_article_tags_tag ON article_tags(tag_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tags_slug ON tags(slug)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_article_stats_views ON article_stats(views DESC)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_article_stats_avg_rating ON article_stats(avg_rating DESC)");
$pdo->exec("CREATE TABLE IF NOT EXISTS url_cache (
    url TEXT PRIMARY KEY,
    body TEXT,
    status_code INTEGER DEFAULT 0,
    fetched_at INTEGER DEFAULT 0,
    ttl_seconds INTEGER DEFAULT 900,
    fail_count INTEGER DEFAULT 0,
    blocked_until INTEGER DEFAULT 0
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS scrape_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow TEXT NOT NULL,
    source_url TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    locked_until INTEGER DEFAULT 0,
    available_at INTEGER DEFAULT 0,
    created_at INTEGER DEFAULT 0,
    updated_at INTEGER DEFAULT 0
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_scrape_queue_workflow_status_available ON scrape_queue(workflow, status, available_at, locked_until)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_scrape_queue_source_url ON scrape_queue(source_url)");
$pdo->exec("CREATE TABLE IF NOT EXISTS article_exports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL,
    slug TEXT NOT NULL,
    html_path TEXT NOT NULL,
    json_path TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(article_id),
    FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS page_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_key TEXT NOT NULL,
    page_label TEXT NOT NULL,
    visitor_hash TEXT NOT NULL,
    views INTEGER NOT NULL DEFAULT 1,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(page_key, visitor_hash)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_page_visits_page_key ON page_visits(page_key)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_page_visits_updated_at ON page_visits(updated_at)");
$pdo->exec("DELETE FROM rss_sources WHERE id NOT IN (SELECT MIN(id) FROM rss_sources GROUP BY url)");
$pdo->exec("DELETE FROM web_sources WHERE id NOT IN (SELECT MIN(id) FROM web_sources GROUP BY url)");
$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_rss_sources_url ON rss_sources(url)");
$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_web_sources_url ON web_sources(url)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_category_published_id ON articles(category, published_at, id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_published_id ON articles(published_at, id)");

// Migration: add niche_id column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE articles ADD COLUMN niche_id INTEGER DEFAULT 1");
} catch (PDOException $e) {
    // Column already exists or migration not needed
}
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_niche_id ON articles(niche_id)");
$pdo->exec("UPDATE articles SET niche_id = 1 WHERE niche_id IS NULL");

// Migration: add secondary image and translation columns
try {
    $pdo->exec("ALTER TABLE articles ADD COLUMN image2 TEXT");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE articles ADD COLUMN translated_title TEXT");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE articles ADD COLUMN translated_content TEXT");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE articles ADD COLUMN orig_language TEXT");
} catch (PDOException $e) {}

// إعدادات افتراضية
$defaults = [
    'site_title' => SITE_TITLE,
    'active_niche' => 'general',
    'min_words' => '3000',
    'auto_publish' => '1',
    'daily_limit' => '5',
    'auto_ai_enabled' => '1',
    'auto_publish_interval_minutes' => '180',
    'auto_publish_interval_seconds' => '10800',
    'auto_publish_last_run_at' => '1970-01-01 00:00:00',
    'content_workflow' => 'rss',
    'url_cache_ttl_seconds' => '900',
    'fetch_timeout_seconds' => '12',
    'fetch_retry_attempts' => '3',
    'fetch_retry_backoff_ms' => '350',
    'fetch_user_agent' => 'Mozilla/5.0 (compatible; VitoBot/1.0; +https://example.com/bot)',
    'workflow_batch_size' => '8',
    'queue_retry_delay_seconds' => '60',
    'queue_max_attempts' => '3',
    'queue_source_cooldown_seconds' => '180',
    'visit_excluded_ips' => '',
    'auto_translate_enabled' => '0',
    'auto_translate_target_language' => '',
    'seo_home_title' => SITE_TITLE,
    'seo_home_description' => 'Automotive reviews, guides, and practical car ownership tips.',
    'seo_article_title_suffix' => SITE_TITLE,
    'seo_default_robots' => 'index,follow',
    'seo_default_og_image' => '',
    'seo_twitter_site' => '',
    'seo_image_alt_suffix' => ' - car image',
    'seo_image_title_suffix' => ' - photo',
    'seo_auto_link_rules' => '',
    'seo_auto_link_auto_internal' => '1',
    'seo_auto_link_max_per_article' => '3',
    'google_analytics_id' => '',
    'google_tag_manager_id' => '',
    'google_site_verification' => '',
    'bing_site_verification' => '',
    'meta_pixel_id' => '',
    'custom_head_scripts' => '',
    'custom_body_scripts' => '',
    'ads_enabled' => '0',
    'ads_injection_mode' => 'smart',
    'ads_paragraph_interval' => '4',
    'ads_max_units_per_article' => '2',
    'ads_min_words_before_first_injection' => '180',
    'ads_min_article_words' => '420',
    'ads_blocked_title_keywords' => '',
    'ads_label_text' => 'Sponsored',
    'ads_html_code' => '<div class="ad-unit-inner">Place your ad code here</div>',
    'ads_txt' => '',
];
foreach ($defaults as $k => $v) {
    $pdo->prepare("INSERT OR IGNORE INTO settings (key,value) VALUES (?,?)")->execute([$k, $v]);
}

// one-time migration for installs created before the scalable pipeline defaults
$migrationKey = 'pipeline_defaults_v2_applied';
$migrationStmt = $pdo->prepare("SELECT value FROM settings WHERE key = ? LIMIT 1");
$migrationStmt->execute([$migrationKey]);
$migrationApplied = $migrationStmt->fetchColumn();
if ($migrationApplied === false) {
    $currentMinWordsStmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'min_words' LIMIT 1");
    $currentMinWordsStmt->execute();
    $currentMinWords = (int)$currentMinWordsStmt->fetchColumn();
    if ($currentMinWords <= 1200) {
        $pdo->prepare("UPDATE settings SET value = '3000' WHERE key = 'min_words'")->execute();
    }

    $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES ('fetch_timeout_seconds', '12')")->execute();
    $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES ('fetch_user_agent', 'Mozilla/5.0 (compatible; VitoBot/1.0; +https://example.com/bot)')")->execute();
    $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
        ->execute([$migrationKey, date('Y-m-d H:i:s')]);
}

// Default tags for better content organization and SEO
$default_tags = [
    ['Review', 'content insights for car buyers'],
    ['Maintenance', 'keep your vehicle running smoothly'],
    ['Safety', 'driving safety and crash prevention'],
    ['Buying Guide', 'everything to know before purchasing'],
    ['Performance', 'engine power and driving dynamics'],
    ['Electric Vehicles', 'EV charging, batteries, and efficiency'],
    ['SUV', 'sport utility vehicles and crossovers'],
    ['Sedan', 'luxury and practical four-door cars'],
    ['Comparison', 'head-to-head model analysis'],
    ['Technology', 'infotainment and automotive tech'],
];
foreach ($default_tags as [$name, $description]) {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
    $pdo->prepare("INSERT OR IGNORE INTO tags (name, slug, description) VALUES (?, ?, ?)")
        ->execute([$name, $slug, $description]);
}

?>
