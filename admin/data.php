<?php
/**
 * Admin Dashboard Data Handler
 * Loads all dashboard data and statistics
 */

$pdo = db_connect();
$csrf = csrfToken();
publishAutoArticleBySchedule();

if (!isset($_SESSION['flash_message'])) {
    $_SESSION['flash_message'] = null;
    $_SESSION['flash_type'] = 'info';
}

// Handle POST requests first
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid security token. Please refresh and try again.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: admin.php');
        exit;
    }

    // Include all handler files
    require_once __DIR__ . '/handlers/password.php';
    require_once __DIR__ . '/handlers/seo-settings.php';
    require_once __DIR__ . '/handlers/scripts-settings.php';
    require_once __DIR__ . '/handlers/ads-settings.php';
    require_once __DIR__ . '/handlers/articles.php';
    require_once __DIR__ . '/handlers/sources.php';
    require_once __DIR__ . '/handlers/smart-sources.php';
    require_once __DIR__ . '/handlers/workflow.php';
    require_once __DIR__ . '/handlers/automation.php';
    require_once __DIR__ . '/handlers/titles.php';
    require_once __DIR__ . '/handlers/settings.php';
}

// Get message from session
$message = $_SESSION['flash_message'];
$messageType = $_SESSION['flash_type'];
$_SESSION['flash_message'] = null;
$_SESSION['flash_type'] = 'info';

// Handle exports
$articleSearch = trim($_GET['qa'] ?? '');
$rssSearch = trim($_GET['qr'] ?? '');
$webSearch = trim($_GET['qw'] ?? '');
$articleCategory = trim($_GET['cat'] ?? '');

if (isset($_GET['export']) && $_GET['export'] === 'articles_json') {
    $exportStmt = $pdo->prepare("SELECT title, slug, excerpt, category, image, image2, translated_title, translated_content, published_at FROM articles ORDER BY id DESC");
    $exportStmt->execute();
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=articles-export.json');
    echo json_encode($exportRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'articles_csv') {
    $exportStmt = $pdo->prepare("SELECT title, slug, category, image, image2, translated_title, published_at FROM articles ORDER BY id DESC");
    $exportStmt->execute();
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=articles-export.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['title', 'slug', 'category', 'image', 'image2', 'translated_title', 'published_at']);
    foreach ($exportRows as $row) {
        fputcsv($out, [$row['title'], $row['slug'], $row['category'], $row['image'], $row['image2'], $row['translated_title'], $row['published_at']]);
    }
    fclose($out);
    exit;
}

// Load dashboard data
$totalArticlesStmt = $pdo->prepare("SELECT COUNT(*) FROM articles");
$totalArticlesStmt->execute();
$totalArticles = (int)$totalArticlesStmt->fetchColumn();

$totalSourcesStmt = $pdo->prepare("SELECT COUNT(*) FROM rss_sources");
$totalSourcesStmt->execute();
$totalSources = (int)$totalSourcesStmt->fetchColumn();

$totalWebSourcesStmt = $pdo->prepare("SELECT COUNT(*) FROM web_sources");
$totalWebSourcesStmt->execute();
$totalWebSources = (int)$totalWebSourcesStmt->fetchColumn();

$latestDateStmt = $pdo->prepare("SELECT MAX(published_at) FROM articles");
$latestDateStmt->execute();
$latestDate = $latestDateStmt->fetchColumn();

// Analytics
$pageVisitSearch = trim($_GET['pv_search'] ?? '');
$pageVisitStats = getPageVisitStats(7, $pageVisitSearch);
$totalTrackedViews = 0;
$totalTrackedVisitors = 0;
foreach ($pageVisitStats as $visitRow) {
    $totalTrackedViews += (int)($visitRow['total_views'] ?? 0);
    $totalTrackedVisitors += (int)($visitRow['unique_visitors'] ?? 0);
}

// Settings
$dailyLimit = (int)getSetting('daily_limit', 5);
$seoHomeTitle = (string)getSetting('seo_home_title', $siteTitle);
$seoHomeDescription = (string)getSetting('seo_home_description', 'Automotive reviews, guides, and practical car ownership tips.');
$seoArticleTitleSuffix = (string)getSetting('seo_article_title_suffix', $siteTitle);
$seoDefaultRobots = (string)getSetting('seo_default_robots', 'index,follow');
$seoDefaultOgImage = (string)getSetting('seo_default_og_image', '');
$seoTwitterSite = (string)getSetting('seo_twitter_site', '');
$seoImageAltSuffix = (string)getSetting('seo_image_alt_suffix', ' - car image');
$seoImageTitleSuffix = (string)getSetting('seo_image_title_suffix', ' - photo');
$seoAutoLinkRules = (string)getSetting('seo_auto_link_rules', '');
$seoAutoLinkAutoInternal = getSettingInt('seo_auto_link_auto_internal', 1, 0, 1) === 1;
$seoAutoLinkMaxPerArticle = getSettingInt('seo_auto_link_max_per_article', 3, 1, 10);

$autoTranslateEnabled = getSettingInt('auto_translate_enabled', 0, 0, 1) === 1;
$autoTranslateTarget = trim((string)getSetting('auto_translate_target_language', ''));

$googleAnalyticsId = (string)getSetting('google_analytics_id', '');
$googleTagManagerId = (string)getSetting('google_tag_manager_id', '');
$googleSiteVerification = (string)getSetting('google_site_verification', '');
$bingSiteVerification = (string)getSetting('bing_site_verification', '');
$metaPixelId = (string)getSetting('meta_pixel_id', '');
$customHeadScripts = (string)getSetting('custom_head_scripts', '');
$customBodyScripts = (string)getSetting('custom_body_scripts', '');
$adsTxtContent = (string)getSetting('ads_txt', '');
$siteBaseUrl = getSiteBaseUrl();
$sitemapUrl = $siteBaseUrl !== '' ? ($siteBaseUrl . '/sitemap.php') : '/sitemap.php';

$adsEnabled = getSettingInt('ads_enabled', 0, 0, 1) === 1;
$adsInjectionMode = (string)getSetting('ads_injection_mode', 'smart');
if (!in_array($adsInjectionMode, ['smart', 'interval'], true)) {
    $adsInjectionMode = 'smart';
}
$adsParagraphInterval = getSettingInt('ads_paragraph_interval', 4, 2, 10);
$adsMaxUnits = getSettingInt('ads_max_units_per_article', 2, 1, 6);
$adsMinWordsBeforeFirstInjection = getSettingInt('ads_min_words_before_first_injection', 180, 80, 600);
$adsMinArticleWords = getSettingInt('ads_min_article_words', 420, 120, 3000);
$adsBlockedTitleKeywords = (string)getSetting('ads_blocked_title_keywords', '');
$adsBlockedCategories = (string)getSetting('ads_blocked_categories', '');
$adsLabelText = (string)getSetting('ads_label_text', 'Sponsored');
$adsHtmlCode = (string)getSetting('ads_html_code', '<div class="ad-unit-inner">Place your ad code here</div>');

// Pipeline & Automation
$minWordsFrom = getSettingInt('min_words_from', 300, 0, 300000);
$minWordsTo = getSettingInt('min_words_to', getSettingInt('min_words', 3000, 300, 300000), 0, 300000);
if ($minWordsTo < $minWordsFrom) {
    [$minWordsFrom, $minWordsTo] = [$minWordsTo, $minWordsFrom];
}
$minWords = max(300, $minWordsTo);

$urlCacheTtlSeconds = getSettingInt('url_cache_ttl_seconds', 900, 60, 86400);
$workflowBatchSize = getSettingInt('workflow_batch_size', 8, 1, 50);
$queueRetryDelaySeconds = getSettingInt('queue_retry_delay_seconds', 60, 5, 7200);
$queueMaxAttempts = getSettingInt('queue_max_attempts', 3, 1, 20);
$fetchTimeoutSeconds = getSettingInt('fetch_timeout_seconds', 12, 3, 45);
$fetchRetryAttempts = getSettingInt('fetch_retry_attempts', 3, 1, 5);
$fetchRetryBackoffMs = getSettingInt('fetch_retry_backoff_ms', 350, 100, 3000);
$queueSourceCooldownSeconds = getSettingInt('queue_source_cooldown_seconds', 180, 30, 7200);
$fetchUserAgent = (string)getSetting('fetch_user_agent', 'Mozilla/5.0 (compatible; VitoBot/1.0; +https://example.com/bot)');
$visitExcludedIps = (string)getSetting('visit_excluded_ips', '');
$apiLockEnabled = getSettingInt('api_lock_enabled', 0, 0, 1) === 1;
$apiAccessKey = (string)getSetting('api_access_key', '');

$detectedVisitorIp = getVisitorIpAddress();
$selectedWorkflow = getSelectedContentWorkflow();
$workflowSummary = getContentWorkflowSummary();
$autoAiEnabled = getSettingInt('auto_ai_enabled', 1, 0, 1);
$autoPublishInterval = getAutoPublishIntervalSeconds();
$autoPublishIntervalFrom = getSettingInt('auto_publish_interval_seconds_from', 0, 0, PHP_INT_MAX);
$autoPublishIntervalTo = getSettingInt('auto_publish_interval_seconds_to', $autoPublishInterval, 0, PHP_INT_MAX);
if ($autoPublishIntervalTo < $autoPublishIntervalFrom) {
    [$autoPublishIntervalFrom, $autoPublishIntervalTo] = [$autoPublishIntervalTo, $autoPublishIntervalFrom];
}
$autoPublishIntervalMinutes = max(1, (int)round($autoPublishInterval / 60));
$autoPublishLastRun = (string)getSetting('auto_publish_last_run_at', '1970-01-01 00:00:00');

// Auto Titles
$autoTitleDefaults = getAutoTitleDefaultSettings();
$autoTitleMode = (string)getAutoTitleSetting('auto_title_mode');
if (!in_array($autoTitleMode, ['template', 'list'], true)) {
    $autoTitleMode = 'template';
}
$autoTitleMinYearOffset = max(-1, min(2, (int)getAutoTitleSetting('auto_title_min_year_offset')));
$autoTitleMaxYearOffset = max(-1, min(3, (int)getAutoTitleSetting('auto_title_max_year_offset')));
$autoTitleBrands = (string)getAutoTitleSetting('auto_title_brands');
$autoTitleModels = (string)getAutoTitleSetting('auto_title_models');
$autoTitleModifiers = (string)getAutoTitleSetting('auto_title_modifiers');
$autoTitleAudiences = (string)getAutoTitleSetting('auto_title_audiences');
$autoTitleAngles = (string)getAutoTitleSetting('auto_title_angles');
$autoTitleTemplates = (string)getAutoTitleSetting('auto_title_templates');
$autoTitleFixedTitles = (string)getAutoTitleSetting('auto_title_fixed_titles');

$cronUrl = getCronEndpointUrl();

// Get categories
$categoryOptionsStmt = $pdo->prepare("SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$categoryOptionsStmt->execute();
$categoryOptions = $categoryOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get articles
$articleSql = "SELECT id, title, slug, category, excerpt, image, image2, translated_title, translated_content, content, published_at FROM articles";
$articleParams = [];
$articleClauses = [];
if ($articleSearch !== '') {
    $articleClauses[] = "title LIKE :title";
    $articleParams['title'] = '%' . $articleSearch . '%';
}
if ($articleCategory !== '') {
    $articleClauses[] = "category = :cat";
    $articleParams['cat'] = $articleCategory;
}
if ($articleClauses) {
    $articleSql .= ' WHERE ' . implode(' AND ', $articleClauses);
}
$articleSql .= " ORDER BY id DESC LIMIT 20";
$articleStmt = $pdo->prepare($articleSql);
foreach ($articleParams as $key => $value) {
    $articleStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$articleStmt->execute();
$articles = $articleStmt->fetchAll(PDO::FETCH_ASSOC);

// Get RSS sources
$rssSql = "SELECT id, url FROM rss_sources";
$rssParams = [];
if ($rssSearch !== '') {
    $rssSql .= " WHERE url LIKE :url";
    $rssParams['url'] = '%' . $rssSearch . '%';
}
$rssSql .= " ORDER BY id DESC";
$rssStmt = $pdo->prepare($rssSql);
foreach ($rssParams as $key => $value) {
    $rssStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$rssStmt->execute();
$rssRows = $rssStmt->fetchAll(PDO::FETCH_ASSOC);

// Get Web sources
$webSql = "SELECT id, url FROM web_sources";
$webParams = [];
if ($webSearch !== '') {
    $webSql .= " WHERE url LIKE :url";
    $webParams['url'] = '%' . $webSearch . '%';
}
$webSql .= " ORDER BY id DESC";
$webStmt = $pdo->prepare($webSql);
foreach ($webParams as $key => $value) {
    $webStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$webStmt->execute();
$webRows = $webStmt->fetchAll(PDO::FETCH_ASSOC);

// Get settings
$settingsSearch = trim($_GET['qs'] ?? '');
$settingsSql = "SELECT key, value FROM settings";
$settingsParams = [];
if ($settingsSearch !== '') {
    $settingsSql .= " WHERE key LIKE :setting_key";
    $settingsParams['setting_key'] = '%' . $settingsSearch . '%';
}
$settingsSql .= " ORDER BY key ASC";
$settingsStmt = $pdo->prepare($settingsSql);
foreach ($settingsParams as $key => $value) {
    $settingsStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$settingsStmt->execute();
$settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get config file info
$configFilePath = __DIR__ . '/../../config.txt';
$configPreview = is_file($configFilePath) ? file_get_contents($configFilePath) : '';
$configContents = loadConfigTxt($configFilePath);
$configGlobalRssCount = count($configContents['sources']['rss'] ?? []);
$configGlobalWebCount = count($configContents['sources']['web'] ?? []);
$configGlobalSettingsCount = count($configContents['settings'] ?? []);
$configNichesCount = count($configContents['niches'] ?? []);
$configFingerprint = $configContents['fingerprint'] ?? '';
