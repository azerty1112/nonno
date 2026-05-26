<?php
require_once 'functions.php';

if (!function_exists('endsWith')) {
    function endsWith($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

$siteTitle = getSiteTitle();

$maxAttempts = 5;
$lockSeconds = 60;
$now = time();

$_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0);
$_SESSION['login_lock_until'] = (int)($_SESSION['login_lock_until'] ?? 0);

$isLocked = $_SESSION['login_lock_until'] > $now;
$remainingLockSeconds = max(0, $_SESSION['login_lock_until'] - $now);

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (empty($_SESSION['logged']) && $requestMethod === 'POST' && isset($_POST['pass'])) {
    $submittedPassword = (string)($_POST['pass'] ?? '');

    if ($isLocked) {
        $_SESSION['login_error'] = 'Too many attempts. Try again in ' . $remainingLockSeconds . ' seconds.';
    } elseif ($submittedPassword === '') {
        $_SESSION['login_error'] = 'Password is required.';
    } elseif (verifyAdminPassword($submittedPassword)) {
        session_regenerate_id(true);
        $_SESSION['logged'] = true;
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lock_until'] = 0;
        unset($_SESSION['login_error']);
    } else {
        $_SESSION['login_attempts']++;

        if ($_SESSION['login_attempts'] >= $maxAttempts) {
            $_SESSION['login_lock_until'] = $now + $lockSeconds;
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_error'] = 'Too many attempts. Login locked for ' . $lockSeconds . ' seconds.';
        } else {
            $remaining = $maxAttempts - $_SESSION['login_attempts'];
            $_SESSION['login_error'] = 'Invalid password. Remaining attempts: ' . $remaining . '.';
        }
    }

    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['logged'])) {
    $loginError = $_SESSION['login_error'] ?? null;
    unset($_SESSION['login_error']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Login - <?= e($siteTitle) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                min-height: 100vh;
                background: radial-gradient(circle at top, #1f2937, #0b1120 65%);
            }
            .login-card {
                max-width: 430px;
                border: 1px solid rgba(255, 255, 255, 0.12);
                backdrop-filter: blur(8px);
            }
        </style>
    </head>
    <body class="d-flex align-items-center justify-content-center text-light p-3">
    <main class="card bg-dark shadow-lg login-card w-100">
        <div class="card-body p-4 p-md-5">
            <h1 class="h4 mb-3 text-center"><?= e($siteTitle) ?> Admin</h1>
            <p class="text-secondary text-center mb-4">Secure access to content management dashboard.</p>

            <?php if ($loginError): ?>
                <div class="alert alert-danger py-2 small mb-3" role="alert"><?= e($loginError) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <label for="pass" class="form-label">Password</label>
                <input id="pass" type="password" name="pass" class="form-control form-control-lg" placeholder="Enter admin password" autocomplete="current-password" required autofocus>
                <button class="btn btn-primary w-100 mt-3">Login</button>
            </form>

            <small class="d-block text-secondary mt-3 text-center">Tip: set <code>ADMIN_PASSWORD</code> env var for production.</small>
        </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$pdo = db_connect();
$csrf = csrfToken();
publishAutoArticleBySchedule();

if (!isset($_SESSION['flash_message'])) {
    $_SESSION['flash_message'] = null;
    $_SESSION['flash_type'] = 'info';
}

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

    if (isset($_POST['update_admin_password'])) {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!verifyAdminPassword($currentPassword)) {
            $_SESSION['flash_message'] = 'Current password is incorrect.';
            $_SESSION['flash_type'] = 'danger';
        } elseif (strlen($newPassword) < 8) {
            $_SESSION['flash_message'] = 'New password must be at least 8 characters.';
            $_SESSION['flash_type'] = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['flash_message'] = 'New password and confirmation do not match.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            setSetting('admin_password_hash', password_hash($newPassword, PASSWORD_DEFAULT));
            $_SESSION['flash_message'] = 'Admin password updated successfully.';
            $_SESSION['flash_type'] = 'success';
        }

        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_daily_limit'])) {
        $newLimit = (int)($_POST['daily_limit'] ?? 5);
        $newLimit = max(1, min(200, $newLimit));
        setSetting('daily_limit', (string)$newLimit);
        $_SESSION['flash_message'] = 'Daily workflow generation limit updated.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_seo_settings'])) {
        $configuredSiteTitle = trim((string)($_POST['site_title'] ?? ''));
        $homeTitle = trim((string)($_POST['seo_home_title'] ?? ''));
        $homeDescription = trim((string)($_POST['seo_home_description'] ?? ''));
        $articleTitleSuffix = trim((string)($_POST['seo_article_title_suffix'] ?? ''));
        $defaultRobots = trim((string)($_POST['seo_default_robots'] ?? 'index,follow'));
        $defaultOgImage = trim((string)($_POST['seo_default_og_image'] ?? ''));
        $twitterSite = trim((string)($_POST['seo_twitter_site'] ?? ''));
        $imageAltSuffix = trim((string)($_POST['seo_image_alt_suffix'] ?? ''));
        $imageTitleSuffix = trim((string)($_POST['seo_image_title_suffix'] ?? ''));
        $seoAutoLinkRules = trim((string)($_POST['seo_auto_link_rules'] ?? ''));
        $seoAutoLinkAutoInternal = isset($_POST['seo_auto_link_auto_internal']) ? 1 : 0;
        $seoAutoLinkMaxPerArticle = (int)($_POST['seo_auto_link_max_per_article'] ?? 3);

        if (mb_strlen($configuredSiteTitle) > 80) {
            $configuredSiteTitle = mb_substr($configuredSiteTitle, 0, 80);
        }
        if (mb_strlen($homeTitle) > 120) {
            $homeTitle = mb_substr($homeTitle, 0, 120);
        }
        if (mb_strlen($homeDescription) > 160) {
            $homeDescription = mb_substr($homeDescription, 0, 160);
        }
        if (mb_strlen($articleTitleSuffix) > 80) {
            $articleTitleSuffix = mb_substr($articleTitleSuffix, 0, 80);
        }
        if (!in_array($defaultRobots, ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'], true)) {
            $defaultRobots = 'index,follow';
        }
        if (mb_strlen($defaultOgImage) > 500) {
            $defaultOgImage = mb_substr($defaultOgImage, 0, 500);
        }
        if ($twitterSite !== '' && mb_substr($twitterSite, 0, 1) !== '@') {
            $twitterSite = '@' . ltrim($twitterSite, '@');
        }
        if (mb_strlen($twitterSite) > 40) {
            $twitterSite = mb_substr($twitterSite, 0, 40);
        }
        if (mb_strlen($imageAltSuffix) > 80) {
            $imageAltSuffix = mb_substr($imageAltSuffix, 0, 80);
        }
        if (mb_strlen($imageTitleSuffix) > 80) {
            $imageTitleSuffix = mb_substr($imageTitleSuffix, 0, 80);
        }
        if (mb_strlen($seoAutoLinkRules) > 12000) {
            $seoAutoLinkRules = mb_substr($seoAutoLinkRules, 0, 12000);
        }
        $seoAutoLinkMaxPerArticle = max(1, min(10, $seoAutoLinkMaxPerArticle));

        setSetting('site_title', $configuredSiteTitle !== '' ? $configuredSiteTitle : SITE_TITLE);
        setSetting('seo_home_title', $homeTitle);
        setSetting('seo_home_description', $homeDescription);
        setSetting('seo_article_title_suffix', $articleTitleSuffix);
        setSetting('seo_default_robots', $defaultRobots);
        setSetting('seo_default_og_image', $defaultOgImage);
        setSetting('seo_twitter_site', $twitterSite);
        setSetting('seo_image_alt_suffix', $imageAltSuffix);
        setSetting('seo_image_title_suffix', $imageTitleSuffix);
        setSetting('seo_auto_link_rules', $seoAutoLinkRules);
        setSetting('seo_auto_link_auto_internal', (string)$seoAutoLinkAutoInternal);
        setSetting('seo_auto_link_max_per_article', (string)$seoAutoLinkMaxPerArticle);

        // translation settings
        $autoTranslateEnabled = isset($_POST['auto_translate_enabled']) ? 1 : 0;
        $autoTranslateTarget = trim((string)($_POST['auto_translate_target_language'] ?? ''));
        if (mb_strlen($autoTranslateTarget) > 5) {
            $autoTranslateTarget = mb_substr($autoTranslateTarget, 0, 5);
        }
        setSetting('auto_translate_enabled', (string)$autoTranslateEnabled);
        setSetting('auto_translate_target_language', $autoTranslateTarget);

        // regenerate static pages when SEO metadata changes
        exportStaticPages();

        $_SESSION['flash_message'] = 'SEO settings updated successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['ping_sitemap'])) {
        $sitemap = getSiteBaseUrl() . '/sitemap.php';
        pingSearchEngines($sitemap);
        updateRobotsTxt();
        $_SESSION['flash_message'] = 'Search engines notified (sitemap pinged).';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_scripts_settings'])) {
        $googleAnalyticsId = strtoupper(trim((string)($_POST['google_analytics_id'] ?? '')));
        $googleTagManagerId = strtoupper(trim((string)($_POST['google_tag_manager_id'] ?? '')));
        $googleSiteVerification = trim((string)($_POST['google_site_verification'] ?? ''));
        $bingSiteVerification = trim((string)($_POST['bing_site_verification'] ?? ''));
        $metaPixelId = preg_replace('/[^0-9]/', '', trim((string)($_POST['meta_pixel_id'] ?? '')));
        $customHeadScripts = trim((string)($_POST['custom_head_scripts'] ?? ''));
        $customBodyScripts = trim((string)($_POST['custom_body_scripts'] ?? ''));

        if ($googleAnalyticsId !== '' && !preg_match('/^(G-[A-Z0-9]+|GT-[A-Z0-9]+|UA-\d+-\d+|AW-\d+)$/', $googleAnalyticsId)) {
            $googleAnalyticsId = '';
        }
        if ($googleTagManagerId !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $googleTagManagerId)) {
            $googleTagManagerId = '';
        }
        if (mb_strlen($googleSiteVerification) > 255) {
            $googleSiteVerification = mb_substr($googleSiteVerification, 0, 255);
        }
        if (mb_strlen($bingSiteVerification) > 255) {
            $bingSiteVerification = mb_substr($bingSiteVerification, 0, 255);
        }
        if ($metaPixelId !== '' && mb_strlen($metaPixelId) > 30) {
            $metaPixelId = mb_substr($metaPixelId, 0, 30);
        }
        if (mb_strlen($customHeadScripts) > 20000) {
            $customHeadScripts = mb_substr($customHeadScripts, 0, 20000);
        }
        if (mb_strlen($customBodyScripts) > 20000) {
            $customBodyScripts = mb_substr($customBodyScripts, 0, 20000);
        }

        setSetting('google_analytics_id', $googleAnalyticsId);
        setSetting('google_tag_manager_id', $googleTagManagerId);
        setSetting('google_site_verification', $googleSiteVerification);
        setSetting('bing_site_verification', $bingSiteVerification);
        setSetting('meta_pixel_id', $metaPixelId);
        setSetting('custom_head_scripts', $customHeadScripts);
        setSetting('custom_body_scripts', $customBodyScripts);
        // optional ads.txt content (also persisted to file)
        $adsTxtContent = trim((string)($_POST['ads_txt_content'] ?? ''));
        if (mb_strlen($adsTxtContent) > 100000) {
            $adsTxtContent = mb_substr($adsTxtContent, 0, 100000);
        }
        setSetting('ads_txt', $adsTxtContent);
        @file_put_contents(__DIR__ . '/ads.txt', $adsTxtContent);
        // since sitemap or base url might be referenced elsewhere, refresh robots.txt too
        updateRobotsTxt();

        $_SESSION['flash_message'] = 'Scripts settings updated successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }


    if (isset($_POST['update_ads_settings'])) {
        $adsEnabled = isset($_POST['ads_enabled']) ? 1 : 0;
        $adsMode = trim((string)($_POST['ads_injection_mode'] ?? 'smart'));
        if (!in_array($adsMode, ['smart', 'interval'], true)) {
            $adsMode = 'smart';
        }

        $adsInterval = (int)($_POST['ads_paragraph_interval'] ?? 4);
        $adsInterval = max(2, min(10, $adsInterval));

        $adsMaxUnits = (int)($_POST['ads_max_units_per_article'] ?? 2);
        $adsMaxUnits = max(1, min(6, $adsMaxUnits));

        $adsMinWords = (int)($_POST['ads_min_words_before_first_injection'] ?? 180);
        $adsMinWords = max(80, min(600, $adsMinWords));

        $adsMinArticleWords = (int)($_POST['ads_min_article_words'] ?? 420);
        $adsMinArticleWords = max(120, min(3000, $adsMinArticleWords));

        $adsBlockedTitleKeywords = trim((string)($_POST['ads_blocked_title_keywords'] ?? ''));
        if (mb_strlen($adsBlockedTitleKeywords) > 300) {
            $adsBlockedTitleKeywords = mb_substr($adsBlockedTitleKeywords, 0, 300);
        }

        $adsBlockedCategories = trim((string)($_POST['ads_blocked_categories'] ?? ''));
        if (mb_strlen($adsBlockedCategories) > 300) {
            $adsBlockedCategories = mb_substr($adsBlockedCategories, 0, 300);
        }

        $adsLabel = trim((string)($_POST['ads_label_text'] ?? 'Sponsored'));
        if ($adsLabel === '') {
            $adsLabel = 'Sponsored';
        }
        if (mb_strlen($adsLabel) > 40) {
            $adsLabel = mb_substr($adsLabel, 0, 40);
        }

        $adsHtmlCode = trim((string)($_POST['ads_html_code'] ?? ''));
        if ($adsHtmlCode === '') {
            $adsHtmlCode = '<div class="ad-unit-inner">Place your ad code here</div>';
        }
        if (mb_strlen($adsHtmlCode) > 12000) {
            $adsHtmlCode = mb_substr($adsHtmlCode, 0, 12000);
        }

        setSetting('ads_enabled', (string)$adsEnabled);
        setSetting('ads_injection_mode', $adsMode);
        setSetting('ads_paragraph_interval', (string)$adsInterval);
        setSetting('ads_max_units_per_article', (string)$adsMaxUnits);
        setSetting('ads_min_words_before_first_injection', (string)$adsMinWords);
        setSetting('ads_min_article_words', (string)$adsMinArticleWords);
        setSetting('ads_blocked_title_keywords', $adsBlockedTitleKeywords);
        setSetting('ads_blocked_categories', $adsBlockedCategories);
        setSetting('ads_label_text', $adsLabel);
        setSetting('ads_html_code', $adsHtmlCode);

        $_SESSION['flash_message'] = 'Ad placement controls updated successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_fetch_settings'])) {
        $timeout = (int)($_POST['fetch_timeout_seconds'] ?? 12);
        $timeout = max(3, min(45, $timeout));
        $retryAttempts = (int)($_POST['fetch_retry_attempts'] ?? 3);
        $retryAttempts = max(1, min(5, $retryAttempts));
        $retryBackoffMs = (int)($_POST['fetch_retry_backoff_ms'] ?? 350);
        $retryBackoffMs = max(100, min(3000, $retryBackoffMs));
        $sourceCooldown = (int)($_POST['queue_source_cooldown_seconds'] ?? 180);
        $sourceCooldown = max(30, min(7200, $sourceCooldown));
        $userAgent = trim((string)($_POST['fetch_user_agent'] ?? ''));
        if ($userAgent === '') {
            $userAgent = 'Mozilla/5.0 (compatible; VitoBot/1.0; +https://example.com/bot)';
        }
        if (mb_strlen($userAgent) > 255) {
            $userAgent = mb_substr($userAgent, 0, 255);
        }

        setSetting('fetch_timeout_seconds', (string)$timeout);
        setSetting('fetch_retry_attempts', (string)$retryAttempts);
        setSetting('fetch_retry_backoff_ms', (string)$retryBackoffMs);
        setSetting('queue_source_cooldown_seconds', (string)$sourceCooldown);
        setSetting('fetch_user_agent', $userAgent);
        $_SESSION['flash_message'] = 'Fetcher timeout, retries, cooldown, and user-agent updated.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_pipeline_settings'])) {
        $minWordsFrom = (int)($_POST['min_words_from'] ?? 300);
        $minWordsTo = (int)($_POST['min_words_to'] ?? 3000);
        $minWordsFrom = max(0, min(300000, $minWordsFrom));
        $minWordsTo = max(0, min(300000, $minWordsTo));
        if ($minWordsTo < $minWordsFrom) {
            [$minWordsFrom, $minWordsTo] = [$minWordsTo, $minWordsFrom];
        }
        $minWords = max(300, $minWordsTo);

        $cacheTtl = (int)($_POST['url_cache_ttl_seconds'] ?? 900);
        $cacheTtl = max(60, min(86400, $cacheTtl));

        $batchSize = (int)($_POST['workflow_batch_size'] ?? 8);
        $batchSize = max(1, min(50, $batchSize));

        $queueRetryDelay = (int)($_POST['queue_retry_delay_seconds'] ?? 60);
        $queueRetryDelay = max(5, min(7200, $queueRetryDelay));

        $queueMaxAttempts = (int)($_POST['queue_max_attempts'] ?? 3);
        $queueMaxAttempts = max(1, min(20, $queueMaxAttempts));

        $intervalMinutes = (int)($_POST['auto_publish_interval_minutes'] ?? 180);
        $intervalMinutes = max(1, $intervalMinutes);

        $visitExcludedIps = trim((string)($_POST['visit_excluded_ips'] ?? ''));
        if (mb_strlen($visitExcludedIps) > 4000) {
            $visitExcludedIps = mb_substr($visitExcludedIps, 0, 4000);
        }
        $visitExcludedIps = normalizeExcludedIpRules($visitExcludedIps);

        $apiLockEnabled = isset($_POST['api_lock_enabled']) ? 1 : 0;
        $apiAccessKey = trim((string)($_POST['api_access_key'] ?? ''));
        if (mb_strlen($apiAccessKey) > 255) {
            $apiAccessKey = mb_substr($apiAccessKey, 0, 255);
        }

        if ($apiLockEnabled === 1 && $apiAccessKey === '') {
            $_SESSION['flash_message'] = 'API lock is enabled, so API access key cannot be empty.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        setSetting('min_words_from', (string)$minWordsFrom);
        setSetting('min_words_to', (string)$minWordsTo);
        setSetting('min_words', (string)$minWords);
        setSetting('url_cache_ttl_seconds', (string)$cacheTtl);
        setSetting('workflow_batch_size', (string)$batchSize);
        setSetting('queue_retry_delay_seconds', (string)$queueRetryDelay);
        setSetting('queue_max_attempts', (string)$queueMaxAttempts);
        setSetting('auto_publish_interval_minutes', (string)$intervalMinutes);
        setSetting('visit_excluded_ips', $visitExcludedIps);
        setSetting('api_lock_enabled', (string)$apiLockEnabled);
        setSetting('api_access_key', $apiAccessKey);

        // Keep minute + second based scheduler settings synchronized.
        $secondsFromMinutes = $intervalMinutes > intdiv(PHP_INT_MAX, 60) ? PHP_INT_MAX : ($intervalMinutes * 60);
        setSetting('auto_publish_interval_seconds', (string)max(1, $secondsFromMinutes));

        $_SESSION['flash_message'] = 'Pipeline configuration updated successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }


    if (isset($_POST['update_auto_scheduler'])) {
        $enabled = isset($_POST['auto_ai_enabled']) ? 1 : 0;
        $intervalFrom = (int)($_POST['auto_publish_interval_seconds_from'] ?? 1);
        $intervalTo = (int)($_POST['auto_publish_interval_seconds_to'] ?? 10800);
        $intervalFrom = max(0, min(300000, $intervalFrom));
        $intervalTo = max(0, min(300000, $intervalTo));
        if ($intervalTo < $intervalFrom) {
            [$intervalFrom, $intervalTo] = [$intervalTo, $intervalFrom];
        }
        $interval = max(1, $intervalTo);

        setSetting('auto_ai_enabled', (string)$enabled);
        setSetting('auto_publish_interval_seconds_from', (string)$intervalFrom);
        setSetting('auto_publish_interval_seconds_to', (string)$intervalTo);
        setSetting('auto_publish_interval_seconds', (string)$interval);

        $_SESSION['flash_message'] = 'Automatic AI publishing settings updated.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_smart_niche_automation'])) {
        $nicheSlug = trim((string)($_POST['smart_active_niche'] ?? ''));
        $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
        $validNicheSlug = '';

        if ($nicheSlug !== '' && class_exists('App\\NicheManager')) {
            $candidateNiche = \App\NicheManager::getNicheBySlug($nicheSlug);
            if ($candidateNiche) {
                $validNicheSlug = (string)($candidateNiche['slug'] ?? '');
                $activeNicheSlug = $validNicheSlug;
                setSetting('active_niche', $validNicheSlug);
            }
        }

        $enabled = isset($_POST['smart_auto_ai_enabled']) ? 1 : 0;
        $intervalFrom = (int)($_POST['smart_auto_publish_interval_seconds_from'] ?? 1);
        $intervalTo = (int)($_POST['smart_auto_publish_interval_seconds_to'] ?? 10800);
        $intervalFrom = max(1, min(300000, $intervalFrom));
        $intervalTo = max(1, min(300000, $intervalTo));
        if ($intervalTo < $intervalFrom) {
            [$intervalFrom, $intervalTo] = [$intervalTo, $intervalFrom];
        }
        $interval = max(1, $intervalTo);

        $mode = trim((string)($_POST['smart_auto_title_mode'] ?? 'template'));
        if (!in_array($mode, ['template', 'list'], true)) {
            $mode = 'template';
        }

        $minYearOffset = (int)($_POST['smart_auto_title_min_year_offset'] ?? 0);
        $maxYearOffset = (int)($_POST['smart_auto_title_max_year_offset'] ?? 1);
        $minYearOffset = max(-1, min(2, $minYearOffset));
        $maxYearOffset = max(-1, min(3, $maxYearOffset));
        if ($maxYearOffset < $minYearOffset) {
            [$minYearOffset, $maxYearOffset] = [$maxYearOffset, $minYearOffset];
        }

        setSetting('auto_ai_enabled', (string)$enabled);
        setSetting('auto_publish_interval_seconds_from', (string)$intervalFrom);
        setSetting('auto_publish_interval_seconds_to', (string)$intervalTo);
        setSetting('auto_publish_interval_seconds', (string)$interval);

        $nichePrefix = 'niche.' . ($activeNicheSlug !== '' ? $activeNicheSlug : 'general') . '.';
        setSetting($nichePrefix . 'auto_title_mode', $mode);
        setSetting($nichePrefix . 'auto_title_min_year_offset', (string)$minYearOffset);
        setSetting($nichePrefix . 'auto_title_max_year_offset', (string)$maxYearOffset);
        setSetting('smart_source_prefill_niche', $activeNicheSlug);

        $smartMsg = 'Smart automation hub updated (niche + scheduler + title mode).';
        $smartFlashType = 'success';
        if ($nicheSlug !== '' && $validNicheSlug === '') {
            $smartMsg .= ' Niche not found, keeping previous active niche.';
            $smartFlashType = 'warning';
        }
        $_SESSION['flash_message'] = $smartMsg;
        $_SESSION['flash_type'] = $smartFlashType;
        header('Location: admin.php#auto-scheduler-section');
        exit;
    }

    if (isset($_POST['update_auto_title_settings'])) {
        $mode = trim((string)($_POST['auto_title_mode'] ?? 'template'));
        if (!in_array($mode, ['template', 'list'], true)) {
            $mode = 'template';
        }

        $minYearOffset = (int)($_POST['auto_title_min_year_offset'] ?? 0);
        $maxYearOffset = (int)($_POST['auto_title_max_year_offset'] ?? 1);
        $minYearOffset = max(-1, min(2, $minYearOffset));
        $maxYearOffset = max(-1, min(3, $maxYearOffset));
        if ($maxYearOffset < $minYearOffset) {
            [$minYearOffset, $maxYearOffset] = [$maxYearOffset, $minYearOffset];
        }

        $fields = [
            'auto_title_brands',
            'auto_title_models',
            'auto_title_modifiers',
            'auto_title_audiences',
            'auto_title_angles',
            'auto_title_templates',
            'auto_title_fixed_titles',
        ];

        $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
        $titlePrefix = 'niche.' . $activeNicheSlug . '.';
        setSetting($titlePrefix . 'auto_title_mode', $mode);
        setSetting($titlePrefix . 'auto_title_min_year_offset', (string)$minYearOffset);
        setSetting($titlePrefix . 'auto_title_max_year_offset', (string)$maxYearOffset);

        foreach ($fields as $fieldKey) {
            $raw = trim((string)($_POST[$fieldKey] ?? ''));
            if (mb_strlen($raw) > 10000) {
                $raw = mb_substr($raw, 0, 10000);
            }

            $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
            $clean = [];
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    $clean[] = $line;
                }
            }

            if ($fieldKey === 'auto_title_templates') {
                $allowedVars = ['{year}', '{brand}', '{model}', '{modifier}', '{angle}', '{audience}'];
                $validated = [];
                foreach ($clean as $tpl) {
                    $hasVar = false;
                    foreach ($allowedVars as $var) {
                        if (mb_strpos($tpl, $var) !== false) {
                            $hasVar = true;
                            break;
                        }
                    }
                    if ($hasVar) {
                        $validated[] = $tpl;
                    }
                }
                $clean = $validated;
            }

            $value = implode("\n", array_values(array_unique($clean)));
            setSetting($titlePrefix . $fieldKey, $value);
        }

        $_SESSION['flash_message'] = 'Auto title generation controls updated.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['preview_auto_titles'])) {
        $samples = [];
        for ($i = 0; $i < 5; $i++) {
            $samples[] = generateAutoTitle();
        }
        $_SESSION['flash_message'] = 'Title preview: ' . implode(' | ', $samples);
        $_SESSION['flash_type'] = 'info';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['reset_auto_title_defaults'])) {
        $defaults = getAutoTitleDefaultSettings();
        $activeNicheSlug = trim((string)getSetting('active_niche', 'general'));
        $titlePrefix = 'niche.' . $activeNicheSlug . '.';
        foreach ($defaults as $k => $v) {
            setSetting($titlePrefix . $k, (string)$v);
        }
        $_SESSION['flash_message'] = 'Auto title controls reset to defaults.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['auto_generate_now'])) {
        $result = publishAutoArticleBySchedule(true);
        if (($result['published'] ?? 0) === 1) {
            $_SESSION['flash_message'] = 'Auto-generated and published: ' . ($result['title'] ?? 'New article');
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Automatic generation failed. Please try again.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['add_titles'])) {
        $rawTitles = array_map('trim', explode("\n", $_POST['titles'] ?? ''));
        $titles = array_values(array_unique(array_filter($rawTitles, function ($t) {
            return mb_strlen((string)$t) >= 5;
        })));
        $generated = 0;

        foreach ($titles as $title) {
            if (!articleExists($title)) {
                $data = generateArticle($title);
                if (saveArticle($title, $data)) {
                    $generated++;
                }
            }
        }

        $_SESSION['flash_message'] = "Generated {$generated} new article(s).";
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['generate_demo_pack'])) {
        $demoTitles = [
            '2026 Porsche Taycan Turbo GT Track Review',
            'Best Hybrid SUVs for Families in 2026',
            'Mercedes-AMG C63 Daily Driving Impressions',
            'How Fast Charging Changed EV Road Trips',
            'Budget Performance Cars Worth Buying This Year',
        ];
        $generated = 0;
        foreach ($demoTitles as $title) {
            if (!articleExists($title)) {
                $data = generateArticle($title);
                if (saveArticle($title, $data)) {
                    $generated++;
                }
            }
        }

        $_SESSION['flash_message'] = "Demo content pack generated: {$generated} new article(s).";
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['run_content_workflow'])) {
        $result = runSelectedContentWorkflow();
        $workflowName = ($result['workflow'] ?? 'rss') === 'web' ? 'Normal Sites' : 'RSS';
        $published = (int)($result['published'] ?? 0);
        $sourcesCount = (int)($result['sources_count'] ?? 0);

        $_SESSION['flash_message'] = "Workflow {$workflowName}: published {$published} new article(s) from {$sourcesCount} source(s).";
        $_SESSION['flash_type'] = 'info';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['fill_smart_sources_10_per_niche'])) {
        $nichesForFillStmt = $pdo->prepare("SELECT slug, name FROM niches ORDER BY id");
        $nichesForFillStmt->execute();
        $nichesForFill = $nichesForFillStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $presetUrls = [];
        foreach ($nichesForFill as $n) {
            $slug = trim((string)($n['slug'] ?? 'general'));
            if ($slug === '') $slug = 'general';
            $safe = preg_replace('/[^a-z0-9\-]/i', '-', strtolower($slug));
            for ($i = 1; $i <= 10; $i++) {
                $presetUrls[] = "https://{$safe}.news-source{$i}.example/feed.xml";
            }
        }
        setSetting('smart_source_prefill_bulk', implode("\n", $presetUrls));
        $_SESSION['flash_message'] = 'تم تجهيز قائمة كبيرة: 10 روابط RSS لكل نيش. يمكنك تعديلها ثم حفظها.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php#auto-scheduler-section');
        exit;
    }

    if (isset($_POST['fill_all_smart_hub_fields'])) {
        $nichesForFillStmt = $pdo->prepare("SELECT slug, name FROM niches ORDER BY id");
        $nichesForFillStmt->execute();
        $nichesForFill = $nichesForFillStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $firstSlug = (string)($nichesForFill[0]['slug'] ?? 'general');
        if ($firstSlug === '') $firstSlug = 'general';
        setSetting('active_niche', $firstSlug);
        setSetting('auto_title_mode', 'template');
        setSetting('auto_publish_interval_seconds_from', '1800');
        setSetting('auto_publish_interval_seconds_to', '7200');
        setSetting('auto_ai_enabled', '1');
        setSetting('smart_source_prefill_url', 'https://news.google.com/rss/search?q=' . rawurlencode($firstSlug));
        setSetting('smart_source_prefill_type', 'rss');
        setSetting('smart_source_prefill_niche', $firstSlug);
        setSetting('smart_source_prefill_selected_niches', $firstSlug);

        $defaultTitleFields = getAutoTitleDefaultSettings();
        foreach (['auto_title_brands', 'auto_title_models', 'auto_title_modifiers', 'auto_title_audiences', 'auto_title_angles', 'auto_title_templates', 'auto_title_fixed_titles'] as $fieldKey) {
            setSetting('niche.' . $firstSlug . '.' . $fieldKey, (string)($defaultTitleFields[$fieldKey] ?? ''));
        }

        setSetting('niche.' . $firstSlug . '.auto_title_mode', 'template');
        setSetting('niche.' . $firstSlug . '.auto_title_min_year_offset', '0');
        setSetting('niche.' . $firstSlug . '.auto_title_max_year_offset', '1');

        $presetUrls = [];
        foreach ($nichesForFill as $n) {
            $slug = trim((string)($n['slug'] ?? 'general'));
            if ($slug === '') $slug = 'general';
            $safe = preg_replace('/[^a-z0-9\-]/i', '-', strtolower($slug));
            for ($i = 1; $i <= 10; $i++) {
                $presetUrls[] = "https://{$safe}.news-source{$i}.example/feed.xml";
            }
        }
        setSetting('smart_source_prefill_bulk', implode("\n", $presetUrls));
        $_SESSION['flash_message'] = 'تم ملء كل الخانات تلقائياً مع 10 RSS لكل نيش.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php#auto-scheduler-section');
        exit;
    }

    if (isset($_POST['fill_closed_fields_all_niches'])) {
        $nichesForFillStmt = $pdo->prepare("SELECT slug FROM niches ORDER BY id");
        $nichesForFillStmt->execute();
        $nichesForFill = $nichesForFillStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allSlugs = [];
        foreach ($nichesForFill as $n) {
            $slug = trim((string)($n['slug'] ?? ''));
            if ($slug !== '') {
                $allSlugs[] = $slug;
            }
        }
        $allSlugs = array_values(array_unique($allSlugs));
        $firstSlug = (string)($allSlugs[0] ?? 'general');
        setSetting('smart_source_prefill_selected_niches', implode(',', $allSlugs));
        setSetting('smart_source_prefill_niche', $firstSlug);
        setSetting('active_niche', $firstSlug);
        $_SESSION['flash_message'] = 'تم تعبئة الخانات المغلقة بكل النيشات المتاحة.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php#auto-scheduler-section');
        exit;
    }

    if (isset($_POST['add_source_smart'])) {
        $smartUrl = trim((string)($_POST['smart_source_url'] ?? ''));
        $smartUrlsBulk = trim((string)($_POST['smart_source_urls'] ?? ''));
        $smartNicheSlug = trim((string)($_POST['smart_target_niche_slug'] ?? ''));
        if ($smartNicheSlug === '') {
            $smartNicheSlug = trim((string)getSetting('active_niche', ''));
        }
        $smartType = trim((string)($_POST['smart_source_type'] ?? 'auto'));

        $normalizeSmartUrl = static function ($url) {
            $url = trim((string)$url);
            if ($url === '') return '';
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . ltrim($url, '/');
            }
            $parts = parse_url($url);
            if (!is_array($parts)) return '';
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            $host = strtolower((string)($parts['host'] ?? ''));
            if (!in_array($scheme, ['http', 'https'], true) || $host === '') return '';
            $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
            if (!empty($parts['user']) || !empty($parts['pass'])) return '';
            $path = (string)($parts['path'] ?? '/');
            if ($path === '') $path = '/';
            if ($path !== '/') $path = rtrim($path, '/');
            $query = (string)($parts['query'] ?? '');
            if ($query !== '') {
                parse_str($query, $queryParams);
                foreach (array_keys($queryParams) as $k) {
                    if (preg_match('/^(utm_|fbclid|gclid|mc_cid|mc_eid)/i', (string)$k)) {
                        unset($queryParams[$k]);
                    }
                }
                ksort($queryParams);
                $query = $queryParams ? ('?' . http_build_query($queryParams)) : '';
            }
            return $scheme . '://' . $host . $port . $path . $query;
        };

        $urls = [];
        if ($smartUrl !== '') $urls[] = $smartUrl;
        if ($smartUrlsBulk !== '') {
            foreach ((preg_split('/\r\n|\r|\n/', $smartUrlsBulk) ?: []) as $line) {
                $line = trim((string)$line);
                if ($line !== '') $urls[] = $line;
            }
        }
        $urls = array_map($normalizeSmartUrl, $urls);
        $urls = array_values(array_unique(array_filter($urls, function ($u) {
            return $u !== '';
        })));
        if (!$urls) {
            $_SESSION['flash_message'] = 'Smart add failed: please provide at least one URL.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php#auto-scheduler-section');
            exit;
        }

        if (!in_array($smartType, ['auto', 'rss', 'web'], true)) {
            $smartType = 'auto';
        }
        $targetNicheId = 0;
        if ($smartNicheSlug !== '' && class_exists('App\\NicheManager')) {
            $targetNiche = \App\NicheManager::getNicheBySlug($smartNicheSlug);
            if ($targetNiche) $targetNicheId = (int)($targetNiche['id'] ?? 0);
        }

        $insRss = $pdo->prepare("INSERT OR IGNORE INTO rss_sources (url) VALUES (?)");
        $insWeb = $pdo->prepare("INSERT OR IGNORE INTO web_sources (url) VALUES (?)");
        $existsNiche = $pdo->prepare("SELECT id FROM niche_sources WHERE niche_id = ? AND type = ? AND url = ? LIMIT 1");

        $isPreview = isset($_POST['smart_source_preview']) && (string)($_POST['smart_source_preview'] === '1');
        $addedRss = 0; $addedWeb = 0; $linked = 0; $invalid = 0; $dupeGlobal = 0;
        $detectedRss = 0; $detectedWeb = 0;
        $invalidExamples = [];
        foreach ($urls as $u) {
            if (!filter_var($u, FILTER_VALIDATE_URL)) {
                $invalid++;
                if (count($invalidExamples) < 3) $invalidExamples[] = $u;
                continue;
            }
            $type = $smartType;
            if ($type === 'auto') {
                $path = strtolower((string)parse_url($u, PHP_URL_PATH));
                $query = strtolower((string)parse_url($u, PHP_URL_QUERY));
                $type = (strpos($path, 'feed') !== false || endsWith($path, '.xml') || endsWith($path, '.rss') || endsWith($path, '.atom') || strpos($query, 'feed=') !== false)
                    ? 'rss' : 'web';
            }
            if ($type === 'rss') $detectedRss++; else $detectedWeb++;
            if ($isPreview) continue;
            if ($type === 'rss') {
                $insRss->execute([$u]);
                $addedRss += $insRss->rowCount() > 0 ? 1 : 0;
                $dupeGlobal += $insRss->rowCount() > 0 ? 0 : 1;
            } else {
                $insWeb->execute([$u]);
                $addedWeb += $insWeb->rowCount() > 0 ? 1 : 0;
                $dupeGlobal += $insWeb->rowCount() > 0 ? 0 : 1;
            }
            if ($targetNicheId > 0 && class_exists('App\\NicheManager')) {
                $existsNiche->execute([$targetNicheId, $type, $u]);
                if (!$existsNiche->fetchColumn()) {
                    \App\NicheManager::addSource($targetNicheId, $type, $u);
                    $linked++;
                }
            }
        }

        $_SESSION['flash_message'] = ($isPreview ? "Smart preview only. " : "Smart merge done. ")
            . "Detected RSS={$detectedRss}, Web={$detectedWeb}. "
            . ($isPreview ? '' : "Added RSS={$addedRss}, Web={$addedWeb}. ")
            . ($dupeGlobal > 0 ? "Global duplicates skipped={$dupeGlobal}. " : '')
            . ($targetNicheId > 0 ? "Niche linked={$linked}. " : ($smartNicheSlug !== '' ? 'Niche not found. ' : ''))
            . ($invalid > 0 ? "Invalid URL(s)={$invalid}" . (!empty($invalidExamples) ? ' [' . implode(' | ', $invalidExamples) . ']' : '') . '.' : '');
        $_SESSION['flash_type'] = $isPreview ? 'info' : (($addedRss + $addedWeb + $linked) > 0 ? 'success' : 'warning');
        header('Location: admin.php#auto-scheduler-section');
        exit;
    }

    if (isset($_POST['add_rss'])) {
        $singleUrl = trim($_POST['rss_url'] ?? '');
        $bulkInput = trim($_POST['rss_urls'] ?? '');
        $bulkUrls = $bulkInput === '' ? [] : preg_split('/\r\n|\r|\n/', $bulkInput);

        $rawUrls = [];
        if ($singleUrl !== '') {
            $rawUrls[] = $singleUrl;
        }
        foreach ($bulkUrls as $rawUrl) {
            $rawUrl = trim((string)$rawUrl);
            if ($rawUrl !== '') {
                $rawUrls[] = $rawUrl;
            }
        }

        $rawUrls = array_values(array_unique($rawUrls));
        if (!$rawUrls) {
            $_SESSION['flash_message'] = 'Please enter at least one RSS/XML URL.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $targetNicheSlug = trim((string)($_POST['rss_target_niche_slug'] ?? ''));
        $targetNicheId = 0;
        if ($targetNicheSlug !== '' && class_exists('App\\NicheManager')) {
            $targetNiche = \App\NicheManager::getNicheBySlug($targetNicheSlug);
            if ($targetNiche) {
                $targetNicheId = (int)($targetNiche['id'] ?? 0);
            }
        }

        $inserted = 0;
        $linkedToNiche = 0;
        $invalid = 0;
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO rss_sources (url) VALUES (?)");
        foreach ($rawUrls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $invalid++;
                continue;
            }

            $stmt->execute([$url]);
            if ($stmt->rowCount() > 0) {
                $inserted++;
            }

            if ($targetNicheId > 0 && class_exists('App\\NicheManager')) {
                $existsStmt = $pdo->prepare("SELECT id FROM niche_sources WHERE niche_id = ? AND type = 'rss' AND url = ? LIMIT 1");
                $existsStmt->execute([$targetNicheId, $url]);
                if (!$existsStmt->fetchColumn()) {
                    \App\NicheManager::addSource($targetNicheId, 'rss', $url);
                    $linkedToNiche++;
                }
            }
        }

        $ignored = count($rawUrls) - $inserted - $invalid;
        if ($inserted > 0) {
            $_SESSION['flash_message'] = "Added {$inserted} RSS source(s)."
                . ($ignored > 0 ? " {$ignored} duplicate(s) skipped." : '')
                . ($invalid > 0 ? " {$invalid} invalid link(s) skipped." : '')
                . ($linkedToNiche > 0 ? " Linked {$linkedToNiche} source(s) to niche workflow." : '');
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = $invalid > 0
                ? "No RSS source was added. {$invalid} invalid link(s) detected."
                : 'No RSS source was added (all links already exist).';
            $_SESSION['flash_type'] = 'warning';
        }

        header('Location: admin.php');
        exit;
    }


    if (isset($_POST['update_content_workflow'])) {
        $workflow = trim((string)($_POST['content_workflow'] ?? 'rss'));
        if (!in_array($workflow, ['rss', 'web'], true)) {
            $workflow = 'rss';
        }

        setSetting('content_workflow', $workflow);
        $_SESSION['flash_message'] = 'Content workflow updated.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['add_web'])) {
        $singleUrl = trim($_POST['web_url'] ?? '');
        $bulkInput = trim($_POST['web_urls'] ?? '');
        $bulkUrls = $bulkInput === '' ? [] : preg_split('/\r\n|\r|\n/', $bulkInput);

        $rawUrls = [];
        if ($singleUrl !== '') {
            $rawUrls[] = $singleUrl;
        }
        foreach ($bulkUrls as $rawUrl) {
            $rawUrl = trim((string)$rawUrl);
            if ($rawUrl !== '') {
                $rawUrls[] = $rawUrl;
            }
        }

        $rawUrls = array_values(array_unique($rawUrls));
        if (!$rawUrls) {
            $_SESSION['flash_message'] = 'Please enter at least one normal website URL.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $targetNicheSlug = trim((string)($_POST['web_target_niche_slug'] ?? ''));
        $targetNicheId = 0;
        if ($targetNicheSlug !== '' && class_exists('App\\NicheManager')) {
            $targetNiche = \App\NicheManager::getNicheBySlug($targetNicheSlug);
            if ($targetNiche) {
                $targetNicheId = (int)($targetNiche['id'] ?? 0);
            }
        }

        $inserted = 0;
        $linkedToNiche = 0;
        $invalid = 0;
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO web_sources (url) VALUES (?)");
        foreach ($rawUrls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $invalid++;
                continue;
            }

            $stmt->execute([$url]);
            if ($stmt->rowCount() > 0) {
                $inserted++;
            }

            if ($targetNicheId > 0 && class_exists('App\\NicheManager')) {
                $existsStmt = $pdo->prepare("SELECT id FROM niche_sources WHERE niche_id = ? AND type = 'web' AND url = ? LIMIT 1");
                $existsStmt->execute([$targetNicheId, $url]);
                if (!$existsStmt->fetchColumn()) {
                    \App\NicheManager::addSource($targetNicheId, 'web', $url);
                    $linkedToNiche++;
                }
            }
        }

        $ignored = count($rawUrls) - $inserted - $invalid;
        if ($inserted > 0) {
            $_SESSION['flash_message'] = "Added {$inserted} normal website source(s)."
                . ($ignored > 0 ? " {$ignored} duplicate(s) skipped." : '')
                . ($invalid > 0 ? " {$invalid} invalid link(s) skipped." : '')
                . ($linkedToNiche > 0 ? " Linked {$linkedToNiche} source(s) to niche workflow." : '');
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = $invalid > 0
                ? "No normal website source was added. {$invalid} invalid link(s) detected."
                : 'No normal website source was added (all links already exist).';
            $_SESSION['flash_type'] = 'warning';
        }

        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['bulk_update_articles'])) {
        $bulkAction = trim((string)($_POST['bulk_article_action'] ?? ''));
        $selectedIds = array_map('intval', $_POST['article_ids'] ?? []);
        $selectedIds = array_values(array_filter(array_unique($selectedIds), function ($id) {
            return $id > 0;
        }));

        if (!$selectedIds) {
            $_SESSION['flash_message'] = 'Please select at least one article.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: admin.php');
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

        if ($bulkAction === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM articles WHERE id IN ($placeholders)");
            $stmt->execute($selectedIds);
            $_SESSION['flash_message'] = 'Selected articles deleted.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: admin.php');
            exit;
        }

        if ($bulkAction === 'set_category') {
            $bulkCategory = trim((string)($_POST['bulk_category'] ?? ''));
            $params = array_merge([$bulkCategory], $selectedIds);
            $stmt = $pdo->prepare("UPDATE articles SET category = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            $_SESSION['flash_message'] = 'Category updated for selected articles.';
            $_SESSION['flash_type'] = 'success';
            header('Location: admin.php');
            exit;
        }

        $_SESSION['flash_message'] = 'Invalid bulk action selected.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_rss'])) {
        $rssId = (int)($_POST['rss_id'] ?? 0);
        $rssUrl = trim((string)($_POST['rss_url'] ?? ''));
        if ($rssId <= 0 || !filter_var($rssUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['flash_message'] = 'Invalid RSS source update request.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $duplicateStmt = $pdo->prepare("SELECT id FROM rss_sources WHERE url = ? AND id != ? LIMIT 1");
        $duplicateStmt->execute([$rssUrl, $rssId]);
        if ($duplicateStmt->fetchColumn()) {
            $_SESSION['flash_message'] = 'RSS URL already exists.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: admin.php');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE rss_sources SET url = ? WHERE id = ?");
        $stmt->execute([$rssUrl, $rssId]);
        $_SESSION['flash_message'] = 'RSS source updated.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_web'])) {
        $webId = (int)($_POST['web_id'] ?? 0);
        $webUrl = trim((string)($_POST['web_url'] ?? ''));
        if ($webId <= 0 || !filter_var($webUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['flash_message'] = 'Invalid website source update request.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $duplicateStmt = $pdo->prepare("SELECT id FROM web_sources WHERE url = ? AND id != ? LIMIT 1");
        $duplicateStmt->execute([$webUrl, $webId]);
        if ($duplicateStmt->fetchColumn()) {
            $_SESSION['flash_message'] = 'Website URL already exists.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: admin.php');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE web_sources SET url = ? WHERE id = ?");
        $stmt->execute([$webUrl, $webId]);
        $_SESSION['flash_message'] = 'Website source updated.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['delete_web'])) {
        $stmt = $pdo->prepare("DELETE FROM web_sources WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_web']]);
        $_SESSION['flash_message'] = 'Normal website source removed.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['delete_article'])) {
        $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_article']]);
        $_SESSION['flash_message'] = 'Article deleted.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['delete_rss'])) {
        $stmt = $pdo->prepare("DELETE FROM rss_sources WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_rss']]);
        $_SESSION['flash_message'] = 'RSS source removed.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['clear_page_visits'])) {
        $stmt = $pdo->prepare("DELETE FROM page_visits");
        $stmt->execute();
        $_SESSION['flash_message'] = 'All page visit statistics have been cleared.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['save_article'])) {
        $articleId = (int)($_POST['article_id'] ?? 0);
        $title = trim((string)($_POST['article_title'] ?? ''));
        $slugInput = trim((string)($_POST['article_slug'] ?? ''));
        $slug = $slugInput !== '' ? slugify($slugInput) : slugify($title);
        $category = trim((string)($_POST['article_category'] ?? ''));
        $excerpt = trim((string)($_POST['article_excerpt'] ?? ''));
        $image = trim((string)($_POST['article_image'] ?? ''));
        $image2 = trim((string)($_POST['article_image2'] ?? ''));
        $content = trim((string)($_POST['article_content'] ?? ''));
        $translatedTitle = trim((string)($_POST['article_translated_title'] ?? ''));
        $translatedContent = trim((string)($_POST['article_translated_content'] ?? ''));

        if ($articleId <= 0 || $title === '' || $content === '' || $slug === '') {
            $_SESSION['flash_message'] = 'Article update failed. Title, slug, and content are required.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $duplicateStmt = $pdo->prepare("SELECT id FROM articles WHERE (title = :title OR slug = :slug) AND id != :id LIMIT 1");
        $duplicateStmt->execute([
            'title' => $title,
            'slug' => $slug,
            'id' => $articleId,
        ]);
        $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
        if ($duplicate) {
            $_SESSION['flash_message'] = 'Article update failed. Title or slug already exists.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $autoTranslate = getSettingInt('auto_translate_enabled', 0, 0, 1) === 1;
        $targetLang = trim((string)getSetting('auto_translate_target_language', ''));
        $origLanguage = '';
        if ($autoTranslate && $targetLang) {
            if ($translatedTitle === '') {
                $translatedTitle = translateText($title, $targetLang);
            }
            if ($translatedContent === '') {
                $translatedContent = translateText($content, $targetLang);
            }
            $origLanguage = $targetLang;
        }

        $stmt = $pdo->prepare("UPDATE articles SET title = ?, slug = ?, category = ?, excerpt = ?, image = ?, image2 = ?, content = ?, translated_title = ?, translated_content = ?, orig_language = ? WHERE id = ?");
        $stmt->execute([$title, $slug, $category, $excerpt, $image, $image2, $content, $translatedTitle ?: null, $translatedContent ?: null, $origLanguage, $articleId]);

        // regenerate auto-tags if none exist
        $existingTags = getArticleTags($articleId);
        if (empty($existingTags)) {
            $autoTags = generateAutoTags($title, $content);
            foreach ($autoTags as $tag) {
                addTagToArticle($articleId, $tag);
            }
        }

        // refresh export artifacts after manual update
        writeArticleExportFiles($articleId, $slug, [
            'id' => $articleId,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'image' => $image ?: null,
            'image2' => $image2 ?: null,
            'translated_title' => $translatedTitle ?: null,
            'translated_content' => $translatedContent ?: null,
            'published_at' => date('c'),
        ]);

        $_SESSION['flash_message'] = 'Article updated successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['save_setting'])) {
        $settingKey = trim((string)($_POST['setting_key'] ?? ''));
        $settingValue = trim((string)($_POST['setting_value'] ?? ''));
        $originalKey = trim((string)($_POST['original_setting_key'] ?? ''));

        if (!preg_match('/^[a-z0-9_\-.]{2,80}$/i', $settingKey)) {
            $_SESSION['flash_message'] = 'Invalid setting key. Use letters, numbers, dots, dashes, and underscores only.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }

        $protectedSettings = ['pipeline_defaults_v2_applied'];
        if ($originalKey !== '' && in_array($originalKey, $protectedSettings, true) && $originalKey !== $settingKey) {
            $_SESSION['flash_message'] = 'This system setting key cannot be renamed.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: admin.php');
            exit;
        }

        if ($originalKey !== '' && $originalKey !== $settingKey) {
            $existsStmt = $pdo->prepare("SELECT key FROM settings WHERE key = ? LIMIT 1");
            $existsStmt->execute([$settingKey]);
            if ($existsStmt->fetchColumn()) {
                $_SESSION['flash_message'] = 'Cannot rename setting. Target key already exists.';
                $_SESSION['flash_type'] = 'danger';
                header('Location: admin.php');
                exit;
            }

            $renameStmt = $pdo->prepare("UPDATE settings SET key = ?, value = ? WHERE key = ?");
            $renameStmt->execute([$settingKey, $settingValue, $originalKey]);
        } else {
            setSetting($settingKey, $settingValue);
        }

        $_SESSION['flash_message'] = "Setting '{$settingKey}' saved.";
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['delete_setting'])) {
        $settingKey = trim((string)($_POST['delete_setting'] ?? ''));
        $protectedSettings = ['pipeline_defaults_v2_applied'];
        if (in_array($settingKey, $protectedSettings, true)) {
            $_SESSION['flash_message'] = 'This system setting is protected and cannot be deleted.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: admin.php');
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM settings WHERE key = ?");
        $stmt->execute([$settingKey]);
        $_SESSION['flash_message'] = 'Setting removed.';
        $_SESSION['flash_type'] = 'warning';
        header('Location: admin.php');
        exit;
    }
}

$message = $_SESSION['flash_message'];
$messageType = $_SESSION['flash_type'];
$_SESSION['flash_message'] = null;
$_SESSION['flash_type'] = 'info';

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

// allow filtering of page visit stats
$pageVisitSearch = trim($_GET['pv_search'] ?? '');
$pageVisitStats = getPageVisitStats(7, $pageVisitSearch);
$totalTrackedViews = 0;
$totalTrackedVisitors = 0;
foreach ($pageVisitStats as $visitRow) {
    $totalTrackedViews += (int)($visitRow['total_views'] ?? 0);
    $totalTrackedVisitors += (int)($visitRow['unique_visitors'] ?? 0);
}
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

// translation feature toggles
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
$categoryOptionsStmt = $pdo->prepare("SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$categoryOptionsStmt->execute();
$categoryOptions = $categoryOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

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

$webSql = "SELECT id, url FROM web_sources";
$webParams = [];
if ($webSearch !== '') {
    $webSql .= " WHERE url LIKE :url";
    $webParams['url'] = '%' . $webSearch . '%';
}
$webSql .= " ORDER BY id DESC";
// Niche management POST handlers

    if (isset($_POST['publish_multi_niches'])) {
        $selectedNiches = $_POST['multi_niches'] ?? [];
        $count = 0;
        foreach ($selectedNiches as $nicheSlug) {
            $nicheSlug = trim((string)$nicheSlug);
            if ($nicheSlug === '') {
                continue;
            }
            setSetting('active_niche', $nicheSlug);
            $result = publishAutoArticleBySchedule(true);
            if (($result['published'] ?? 0) === 1) {
                $count++;
            }
        }
        $_SESSION['flash_message'] = "تم النشر في {$count} نيش.";
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['create_niche'])) {
        $rawSlug = trim((string)($_POST['niche_slug'] ?? ''));
        $name = trim((string)($_POST['niche_name'] ?? ''));
        $description = trim((string)($_POST['niche_description'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_message'] = 'Niche name is required.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php');
            exit;
        }
        $slug = $rawSlug !== '' ? slugify($rawSlug) : slugify($name);
        $id = \App\NicheManager::createNiche($slug, $name, $description);
        if ($id > 0) {
            $_SESSION['flash_message'] = 'Niche created successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to create niche.';
            $_SESSION['flash_type'] = 'danger';
        }
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['delete_niche'])) {
        $nicheId = (int)($_POST['niche_id'] ?? 0);
        if ($nicheId > 0 && \App\NicheManager::deleteNiche($nicheId)) {
            $_SESSION['flash_message'] = 'Niche deleted.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Invalid niche selected or delete failed.';
            $_SESSION['flash_type'] = 'danger';
        }
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['update_niche'])) {
        $nicheId = (int)($_POST['niche_id'] ?? 0);
        $name = trim((string)($_POST['niche_name'] ?? ''));
        $description = trim((string)($_POST['niche_description'] ?? ''));
        if ($nicheId > 0 && $name !== '' && \App\NicheManager::updateNiche($nicheId, $name, $description)) {
            $_SESSION['flash_message'] = 'Niche updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Invalid niche data or update failed.';
            $_SESSION['flash_type'] = 'danger';
        }
        header('Location: admin.php#niche-management');
        exit;
    }

    if (isset($_POST['add_niche_source'])) {
        $nicheId = (int)($_POST['niche_id'] ?? 0);
        $type = trim((string)($_POST['source_type'] ?? 'rss')) === 'web' ? 'web' : 'rss';
        $url = trim((string)($_POST['source_url'] ?? ''));
        if ($nicheId > 0 && $url !== '' && \App\NicheManager::addSource($nicheId, $type, $url)) {
            $_SESSION['flash_message'] = 'Source added.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Invalid niche or URL.';
            $_SESSION['flash_type'] = 'danger';
        }
        header('Location: admin.php');
        exit;
    }


    if (isset($_POST['replace_niche_sources'])) {
        $nicheId = (int)($_POST['niche_id'] ?? 0);
        $type = trim((string)($_POST['source_type'] ?? 'rss')) === 'web' ? 'web' : 'rss';
        $bulkInput = trim((string)($_POST['source_urls_bulk'] ?? ''));

        if ($nicheId <= 0) {
            $_SESSION['flash_message'] = 'Invalid niche selected.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php#niche-management');
            exit;
        }

        $rows = $bulkInput === '' ? [] : (preg_split('/\r\n|\r|\n/', $bulkInput) ?: []);
        $uniqueUrls = [];
        foreach ($rows as $row) {
            $url = trim((string)$row);
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $uniqueUrls[$url] = true;
        }

        if (\App\NicheManager::replaceSources($nicheId, $type, array_keys($uniqueUrls))) {
            $_SESSION['flash_message'] = strtoupper($type) . " sources replaced successfully (" . count($uniqueUrls) . " source(s)).";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Could not replace niche sources. Please try again.';
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: admin.php#niche-management');
        exit;
    }

    if (isset($_POST['remove_niche_source'])) {
        $nicheId = (int)($_POST['niche_id'] ?? 0);
        $type = trim((string)($_POST['source_type'] ?? '')) === 'web' ? 'web' : 'rss';
        $url = trim((string)($_POST['source_url'] ?? ''));
        if ($nicheId > 0 && $url !== '' && \App\NicheManager::removeSource($nicheId, $type, $url)) {
            $_SESSION['flash_message'] = 'Source removed.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Invalid source selected or remove failed.';
            $_SESSION['flash_type'] = 'danger';
        }
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['manage_niche_auto_title'])) {
        $nicheSlug = trim((string)($_POST['niche_auto_title'] ?? ''));
        if ($nicheSlug !== '') {
            setSetting('active_niche', $nicheSlug);
            $_SESSION['flash_message'] = "Auto-title editor switched to niche: {$nicheSlug}";
            $_SESSION['flash_type'] = 'info';
        } else {
            $_SESSION['flash_message'] = 'Please select a niche first.';
            $_SESSION['flash_type'] = 'warning';
        }
        header('Location: admin.php#auto-scheduler-section');
        exit;
    }

    if (isset($_POST['save_niche_title_pack'])) {
        $nicheSlug = trim((string)($_POST['niche_title_slug'] ?? ''));
        if ($nicheSlug === '') {
            $_SESSION['flash_message'] = 'Invalid niche for title pack update.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: admin.php#niche-management');
            exit;
        }

        $mode = trim((string)($_POST['niche_auto_title_mode'] ?? 'template'));
        if (!in_array($mode, ['template', 'list'], true)) {
            $mode = 'template';
        }

        $fields = [
            'auto_title_fixed_titles' => (string)($_POST['niche_fixed_titles'] ?? ''),
            'auto_title_brands' => (string)($_POST['niche_brands'] ?? ''),
            'auto_title_models' => (string)($_POST['niche_models'] ?? ''),
            'auto_title_modifiers' => (string)($_POST['niche_modifiers'] ?? ''),
            'auto_title_audiences' => (string)($_POST['niche_audiences'] ?? ''),
            'auto_title_angles' => (string)($_POST['niche_angles'] ?? ''),
            'auto_title_templates' => (string)($_POST['niche_templates'] ?? ''),
        ];

        $prefix = 'niche.' . $nicheSlug . '.';
        setSetting($prefix . 'auto_title_mode', $mode);

        foreach ($fields as $fieldKey => $raw) {
            $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
            $clean = [];
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    $clean[] = $line;
                }
            }
            $clean = array_values(array_unique($clean));
            setSetting($prefix . $fieldKey, implode("\n", $clean));
        }

        $_SESSION['flash_message'] = 'Niche title pack updated successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: admin.php#niche-management');
        exit;
    }

    if (isset($_POST['set_active_niche'])) {
        $slug = trim((string)($_POST['active_niche'] ?? ''));
        if ($slug !== '' && class_exists('App\\NicheManager')) {
            $niche = \App\NicheManager::getNicheBySlug($slug);
            if ($niche) {
                setSetting('active_niche', $slug);
                $_SESSION['flash_message'] = 'Active niche updated.';
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Invalid niche selected.';
                $_SESSION['flash_type'] = 'danger';
            }
        } else {
            $_SESSION['flash_message'] = 'Invalid niche selected.';
            $_SESSION['flash_type'] = 'danger';
        }
        header('Location: admin.php');
        exit;
    }

    if (isset($_POST['refresh_config_file'])) {
        $beforeFingerprint = getSetting('config_txt_fingerprint', '');
        $configFileResult = loadConfigFileIfChanged($pdo, __DIR__ . '/config.txt');
        $afterFingerprint = getSetting('config_txt_fingerprint', '');
        if ($afterFingerprint !== '' && $afterFingerprint !== $beforeFingerprint) {
            $_SESSION['flash_message'] = 'config.txt reloaded and settings applied.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'No changes detected in config.txt or the file was not found.';
            $_SESSION['flash_type'] = 'info';
        }
        header('Location: admin.php#config-management');
        exit;
    }

    $webStmt = $pdo->prepare($webSql);
foreach ($webParams as $key => $value) {
    $webStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$webStmt->execute();
$webRows = $webStmt->fetchAll(PDO::FETCH_ASSOC);

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

$configFilePath = __DIR__ . '/config.txt';
$configPreview = is_file($configFilePath) ? file_get_contents($configFilePath) : '';
$configContents = loadConfigTxt($configFilePath);
$configGlobalRssCount = count($configContents['sources']['rss'] ?? []);
$configGlobalWebCount = count($configContents['sources']['web'] ?? []);
$configGlobalSettingsCount = count($configContents['settings'] ?? []);
$configNichesCount = count($configContents['niches'] ?? []);
$configFingerprint = $configContents['fingerprint'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel - <?= e($siteTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* تحسين زر النسخ */
        .copy-btn {
            cursor: pointer;
            border: none;
            background: none;
            color: #38bdf8;
            font-size: 1.1em;
            margin-left: 0.3em;
        }
        .copy-success {
            color: #22c55e;
            font-size: 0.9em;
            margin-right: 0.5em;
        }
        /* تنبيه تفاعلي أعلى الصفحة */
        #top-alert {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 9999;
            display: none;
        }
    </style>
    <style>
        body {
            --bs-heading-color: #f8fafc;
            background:
                radial-gradient(circle at 12% 8%, rgba(59, 130, 246, 0.22), transparent 46%),
                radial-gradient(circle at 85% 14%, rgba(168, 85, 247, 0.18), transparent 42%),
                linear-gradient(155deg, #020617 0%, #0b1120 35%, #111827 100%);
            background-attachment: fixed;
            color: #f8fafc;
        }
        .text-secondary,
        .text-light-emphasis,
        .text-muted,
        small {
            color: #cbd5e1 !important;
        }
        .section-card {
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.96), rgba(17, 24, 39, 0.92));
            border: 1px solid rgba(148, 163, 184, 0.2);
            overflow-wrap: anywhere;
            border-radius: 0.95rem;
        }
        .container {
            max-width: 1360px;
        }
        .form-control,
        .form-select,
        .btn {
            min-height: 42px;
        }
        .table-responsive {
            border-radius: 0.65rem;
        }
        .stat-card h3,
        .stat-card h6 {
            margin-bottom: 0;
        }
        .table td,
        .table th {
            vertical-align: middle;
        }
        .list-group-item {
            background: #4a273b;
            color: #f8f9fa;
            border-color: rgba(255, 255, 255, 0.08);
        }
        .workflow-badge {
            font-size: 0.75rem;
            letter-spacing: 0.2px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .mini-analytics {
            border: 1px solid rgba(14, 165, 233, 0.35);
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(37, 99, 235, 0.2));
        }
        .mini-analytics .table {
            --bs-table-bg: transparent;
            --bs-table-border-color: rgba(255, 255, 255, 0.08);
            margin-bottom: 0;
        }
        .mini-analytics .progress {
            height: 6px;
            background-color: rgba(255, 255, 255, 0.12);
        }
        .dashboard-sidebar {
            position: sticky;
            top: 1rem;
            max-height: calc(100vh - 2rem);
            overflow: hidden;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
            border-radius: 1.1rem;
        }
        .dashboard-sidebar .nav-link {
            color: #bfdbfe;
            border: 1px solid rgba(59, 130, 246, 0.45);
            background: rgba(59, 130, 246, 0.09);
            margin-bottom: 0.5rem;
            border-radius: 0.6rem;
            transition: all 0.2s ease;
        }
        .dashboard-sidebar .nav-link:hover,
        .dashboard-sidebar .nav-link.active {
            color: #eff6ff;
            background: rgba(37, 99, 235, 0.3);
            border-color: rgba(147, 197, 253, 0.85);
        }
        .panel-nav-btn {
            text-align: left;
            min-height: 42px;
            border: 1px solid rgba(59, 130, 246, 0.45);
            color: #bfdbfe;
            background: rgba(37, 99, 235, 0.1);
            border-radius: 0.8rem;
            padding: 0.8rem 1rem;
            transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
        }
        .panel-nav-btn:hover,
        .panel-nav-btn.active {
            color: #eff6ff;
            border-color: rgba(147, 197, 253, 0.85);
            background: rgba(37, 99, 235, 0.32);
        }
        .panel-nav-btn:focus-visible {
            outline: 2px solid rgba(252, 165, 165, 0.85);
            outline-offset: 2px;
        }
        #control-panel-search {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(248, 113, 113, 0.3);
            color: #fff;
        }
        #control-panel-search::placeholder {
            color: #ffb4b5;
        }

        .panel-nav-toolbar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.65rem;
        }
        .panel-nav-toolbar .btn {
            flex: 1;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            font-weight: 600;
        }
        #control-panel-nav {
            max-height: calc(100vh - 16rem);
            overflow-y: auto;
            padding-right: 0.2rem;
            padding-bottom: 0.3rem;
            border-top: 1px solid rgba(148, 163, 184, 0.16);
            margin-top: 0.75rem;
        }
        .section-counter {
            display: inline-block;
            min-width: 92px;
            text-align: center;
            font-size: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.45);
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            margin-bottom: 0.5rem;
            color: #e2e8f0;
        }
        .panel-nav-empty {
            border: 1px dashed rgba(248, 113, 113, 0.45);
            border-radius: 0.6rem;
            padding: 0.6rem;
            color: #fecaca;
            font-size: 0.82rem;
            text-align: center;
        }
        #active-control-panel {
            display: none;
        }
        #control-cards-source {
            display: block !important;
            width: 100%;
        }
        .panel-section {
            display: block !important;
            opacity: 1 !important;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .panel-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 26px 75px rgba(15, 23, 42, 0.16);
        }
        .panel-card-highlight {
            border-color: rgba(59, 130, 246, 0.8) !important;
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.25);
            background: rgba(37, 99, 235, 0.12) !important;
        }
        .btn-primary,
        .btn-outline-info,
        .bg-info,
        .text-bg-primary {
            background-color: #2563eb !important;
            border-color: #60a5fa !important;
            color: #fff !important;
        }
        .progress-bar.bg-info {
            background-color: #38bdf8 !important;
        }
        .admin-hero {
            border: 1px solid rgba(96, 165, 250, 0.35);
            background: linear-gradient(130deg, rgba(30, 64, 175, 0.35), rgba(15, 23, 42, 0.95));
            border-radius: 1rem;
        }
        .ads-preview .inline-ad-unit {
            margin: 0;
            border: 1px solid rgba(251, 146, 60, 0.65);
            background: rgba(255, 247, 237, 0.1);
            border-radius: 0.75rem;
            padding: 0.85rem;
        }
        .ads-preview .inline-ad-label {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.72rem;
            font-weight: 700;
            color: #fdba74;
            margin-bottom: 0.45rem;
        }
        .ads-preview .ad-unit-inner {
            border: 1px dashed rgba(251, 146, 60, 0.8);
            border-radius: 0.65rem;
            padding: 0.75rem;
            color: #fed7aa;
            text-align: center;
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #pipeline-config-section h5,
        #pipeline-config-section h6,
        #pipeline-config-section .form-label,
        #auto-scheduler-section h5,
        #auto-scheduler-section h6,
        #auto-scheduler-section .form-label {
            color: #ff4d4f !important;
        }
        #auto-scheduler-section {
            border: 1px solid rgba(250, 204, 21, 0.35);
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
        }
        #auto-scheduler-section .smart-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        #auto-scheduler-section .smart-pill {
            border: 1px solid rgba(250, 204, 21, 0.55);
            border-radius: 999px;
            padding: 0.2rem 0.65rem;
            font-size: 0.78rem;
            color: #fde68a;
            background: rgba(250, 204, 21, 0.1);
        }
        #auto-scheduler-section .niche-details-toggle {
            min-width: 118px;
        }
        @media (max-width: 991.98px) {
            body {
                background-attachment: scroll;
            }
            .container {
                padding-left: 0.8rem;
                padding-right: 0.8rem;
            }
            .panel-nav-toolbar .btn {
                min-height: 46px;
            }
            .dashboard-sidebar {
                position: static;
                max-height: none;
                overflow: visible;
            }
            #control-panel-nav {
                max-height: 35vh;
            }
            .panel-nav-btn {
                font-size: 0.95rem;
                padding: 0.6rem 0.75rem;
                white-space: normal;
            }
            .panel-nav-toolbar {
                flex-direction: row;
                flex-wrap: wrap;
            }
            .d-flex.gap-2 {
                flex-wrap: wrap;
            }
            h1 {
                font-size: 1.45rem;
                line-height: 1.35;
            }
            .table {
                font-size: 0.9rem;
            }
            .card-body {
                padding: 0.9rem;
            }
        }
        @media (min-width: 1200px) {
            #control-panel-nav {
                max-height: calc(100vh - 14rem);
            }
            .section-card {
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            }
        }
    </style>
</head>
<body class="text-light">
<!-- تنبيه تفاعلي أعلى الصفحة -->
<div id="top-alert" class="alert alert-info text-center" role="alert" style="display:none;"></div>
<div class="container py-4 py-lg-5">
    <div class="admin-hero p-4 p-lg-4 mb-4 shadow-lg">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3">
            <div>
                <span class="badge text-bg-primary mb-2 px-3 py-2"><i class="bi bi-stars"></i> Admin v2</span>
                <h1 class="mb-1"><i class="bi bi-speedometer2"></i> <?= e($siteTitle) ?> Control Center</h1>
                <p class="text-secondary mb-0">A fully refreshed admin experience for content, workflows, automation, and publishing controls.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Open Public Site</a>
                <form method="post" class="mb-0">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button class="btn btn-danger" name="logout" value="1"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <!-- تحسين عرض الرسائل: تنبيه قابل للإغلاق وتختفي تلقائياً -->
        <div class="alert alert-<?= e($messageType) ?> shadow-sm alert-dismissible fade show" role="alert" id="main-alert">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <script>
            setTimeout(function(){
                var alert = document.getElementById('main-alert');
                if(alert) alert.classList.remove('show');
            }, 5000);
        </script>
    <?php endif; ?>

    <?php
    $workflowHealth = $workflowSummary['health'] ?? 'ready';
    $workflowAlertClass = $workflowHealth === 'ready' ? 'success' : 'warning';
    $workflowAlertText = $workflowHealth === 'ready'
        ? 'Workflow is ready. Scheduled and manual runs will follow the selected source type.'
        : ($workflowHealth === 'missing_sources'
            ? 'No sources found for the selected workflow. Add sources below before running.'
            : 'Auto scheduler is disabled. Manual workflow runs still work normally.');
    ?>
    <div class="alert alert-<?= $workflowAlertClass ?> d-flex flex-wrap align-items-center gap-2 shadow-sm" role="status">
        <span class="badge text-bg-dark workflow-badge">Workflow: <?= e($workflowSummary['selected_workflow_label']) ?></span>
        <span class="badge text-bg-secondary workflow-badge">Sources: <?= (int)$workflowSummary['selected_sources'] ?></span>
        <span class="badge text-bg-secondary workflow-badge">Daily limit: <?= (int)$workflowSummary['daily_limit'] ?></span>
        <span class="ms-1"><?= e($workflowAlertText) ?></span>
    </div>

    <div class="row g-3 mb-4" id="overview-stats">
        <!-- شريط إحصائيات سريع أعلى لوحة التحكم -->
        <div class="col-12 mb-2">
            <div class="d-flex flex-wrap gap-3 justify-content-center align-items-center">
                <span class="badge bg-primary">المقالات: <?= $totalArticles ?></span>
                <span class="badge bg-success">مصادر RSS/Web: <?= $totalSources ?> / <?= $totalWebSources ?></span>
                <span class="badge bg-warning text-dark">الحد اليومي: <?= $dailyLimit ?></span>
                <span class="badge bg-info text-dark">آخر نشر: <?= e($latestDate ?: 'N/A') ?></span>
                <span class="badge bg-secondary">الزوار: <?= (int)$totalTrackedVisitors ?> • المشاهدات: <?= (int)$totalTrackedViews ?></span>
            </div>
        </div>
    </div>

    <div class="card section-card mini-analytics mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h6 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Visitor Analytics by Page</h6>
                    <small class="text-light-emphasis">Compact view · top <?= count($pageVisitStats) ?> pages</small>
            </div>

                <form method="get" class="mb-2 d-flex gap-2">
                    <input type="text" name="pv_search" class="form-control form-control-sm" placeholder="Filter pages" value="<?= e($pageVisitSearch) ?>">
                    <button class="btn btn-sm btn-outline-light">Go</button>
                </form>
                <form method="post" class="mb-2">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <button name="clear_page_visits" value="1" class="btn btn-sm btn-outline-danger" onclick="return confirm('This will remove all stored page visit records. Proceed?')">Clear all visits</button>
                </form>

            <?php if (!$pageVisitStats): ?>
                <small class="text-secondary">No visit data yet. Open the public pages and stats will appear automatically.</small>
            <?php else: ?>
                <?php $maxViews = max(1, (int)$pageVisitStats[0]['total_views']); ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle text-light">
                        <thead>
                        <tr>
                            <th>Page</th>
                            <th class="text-center">Unique</th>
                            <th class="text-center">Views</th>
                            <th class="text-center">Last 24h</th>
                            <th style="width: 180px;">Trend</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pageVisitStats as $visitRow): ?>
                            <?php $ratio = min(100, (int)round(((int)$visitRow['total_views'] / $maxViews) * 100)); ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= e($visitRow['page_label']) ?></span>
                                    <small class="text-secondary d-block"><?= e($visitRow['page_key']) ?></small>
                                </td>
                                <td class="text-center"><span class="badge text-bg-secondary"><?= (int)$visitRow['unique_visitors'] ?></span></td>
                                <td class="text-center"><span class="badge text-bg-primary"><?= (int)$visitRow['total_views'] ?></span></td>
                                <td class="text-center"><span class="badge text-bg-dark"><?= (int)$visitRow['visitors_24h'] ?></span></td>
                                <td>
                                    <div class="progress" role="progressbar" aria-label="Page views trend" aria-valuenow="<?= $ratio ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-info" style="width: <?= $ratio ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <aside class="col-lg-3">
            <div class="card section-card dashboard-sidebar">
                <div class="card-body">
                    <h5 class="mb-3"><i class="bi bi-layout-sidebar"></i> Navigation Hub</h5>
                    <hr class="border-secondary-subtle my-3">
                    <h6 class="mb-2"><i class="bi bi-ui-checks-grid"></i> Quick Section Switcher</h6>
                    <input type="search" id="control-panel-search" class="form-control form-control-sm mb-2" placeholder="Search sections..." aria-label="Search dashboard sections">
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                        <span class="section-counter" id="section-counter">0/0 sections</span>
                        <button type="button" class="btn btn-sm btn-outline-light" id="panel-reset-btn" aria-label="Reset search"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    </div>
                    <small class="d-block text-secondary mb-3" id="active-section-label">Active: —</small>
                    <div class="panel-nav-toolbar">
                        <button type="button" class="btn btn-sm btn-outline-light" id="panel-prev-btn" aria-label="Previous section"><i class="bi bi-arrow-left"></i><span>Previous</span></button>
                        <button type="button" class="btn btn-sm btn-outline-light" id="panel-next-btn" aria-label="Next section"><span>Next</span><i class="bi bi-arrow-right"></i></button>
                    </div>
                    <div class="d-grid gap-2" id="control-panel-nav"></div>
                    <div class="panel-nav-empty d-none" id="control-panel-empty">No matching sections found.</div>
                </div>
            </div>

        </aside>

        <div class="col-lg-9">
            <div id="active-control-panel" class="mb-3"></div>
            <div class="row g-4" id="control-cards-source">
                <!-- Section: Daily Publishing Limit -->
                <div class="card section-card mb-3 panel-section" id="publishing-settings" style="display:none;">
                    <div class="card-body">
                        <h5><i class="bi bi-sliders"></i> Daily Publishing Limit</h5>
                        <form method="post" class="row g-2 align-items-end">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <div class="col-8">
                                <label class="form-label">Daily Workflow Limit</label>
                                <input type="number" name="daily_limit" class="form-control" min="1" max="200" value="<?= $dailyLimit ?>">
                            </div>
                            <div class="col-4">
                                <button name="update_daily_limit" value="1" class="btn btn-outline-light w-100">Save</button>
                            </div>
                        </form>
                        <small class="text-secondary">Controls max articles generated per selected workflow run.</small>
                    </div>
                </div>

                <!-- Section: SEO Settings -->
                <div class="card section-card mb-3 panel-section" id="seo-settings" style="display:none;">
                    <div class="card-body">
                        <h5><i class="bi bi-search"></i> SEO Settings</h5>
                        <form method="post" class="row g-2 align-items-end">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <div class="col-12">
                                <label class="form-label">Site Brand Title</label>
                            <input type="text" name="site_title" class="form-control" maxlength="80" value="<?= e($siteTitle) ?>" placeholder="AutoCar Niche">
                            <small class="text-secondary">Used for navbar, admin header, API site name, and sitemap publication name.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Homepage Title</label>
                            <input type="text" name="seo_home_title" class="form-control" maxlength="120" value="<?= e($seoHomeTitle) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Homepage Meta Description</label>
                            <textarea name="seo_home_description" class="form-control" rows="3" maxlength="160"><?= e($seoHomeDescription) ?></textarea>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Article Title Suffix</label>
                            <input type="text" name="seo_article_title_suffix" class="form-control" maxlength="80" value="<?= e($seoArticleTitleSuffix) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Default Robots</label>
                            <select name="seo_default_robots" class="form-select">
                                <option value="index,follow" <?= $seoDefaultRobots === 'index,follow' ? 'selected' : '' ?>>index,follow</option>
                                <option value="noindex,follow" <?= $seoDefaultRobots === 'noindex,follow' ? 'selected' : '' ?>>noindex,follow</option>
                                <option value="index,nofollow" <?= $seoDefaultRobots === 'index,nofollow' ? 'selected' : '' ?>>index,nofollow</option>
                                <option value="noindex,nofollow" <?= $seoDefaultRobots === 'noindex,nofollow' ? 'selected' : '' ?>>noindex,nofollow</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Default Social Image URL (OG/Twitter)</label>
                            <input type="url" name="seo_default_og_image" class="form-control" maxlength="500" value="<?= e($seoDefaultOgImage) ?>" placeholder="https://example.com/cover.jpg">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Twitter Site Username (optional)</label>
                            <input type="text" name="seo_twitter_site" class="form-control" maxlength="40" value="<?= e($seoTwitterSite) ?>" placeholder="@yourbrand">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Auto Image ALT Suffix</label>
                            <input type="text" name="seo_image_alt_suffix" class="form-control" maxlength="80" value="<?= e($seoImageAltSuffix) ?>" placeholder="- car image">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Auto Image Title Suffix</label>
                            <input type="text" name="seo_image_title_suffix" class="form-control" maxlength="80" value="<?= e($seoImageTitleSuffix) ?>" placeholder="- photo">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Auto Keyword Linking Rules</label>
                            <textarea name="seo_auto_link_rules" class="form-control" rows="6" maxlength="12000" placeholder="car insurance|https://example.com/insurance-guide|newtab|nofollow&#10;Tesla|internal&#10;Model Y|slug:model-y-review&#10;BMW"><?= e($seoAutoLinkRules) ?></textarea>
                            <small class="text-secondary">One rule per line: <code>keyword|destination|newtab|nofollow</code>. If destination is omitted (e.g. <code>BMW</code>), it resolves automatically to an internal related article. You can also use <code>internal</code> or <code>slug:article-slug</code>.</small>
                        </div>
                        <div class="col-8">
                            <label class="form-label">Automatic Internal Links per Article</label>
                            <input type="number" name="seo_auto_link_max_per_article" class="form-control" min="1" max="10" value="<?= (int)$seoAutoLinkMaxPerArticle ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label d-block">&nbsp;</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="seo_auto_link_auto_internal" name="seo_auto_link_auto_internal" value="1" <?= $seoAutoLinkAutoInternal ? 'checked' : '' ?>>
                                <label class="form-check-label" for="seo_auto_link_auto_internal">Auto internal</label>
                            </div>
                        </div>

                        <!-- translation settings -->
                        <div class="col-12"><hr class="border-secondary-subtle"></div>
                        <div class="col-12">
                            <h6 class="mb-1">Auto Translation</h6>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_translate_enabled" id="auto_translate_enabled" <?= $autoTranslateEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_translate_enabled">Enable automatic translation for new articles</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Target language code</label>
                            <input type="text" name="auto_translate_target_language" class="form-control" maxlength="5" value="<?= e($autoTranslateTarget) ?>" placeholder="e.g. ar">
                        </div>

                        <div class="col-12">
                            <button name="update_seo_settings" value="1" class="btn btn-outline-light w-100">Save SEO Settings</button>
                        </div>
                        <div class="col-12">
                            <button name="ping_sitemap" value="1" class="btn btn-outline-secondary w-100 mt-2">Ping Search Engines Now</button>
                        </div>
                    </form>
                    <small class="text-secondary">Manage global metadata for homepage, article title suffix, robots rules, and social sharing tags.</small>
                </div>

            <!-- Section: Ads Manager -->
            <div class="card section-card mb-3 panel-section" id="ads-settings" style="display:none;">
                    <div class="card-body">
                        <h5><i class="bi bi-badge-ad"></i> Smart Ads Manager</h5>
                        <form method="post" class="row g-2 align-items-end">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ads_enabled" name="ads_enabled" value="1" <?= $adsEnabled ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ads_enabled">Enable ad injection in article pages</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Injection Strategy</label>
                                <select name="ads_injection_mode" class="form-select">
                                    <option value="smart" <?= $adsInjectionMode === 'smart' ? 'selected' : '' ?>>AI Smart Placement (recommended)</option>
                                    <option value="interval" <?= $adsInjectionMode === 'interval' ? 'selected' : '' ?>>Fixed Paragraph Interval</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Max Ads / Article</label>
                                <input type="number" name="ads_max_units_per_article" class="form-control" min="1" max="6" value="<?= (int)$adsMaxUnits ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Paragraph Interval</label>
                                <input type="number" name="ads_paragraph_interval" class="form-control" min="2" max="10" value="<?= (int)$adsParagraphInterval ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Min Words before First Ad</label>
                                <input type="number" name="ads_min_words_before_first_injection" class="form-control" min="80" max="600" value="<?= (int)$adsMinWordsBeforeFirstInjection ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Min Article Words (Enable Ads)</label>
                                <input type="number" name="ads_min_article_words" class="form-control" min="120" max="3000" value="<?= (int)$adsMinArticleWords ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Block Ads for Title Keywords (comma separated)</label>
                                <input type="text" name="ads_blocked_title_keywords" class="form-control" maxlength="300" value="<?= e($adsBlockedTitleKeywords) ?>" placeholder="opinion, breaking, live blog">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Block Ads for Categories (comma separated)</label>
                                <input type="text" name="ads_blocked_categories" class="form-control" maxlength="300" value="<?= e($adsBlockedCategories) ?>" placeholder="news, opinion, analysis">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Ad Label</label>
                                <input type="text" name="ads_label_text" class="form-control" maxlength="40" value="<?= e($adsLabelText) ?>" placeholder="Sponsored">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Ad HTML / Script Code</label>
                                <textarea name="ads_html_code" class="form-control" rows="5" placeholder="Paste AdSense or custom ad snippet"><?= e($adsHtmlCode) ?></textarea>
                            </div>
                            <div class="col-12">
                                <button name="update_ads_settings" value="1" class="btn btn-outline-light w-100">Save Ads Controls</button>
                            </div>
                        </form>
                        <small class="text-secondary">Smart mode uses content-aware rules, minimum article length checks, and optional title keyword blocking for safer monetization.</small>
                        <div class="mt-3 ads-preview">
                            <div class="inline-ad-unit">
                                <div class="inline-ad-label"><?= e($adsLabelText) ?> • Preview</div>
                                <?= $adsHtmlCode !== '' ? $adsHtmlCode : '<div class="ad-unit-inner">Place your ad code here</div>' ?>
                            </div>
                        </div>
                    </div>
                </div>

            <div class="card section-card mb-3 panel-section" id="scripts-settings" style="display:none;">
                <div class="card-body">
                    <h5><i class="bi bi-code-slash"></i> Scripts & Tracking</h5>
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-6">
                            <label class="form-label">Google Analytics ID</label>
                            <input type="text" name="google_analytics_id" class="form-control" value="<?= e($googleAnalyticsId) ?>" placeholder="G-XXXXXXXXXX">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Google Tag Manager ID</label>
                            <input type="text" name="google_tag_manager_id" class="form-control" value="<?= e($googleTagManagerId) ?>" placeholder="GTM-XXXXXXX">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Google Verification</label>
                            <input type="text" name="google_site_verification" class="form-control" maxlength="255" value="<?= e($googleSiteVerification) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Bing Verification</label>
                            <input type="text" name="bing_site_verification" class="form-control" maxlength="255" value="<?= e($bingSiteVerification) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Meta Pixel ID</label>
                            <input type="text" name="meta_pixel_id" class="form-control" maxlength="30" value="<?= e($metaPixelId) ?>" placeholder="123456789012345">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Custom &lt;head&gt; Scripts</label>
                            <textarea name="custom_head_scripts" class="form-control" rows="4" placeholder="&lt;script&gt;...&lt;/script&gt;"><?= e($customHeadScripts) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Custom Footer Scripts (before &lt;/body&gt;)</label>
                            <textarea name="custom_body_scripts" class="form-control" rows="4" placeholder="&lt;script&gt;...&lt;/script&gt;"><?= e($customBodyScripts) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ads.txt</label>
                            <textarea name="ads_txt_content" class="form-control" rows="5" placeholder="google.com, pub-xxxxxxxxxxxxxxxx, DIRECT, f08c47fec0942fa0"><?= e($adsTxtContent) ?></textarea>
                            <small class="text-secondary">This content is saved in settings and written to <code>/ads.txt</code>.</small>
                        </div>
                        <div class="col-12">
                            <button name="update_scripts_settings" value="1" class="btn btn-outline-light w-100">Save Scripts & Tracking</button>
                        </div>
                    </form>
                    <small class="text-secondary">Use this section to inject tracking scripts globally across the site.</small>
                    <div class="alert alert-secondary mt-3 mb-0">
                        <div><strong>Sitemap URL:</strong> <code id="sitemap-url"><?= e($sitemapUrl) ?></code>
                            <button type="button" class="copy-btn" onclick="copyToClipboard('sitemap-url')" title="نسخ"><i class="bi bi-clipboard"></i></button>
                            <span id="copy-success-sitemap" class="copy-success" style="display:none;">تم النسخ!</span>
                        </div>
                        <div class="small mt-2">Submit this URL in Google Search Console and Bing Webmaster Tools for faster indexing.</div>
                    </div>
                </div>
            </div>


            <div class="card section-card mb-3">
                <div class="card-body">
                    <h5><i class="bi bi-diagram-3"></i> Content Workflow Selection</h5>
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-8">
                            <label class="form-label">Selected Content Workflow</label>
                            <select name="content_workflow" class="form-select">
                                <option value="rss" <?= $selectedWorkflow === 'rss' ? 'selected' : '' ?>>RSS Workflow</option>
                                <option value="web" <?= $selectedWorkflow === 'web' ? 'selected' : '' ?>>Normal Sites Workflow (Symfony DomCrawler)</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <button name="update_content_workflow" value="1" class="btn btn-outline-light w-100">Apply</button>
                        </div>
                    </form>
                    <small class="text-secondary">Cron and manual run will execute the selected workflow only.</small>
                </div>
            </div>

            <div class="card section-card mb-3 panel-section" id="auto-scheduler-section" style="display:none;">
                <div class="card-body">
                    <?php
                    $nichesListStmt = $pdo->prepare("SELECT id, slug, name, description FROM niches ORDER BY id");
                    $nichesListStmt->execute();
                    $nichesList = $nichesListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    ?>
                    <h5 class="text-danger"><i class="bi bi-robot"></i> Smart Niche Automation Hub <span class="badge text-bg-dark ms-2">Pro</span></h5>
                    <p class="text-secondary mb-3">دمج ذكي بين <strong>AI Auto Publish Scheduler</strong> و <strong>Niche Management</strong> و <strong>Auto Title Generator Controls</strong> و <strong>Source Intake</strong> في لوحة واحدة لإدارة أسرع وأوضح.</p>
                    <div class="smart-toolbar">
                        <span class="smart-pill"><i class="bi bi-diagram-3"></i> Niches: <?= count($nichesList) ?></span>
                        <span class="smart-pill"><i class="bi bi-tags"></i> Active: <?= e((string)getSetting('active_niche', 'general')) ?></span>
                        <span class="smart-pill"><i class="bi bi-clock-history"></i> Interval: <?= (int)$autoPublishIntervalFrom ?> - <?= (int)$autoPublishIntervalTo ?>s</span>
                        <span class="smart-pill"><i class="bi bi-cpu"></i> Mode: <?= e(strtoupper($autoTitleMode)) ?></span>
                        <span class="smart-pill"><i class="bi bi-lightning-charge"></i> Scheduler: <?= $autoAiEnabled ? 'ON' : 'OFF' ?></span>
                    </div>
                    <form method="post" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-md-4">
                            <label class="form-label">Active Niche</label>
                            <select name="smart_active_niche" class="form-select">
                                <?php foreach ($nichesList as $n): ?>
                                    <option value="<?= e($n['slug']) ?>" <?= e((string)getSetting('active_niche', 'general')) === $n['slug'] ? 'selected' : '' ?>><?= e($n['name']) ?> (<?= e($n['slug']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Auto Title Mode</label>
                            <select name="smart_auto_title_mode" class="form-select">
                                <option value="template" <?= $autoTitleMode === 'template' ? 'selected' : '' ?>>Template + Variables</option>
                                <option value="list" <?= $autoTitleMode === 'list' ? 'selected' : '' ?>>Fixed Titles List</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Year Offset Min</label>
                            <input type="number" name="smart_auto_title_min_year_offset" class="form-control" min="-1" max="2" value="<?= (int)$autoTitleMinYearOffset ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Year Offset Max</label>
                            <input type="number" name="smart_auto_title_max_year_offset" class="form-control" min="-1" max="3" value="<?= (int)$autoTitleMaxYearOffset ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Interval From</label>
                            <input type="number" name="smart_auto_publish_interval_seconds_from" class="form-control" min="0" max="300000" value="<?= (int)$autoPublishIntervalFrom ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Interval To</label>
                            <input type="number" name="smart_auto_publish_interval_seconds_to" class="form-control" min="0" max="300000" value="<?= (int)$autoPublishIntervalTo ?>">
                        </div>
                        <div class="col-md-1">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="smart_auto_ai_enabled" name="smart_auto_ai_enabled" value="1" <?= $autoAiEnabled ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <div class="col-12">
                            <button name="update_smart_niche_automation" value="1" class="btn btn-warning w-100">Apply Smart Merge Settings</button>
                        </div>
                    </form>

                    <div class="alert alert-info">
                        <strong>أمثلة نيشات مقترحة:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>EV</strong>: Tesla, BYD, Lucid + محتوى الشحن السريع والمدى.</li>
                            <li><strong>SUV Family</strong>: أمان العائلة، المساحة، أفضل 7 مقاعد.</li>
                            <li><strong>Luxury</strong>: Mercedes, BMW, Audi + مراجعات الفخامة والتقنيات.</li>
                            <li><strong>Motorcycles</strong>: Adventure/Street bikes + معدات القيادة.</li>
                            <li><strong>Budget Cars</strong>: أفضل سيارات اقتصادية واستهلاك الوقود.</li>
                        </ul>
                    </div>


                    <hr class="border-secondary-subtle my-3">
                    <h6><span class="badge text-bg-secondary me-2">1</span><i class="bi bi-kanban-fill"></i> Niche Management (Integrated)</h6>

                    <form method="post" class="row g-2 mb-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-12">
                            <label class="form-label">نشر محتوى لأكثر من نيش</label>
                            <?php $prefilledSelectedNiches = array_values(array_filter(array_map('trim', explode(',', (string)getSetting('smart_source_prefill_selected_niches', ''))))); ?>
                            <select name="multi_niches[]" class="form-select" multiple>
                                <?php foreach ($nichesList as $n): ?>
                                    <option value="<?= e($n['slug']) ?>" <?= in_array($n['slug'], $prefilledSelectedNiches, true) ? 'selected' : '' ?>><?= e($n['name']) ?> (<?= e($n['slug']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-2">
                            <button name="publish_multi_niches" value="1" class="btn btn-success">نشر في جميع النيشات المختارة</button>
                        </div>
                    </form>

                    

                    <form method="post" class="row g-2 mb-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-12">
                            <label class="form-label">Create New Niche</label>
                        </div>
                        <div class="col-4">
                            <input type="text" name="niche_slug" class="form-control" placeholder="slug (optional)">
                        </div>
                        <div class="col-4">
                            <input type="text" name="niche_name" class="form-control" placeholder="Display name" required>
                        </div>
                        <div class="col-4">
                            <input type="text" name="niche_description" class="form-control" placeholder="Short description">
                        </div>
                        <div class="col-12">
                            <button name="create_niche" value="1" class="btn btn-outline-light">Create Niche</button>
                        </div>
                    </form>

                    <div class="alert alert-info mb-3">
                        <strong>ملاحظة:</strong> استخدم اختيار النيش في أعلى قسم <strong>Smart Niche Automation Hub Pro</strong> كنقطة تحكم واحدة للنيش النشط. سيُستخدم هذا النيش في إضافة المصادر والكتاب الآلي والعناوين.
                    </div>

                    <div class="list-group mb-3">
                        <?php foreach ($nichesList as $n): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= e($n['name']) ?></strong>
                                        <div class="small text-secondary"><?= e($n['slug']) ?> — <?= e($n['description']) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <button class="btn btn-sm btn-outline-secondary me-1 niche-details-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#niche-details-<?= (int)$n['id'] ?>" aria-expanded="false" aria-controls="niche-details-<?= (int)$n['id'] ?>">Show Fields</button>
                                        <form method="post" onsubmit="return confirm('Delete this niche?');" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                            <button name="delete_niche" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>

                                <?php
                                    $sourceGroups = \App\NicheManager::getNicheSources((int)$n['id']);
                                ?>
                                <div class="collapse mt-2" id="niche-details-<?= (int)$n['id'] ?>">
                                <div class="row g-2">
                                    <div class="col-12 mb-3">
                                        <form method="post" class="row g-2 align-items-end">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                            <div class="col-sm-4">
                                                <label class="form-label">Niche Name</label>
                                                <input type="text" name="niche_name" class="form-control" value="<?= e($n['name']) ?>" required>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label">Description</label>
                                                <input type="text" name="niche_description" class="form-control" value="<?= e($n['description']) ?>">
                                            </div>
                                            <div class="col-sm-2">
                                                <button name="update_niche" class="btn btn-sm btn-outline-primary w-100">Update</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="col-12">
                                        <div class="small mb-2 text-secondary">Sources:</div>
                                        <?php if (empty($sourceGroups['rss']) && empty($sourceGroups['web'])): ?>
                                            <em class="text-secondary">No sources configured.</em>
                                        <?php else: ?>
                                            <?php if (!empty($sourceGroups['rss'])): ?>
                                                <div class="mb-2">
                                                    <strong>RSS Sources</strong>
                                                    <?php foreach ($sourceGroups['rss'] as $url): ?>
                                                        <div class="d-flex justify-content-between align-items-center py-1">
                                                            <div><strong>[rss]</strong> <?= e($url) ?></div>
                                                            <form method="post" class="ms-2">
                                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                                <input type="hidden" name="source_url" value="<?= e($url) ?>">
                                                                <input type="hidden" name="source_type" value="rss">
                                                                <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                                                <button name="remove_niche_source" class="btn btn-sm btn-outline-light">Remove</button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($sourceGroups['web'])): ?>
                                                <div class="mb-2">
                                                    <strong>Web Sources</strong>
                                                    <?php foreach ($sourceGroups['web'] as $url): ?>
                                                        <div class="d-flex justify-content-between align-items-center py-1">
                                                            <div><strong>[web]</strong> <?= e($url) ?></div>
                                                            <form method="post" class="ms-2">
                                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                                <input type="hidden" name="source_url" value="<?= e($url) ?>">
                                                                <input type="hidden" name="source_type" value="web">
                                                                <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                                                <button name="remove_niche_source" class="btn btn-sm btn-outline-light">Remove</button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-12">
                                        <form method="post" class="row g-2">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                            <div class="col-12 col-md-3">
                                                <label class="form-label">Source Type</label>
                                                <select name="source_type" class="form-select">
                                                    <option value="rss">RSS</option>
                                                    <option value="web">Web</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label">Source URL</label>
                                                <input type="url" name="source_url" class="form-control" placeholder="https://example.com/feed.xml" required>
                                            </div>
                                            <div class="col-12 col-md-3 d-grid">
                                                <button name="add_niche_source" class="btn btn-outline-light">Save Source</button>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="col-12">
                                        <form method="post" class="row g-2">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                            <div class="col-md-3">
                                                <label class="form-label">Replace Type</label>
                                                <select name="source_type" class="form-select">
                                                    <option value="rss">Replace RSS List</option>
                                                    <option value="web">Replace Web List</option>
                                                </select>
                                            </div>
                                            <div class="col-md-7">
                                                <label class="form-label">Source URLs</label>
                                                <textarea name="source_urls_bulk" class="form-control" rows="3" placeholder="Paste one URL per line"></textarea>
                                            </div>
                                            <div class="col-md-2 d-grid">
                                                <button name="replace_niche_sources" class="btn btn-outline-warning w-100" onclick="return confirm('This will replace all existing sources of this type for this niche. Continue?');">Replace</button>
                                            </div>
                                            <div class="col-12">
                                                <small class="text-secondary">لكل نيش قائمة مستقلة بالكامل للمصادر. يمكنك لصق قائمة روابط كاملة وسيتم استبدالها دفعة واحدة.</small>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                    <div class="col-4">
                                        <select name="source_type" class="form-select">
                                            <option value="rss">RSS</option>
                                            <option value="web">Web</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <input type="url" name="source_url" class="form-control" placeholder="https://example.com/feed.xml" required>
                                    </div>
                                    <div class="col-2">
                                        <button name="add_niche_source" class="btn btn-outline-light w-100">Save Source</button>
                                    </div>
                                </form>


                                <form method="post" class="row g-2 mt-2">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="niche_id" value="<?= (int)$n['id'] ?>">
                                    <div class="col-md-3">
                                        <select name="source_type" class="form-select">
                                            <option value="rss">Replace RSS List</option>
                                            <option value="web">Replace Web List</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7">
                                        <textarea name="source_urls_bulk" class="form-control" rows="3" placeholder="Paste one URL per line"></textarea>
                                    </div>
                                    <div class="col-md-2">
                                        <button name="replace_niche_sources" class="btn btn-outline-warning w-100" onclick="return confirm('This will replace all existing sources of this type for this niche. Continue?');">Replace</button>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-secondary">لكل نيش قائمة مستقلة بالكامل للمصادر. يمكنك لصق قائمة روابط كاملة وسيتم استبدالها دفعة واحدة.</small>
                                    </div>
                                </form>
                                <form method="post" class="row g-2 mt-3 border-top pt-2">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="niche_title_slug" value="<?= e($n['slug']) ?>">
                                    <?php
                                        $nichePrefix = 'niche.' . $n['slug'] . '.';
                                        $nicheModeValue = (string)getSetting($nichePrefix . 'auto_title_mode', 'template');
                                        $nicheFixedTitlesValue = (string)getSetting($nichePrefix . 'auto_title_fixed_titles', '');
                                        $nicheBrandsValue = (string)getSetting($nichePrefix . 'auto_title_brands', '');
                                        $nicheModelsValue = (string)getSetting($nichePrefix . 'auto_title_models', '');
                                        $nicheModifiersValue = (string)getSetting($nichePrefix . 'auto_title_modifiers', '');
                                        $nicheAudiencesValue = (string)getSetting($nichePrefix . 'auto_title_audiences', '');
                                        $nicheAnglesValue = (string)getSetting($nichePrefix . 'auto_title_angles', '');
                                        $nicheTemplatesValue = (string)getSetting($nichePrefix . 'auto_title_templates', '');
                                    ?>
                                    <div class="col-md-2">
                                        <select name="niche_auto_title_mode" class="form-select">
                                            <option value="template" <?= $nicheModeValue === 'template' ? 'selected' : '' ?>>Template</option>
                                            <option value="list" <?= $nicheModeValue === 'list' ? 'selected' : '' ?>>Fixed List</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <textarea name="niche_fixed_titles" class="form-control" rows="2" placeholder="Paste niche titles (one per line)"><?= e($nicheFixedTitlesValue) ?></textarea>
                                    </div>
                                    <div class="col-md-5">
                                        <textarea name="niche_brands" class="form-control" rows="2" placeholder="Paste niche keywords/brands (one per line)"><?= e($nicheBrandsValue) ?></textarea>
                                    </div>
                                    <div class="col-md-6"><textarea name="niche_models" class="form-control" rows="2" placeholder="Models / topics list"><?= e($nicheModelsValue) ?></textarea></div>
                                    <div class="col-md-6"><textarea name="niche_modifiers" class="form-control" rows="2" placeholder="Modifiers e.g. guide, review"><?= e($nicheModifiersValue) ?></textarea></div>
                                    <div class="col-md-6"><textarea name="niche_audiences" class="form-control" rows="2" placeholder="Audience list"><?= e($nicheAudiencesValue) ?></textarea></div>
                                    <div class="col-md-6"><textarea name="niche_angles" class="form-control" rows="2" placeholder="Angles list"><?= e($nicheAnglesValue) ?></textarea></div>
                                    <div class="col-12"><textarea name="niche_templates" class="form-control" rows="2" placeholder="Title templates with {year} {brand} {model} {modifier} {angle} {audience}"><?= e($nicheTemplatesValue) ?></textarea></div>
                                    <div class="col-12">
                                        <button name="save_niche_title_pack" value="1" class="btn btn-outline-info w-100">Save Niche Title/Keywords Pack</button>
                                    </div>
                                </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <small class="text-secondary">Use niches to segment sources and generate content for different verticals (e.g., EVs, Motorcycles, Home Appliances).</small>
                </div>
            </div>

            <div class="card section-card mb-3">
                <div class="card-body">
                    <h5><i class="bi bi-shield-check"></i> Fetch Protection Settings</h5>
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-4">
                            <label class="form-label">Fetch Timeout (s)</label>
                            <input type="number" name="fetch_timeout_seconds" class="form-control" min="3" max="45" value="<?= (int)$fetchTimeoutSeconds ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Retry Attempts</label>
                            <input type="number" name="fetch_retry_attempts" class="form-control" min="1" max="5" value="<?= (int)$fetchRetryAttempts ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Retry Backoff (ms)</label>
                            <input type="number" name="fetch_retry_backoff_ms" class="form-control" min="100" max="3000" value="<?= (int)$fetchRetryBackoffMs ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Source Cooldown (s)</label>
                            <input type="number" name="queue_source_cooldown_seconds" class="form-control" min="30" max="7200" value="<?= (int)$queueSourceCooldownSeconds ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Fetcher User-Agent</label>
                            <input type="text" name="fetch_user_agent" class="form-control" maxlength="255" value="<?= e($fetchUserAgent) ?>">
                        </div>
                        <div class="col-12">
                            <button name="update_fetch_settings" value="1" class="btn btn-outline-light w-100">Update Fetch Settings</button>
                        </div>
                    </form>
                    <small class="text-secondary">Anti-block controls: timeout, retries with backoff, URL queue cooldown, and custom UA.</small>
                </div>
            </div>

            <div class="card section-card mb-3 panel-section" id="pipeline-config-section" style="display:none;">
                <div class="card-body">
                    <h5 class="text-danger"><i class="bi bi-cpu"></i> Core Pipeline Config</h5>
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-6">
                            <label class="form-label">Minimum Article Words (from)</label>
                            <input type="number" name="min_words_from" class="form-control" min="0" max="300000" value="<?= (int)$minWordsFrom ?>" placeholder="0000" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Minimum Article Words (to)</label>
                            <input type="number" name="min_words_to" class="form-control" min="0" max="300000" value="<?= (int)$minWordsTo ?>" placeholder="300000" required>
                            <small class="text-secondary">النطاق المسموح: من 0000 إلى 300000 (خانتين: من / إلى).</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label">URL Cache TTL (s)</label>
                            <input type="number" name="url_cache_ttl_seconds" class="form-control" min="60" max="86400" value="<?= (int)$urlCacheTtlSeconds ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Workflow Batch Size</label>
                            <input type="number" name="workflow_batch_size" class="form-control" min="1" max="50" value="<?= (int)$workflowBatchSize ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Queue Retry Delay (s)</label>
                            <input type="number" name="queue_retry_delay_seconds" class="form-control" min="5" max="7200" value="<?= (int)$queueRetryDelaySeconds ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Queue Max Attempts</label>
                            <input type="number" name="queue_max_attempts" class="form-control" min="1" max="20" value="<?= (int)$queueMaxAttempts ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Auto Publish Interval (minutes)</label>
                            <input type="number" name="auto_publish_interval_minutes" class="form-control" min="1" value="<?= (int)$autoPublishIntervalMinutes ?>">
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="api_lock_enabled" name="api_lock_enabled" <?= $apiLockEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="api_lock_enabled">Enable API Lock (require API key)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">API Access Key</label>
                            <input type="text" name="api_access_key" class="form-control" maxlength="255" value="<?= e($apiAccessKey) ?>" placeholder="Set a secret key for api.php">
                            <small class="text-secondary">When lock is enabled, pass key using <code>?key=YOUR_SECRET</code> or <code>X-API-Key</code> header.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Exclude IPs From Visit Analytics</label>
                            <textarea name="visit_excluded_ips" class="form-control" rows="3" placeholder="127.0.0.1
203.0.113.4"><?= e($visitExcludedIps) ?></textarea>
                            <small class="text-secondary">One IP per line or separated by commas/spaces. Supports exact IPs and IPv4 CIDR (example: 203.0.113.0/24).</small>
                            <?php if ($detectedVisitorIp !== ''): ?>
                                <small class="d-block text-info mt-1">Detected current request IP: <code><?= e($detectedVisitorIp) ?></code></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <button name="update_pipeline_settings" value="1" class="btn btn-outline-info w-100">Update Pipeline Config</button>
                        </div>
                    </form>
                    <small class="text-secondary">Includes the main configuration values from <code>config.php</code> so you can manage them from one place.</small>
                
                    <hr class="border-secondary-subtle my-3">
                    <h6><span class="badge text-bg-secondary me-2">2</span><i class="bi bi-columns-gap"></i> Unified Source Intake (RSS + Web) linked to Niche</h6>
                    <form method="post" class="row g-2 mb-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-md-5">
                            <input type="url" name="smart_source_url" class="form-control" value="<?= e((string)getSetting('smart_source_prefill_url', '')) ?>" placeholder="https://example.com/feed.xml or /news/">
                            <textarea name="smart_source_urls" class="form-control mt-2" rows="10" placeholder="Bulk URLs (one per line)"><?= e((string)getSetting('smart_source_prefill_bulk', '')) ?></textarea>
                            <small class="text-secondary d-block mt-1">تم تعمير هذه الخانة تلقائياً عند الضغط على زر التعبئة الذكية.</small>
                        </div>
                        <div class="col-md-3">
                            <select name="smart_source_type" class="form-select">
                                <option value="auto" <?= e((string)getSetting('smart_source_prefill_type', 'auto')) === 'auto' ? 'selected' : '' ?>>Auto Detect Type</option>
                                <option value="rss" <?= e((string)getSetting('smart_source_prefill_type', 'auto')) === 'rss' ? 'selected' : '' ?>>Force RSS</option>
                                <option value="web" <?= e((string)getSetting('smart_source_prefill_type', 'auto')) === 'web' ? 'selected' : '' ?>>Force Web</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="smart_target_niche_slug" class="form-select">
                                <option value="">Optional niche link (falls back to active niche)</option>
                                <?php $currentActiveNiche = (string)getSetting('active_niche', 'general'); ?>
                                <?php foreach ($nichesList as $n): ?>
                                    <option value="<?= e($n['slug']) ?>" <?= $currentActiveNiche === $n['slug'] ? 'selected' : '' ?>><?= e($n['name']) ?> (<?= e($n['slug']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button name="add_source_smart" value="1" class="btn btn-warning w-100" onclick="this.form.smart_source_preview.value='0';">Save Source</button>
                            <button name="add_source_smart" value="1" class="btn btn-outline-info w-100 mt-2" onclick="this.form.smart_source_preview.value='1';">Preview Parse</button>
                            <input type="hidden" name="smart_source_preview" value="0">
                            <button name="fill_smart_sources_10_per_niche" value="1" class="btn btn-outline-light w-100 mt-2">Fill 10 RSS / Niche</button>
                            <button name="fill_all_smart_hub_fields" value="1" class="btn btn-light w-100 mt-2">Fill All Fields</button>
                            <button name="fill_closed_fields_all_niches" value="1" class="btn btn-secondary w-100 mt-2">Fill Closed Fields / All Niches</button>
                        </div>
                    </form>
                    <div class="alert alert-secondary small mb-3">
                        استخدم نموذج <strong>Unified Source Intake</strong> أعلاه لإضافة RSS أو مواقع عادية (مفرد أو جماعي). إذا تركت النيش فارغًا، سيتم الربط تلقائيًا بـ <strong>النيش النشط</strong>.
                    </div>
                    <hr class="border-secondary-subtle my-3">
                    <h6><span class="badge text-bg-secondary me-2">3</span><i class="bi bi-sliders"></i> Advanced Scheduler + Title Controls</h6>
                    <div class="alert alert-secondary mb-3">
                        <strong>تحسين:</strong> تم توحيد إدارة العنوان التلقائي عبر النيش النشط فقط. اختر النيش من أعلى لوحة Smart Hub ثم اضغط "Apply Smart Merge Settings" لتحديث إعدادات العنوان والجدولة وتحديد النيش النشط.
                    </div>

                                    <!-- تحسينات ذكية: عرض ملخص لكل نيش وعدد المقالات والمصادر -->
                                    <?php
                                        $nicheArticleCountStmt = $pdo->prepare('SELECT category, COUNT(*) AS total FROM articles GROUP BY category');
                                        $nicheArticleCountStmt->execute();
                                        $nicheArticleCounts = [];
                                        foreach ($nicheArticleCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                            $nicheArticleCounts[(string)($row['category'] ?? '')] = (int)($row['total'] ?? 0);
                                        }

                                        $nicheSourceCountStmt = $pdo->prepare('SELECT niche_id, COUNT(*) AS total FROM niche_sources GROUP BY niche_id');
                                        $nicheSourceCountStmt->execute();
                                        $nicheSourceCounts = [];
                                        foreach ($nicheSourceCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                                            $nicheSourceCounts[(int)($row['niche_id'] ?? 0)] = (int)($row['total'] ?? 0);
                                        }
                                    ?>
                                    <div class="mb-3">
                                        <h6 class="text-info">ملخص النيشات</h6>
                                        <ul class="list-group">
                                            <?php foreach ($nichesList as $n): ?>
                                                <?php
                                                    $nicheSlug = (string)($n['slug'] ?? '');
                                                    $nicheId = (int)($n['id'] ?? 0);
                                                    $articleCount = (int)($nicheArticleCounts[$nicheSlug] ?? 0);
                                                    $sourceCount = (int)($nicheSourceCounts[$nicheId] ?? 0);
                                                ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span><strong><?= e($n['name']) ?></strong> (<?= e($nicheSlug) ?>)</span>
                                                    <span class="badge bg-primary">مقالات: <?= $articleCount ?></span>
                                                    <span class="badge bg-success">مصادر: <?= $sourceCount ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="auto_ai_enabled" name="auto_ai_enabled" value="1" <?= $autoAiEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_ai_enabled">Enable fully automatic title + article publishing</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Publish Every (from)</label>
                            <input type="number" name="auto_publish_interval_seconds_from" class="form-control" min="0" max="300000" value="<?= (int)$autoPublishIntervalFrom ?>" placeholder="0000" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Publish Every (to)</label>
                            <input type="number" name="auto_publish_interval_seconds_to" class="form-control" min="0" max="300000" value="<?= (int)$autoPublishIntervalTo ?>" placeholder="300000" required>
                        </div>
                        <div class="col-4">
                            <button name="update_auto_scheduler" value="1" class="btn btn-outline-warning w-100">Update</button>
                        </div>
                    </form>
                    <small class="text-secondary d-block mt-2">Range: 0000-300000 (two fields: from/to). Last automatic publish run: <?= e($autoPublishLastRun) ?></small>

                    <hr class="border-secondary-subtle my-3">
                    <h6><i class="bi bi-type"></i> Auto Title Generator Controls</h6>
                    <form method="post" class="row g-2 mt-1">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-6">
                            <label class="form-label">Mode</label>
                            <select name="auto_title_mode" class="form-select">
                                <option value="template" <?= $autoTitleMode === 'template' ? 'selected' : '' ?>>Template + Variables</option>
                                <option value="list" <?= $autoTitleMode === 'list' ? 'selected' : '' ?>>Fixed Titles List</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Min Year Offset</label>
                            <input type="number" name="auto_title_min_year_offset" class="form-control" min="-1" max="2" value="<?= (int)$autoTitleMinYearOffset ?>">
                        </div>
                        <div class="col-3">
                            <label class="form-label">Max Year Offset</label>
                            <input type="number" name="auto_title_max_year_offset" class="form-control" min="-1" max="3" value="<?= (int)$autoTitleMaxYearOffset ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Brands (one per line)</label>
                            <textarea name="auto_title_brands" class="form-control" rows="4"><?= e($autoTitleBrands) ?></textarea>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Models (one per line)</label>
                            <textarea name="auto_title_models" class="form-control" rows="4"><?= e($autoTitleModels) ?></textarea>
                        </div>
                        <div class="col-6">
                            <label class="form-label">SEO Modifiers (one per line)</label>
                            <textarea name="auto_title_modifiers" class="form-control" rows="4"><?= e($autoTitleModifiers) ?></textarea>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Audience Segments (one per line)</label>
                            <textarea name="auto_title_audiences" class="form-control" rows="4"><?= e($autoTitleAudiences) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Angles (one per line)</label>
                            <textarea name="auto_title_angles" class="form-control" rows="4"><?= e($autoTitleAngles) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Templates (one per line)</label>
                            <textarea name="auto_title_templates" class="form-control" rows="4"><?= e($autoTitleTemplates) ?></textarea>
                            <small class="text-secondary">Allowed variables: <code>{year}</code>, <code>{brand}</code>, <code>{model}</code>, <code>{modifier}</code>, <code>{angle}</code>, <code>{audience}</code>.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Fixed Titles (one per line, used when mode = Fixed Titles List)</label>
                            <textarea name="auto_title_fixed_titles" class="form-control" rows="4"><?= e($autoTitleFixedTitles) ?></textarea>
                        </div>
                        <div class="col-12">
                            <button name="update_auto_title_settings" value="1" class="btn btn-outline-warning w-100">Save Title Generation Controls</button>
                        </div>
                        <div class="col-6">
                            <button name="preview_auto_titles" value="1" class="btn btn-outline-info w-100">Preview 5 Generated Titles</button>
                        </div>
                        <div class="col-6">
                            <button name="reset_auto_title_defaults" value="1" class="btn btn-outline-secondary w-100" onclick="return confirm('Reset all title controls to defaults?');">Reset to Defaults</button>

                        </div>
                    </form>

                    <div class="alert alert-secondary mt-3 mb-2">
                        <div class="small text-uppercase text-muted mb-1">Hosting Cron URL</div>
                        <code class="d-block text-break"><?= e($cronUrl) ?></code>
                        <small class="text-secondary d-block mt-2">Set your hosting cron job to call this URL every 10 seconds (or the smallest interval your provider allows).</small>
                        <small class="text-secondary d-block">No token required. This URL supports HTTPS proxy headers and subfolder deployments automatically.</small>
                    </div>
                    <form method="post" class="mt-2">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button name="auto_generate_now" value="1" class="btn btn-outline-success w-100">
                            <i class="bi bi-magic"></i> Generate Title + Publish Now
                        </button>
                    </form>

                    <form method="post" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <button name="generate_demo_pack" value="1" class="btn btn-outline-info w-100">
                            <i class="bi bi-stars"></i> Generate Demo Content Pack
                        </button>
                    </form>
                </div>
            </div>

            <div class="card section-card mb-3 panel-section" id="admin-password-settings" style="display:none;">
                <div class="card-body">
                    <h5><i class="bi bi-key"></i> Change Admin Password</h5>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <div class="col-12">
                            <input type="password" name="current_password" class="form-control" placeholder="Current password" autocomplete="current-password" required>
                        </div>
                        <div class="col-12">
                            <input type="password" name="new_password" class="form-control" placeholder="New password (min 8 chars)" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="col-12">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button name="update_admin_password" value="1" class="btn btn-outline-light">Save New Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card section-card mb-3">
                <div class="card-body">
                    <h5><i class="bi bi-gear-wide-connected"></i> System Constants (Read Only)</h5>
                    <div class="small">
                        <div><span class="text-secondary">SITE_TITLE (fallback constant):</span> <code id="site-title-const"><?= e(SITE_TITLE) ?></code>
                            <button type="button" class="copy-btn" onclick="copyToClipboard('site-title-const')" title="نسخ"><i class="bi bi-clipboard"></i></button>
                            <span id="copy-success-title" class="copy-success" style="display:none;">تم النسخ!</span>
                        </div>
                        <div><span class="text-secondary">Configured Site Title:</span> <code id="site-title-config"><?= e($siteTitle) ?></code>
                            <button type="button" class="copy-btn" onclick="copyToClipboard('site-title-config')" title="نسخ"><i class="bi bi-clipboard"></i></button>
                            <span id="copy-success-config" class="copy-success" style="display:none;">تم النسخ!</span>
                        </div>
                        <div><span class="text-secondary">DB_FILE:</span> <code id="db-file"><?= e(DB_FILE) ?></code>
                            <button type="button" class="copy-btn" onclick="copyToClipboard('db-file')" title="نسخ"><i class="bi bi-clipboard"></i></button>
                            <span id="copy-success-db" class="copy-success" style="display:none;">تم النسخ!</span>
                        </div>
                        <div><span class="text-secondary">Password Hash:</span> <code id="pass-hash"><?= e(substr(PASSWORD_HASH, 0, 20)) ?>...</code>
                            <button type="button" class="copy-btn" onclick="copyToClipboard('pass-hash')" title="نسخ"><i class="bi bi-clipboard"></i></button>
                            <span id="copy-success-hash" class="copy-success" style="display:none;">تم النسخ!</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card section-card mb-3 panel-section" id="sources-management" style="display:none;">
                <div class="card-body">
                    <h5><i class="bi bi-pencil-square"></i> Add Titles Manually</h5>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <textarea name="titles" class="form-control" rows="6" placeholder="One title per line"></textarea>
                        <button name="add_titles" class="btn btn-success mt-3 w-100">Add to Queue & Generate</button>
                    </form>
                </div>
            </div>

        </div>

        <div class="col-xl-12" id="content-data">
            <form method="post" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <button name="run_content_workflow" value="1" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-arrow-repeat"></i> Run Selected Content Workflow & Generate
                </button>
            </form>
            <div class="d-flex gap-2 mb-3">
                <a href="admin.php?export=articles_json" class="btn btn-outline-light w-100"><i class="bi bi-filetype-json"></i> Export JSON</a>
                <a href="admin.php?export=articles_csv" class="btn btn-outline-light w-100"><i class="bi bi-filetype-csv"></i> Export CSV</a>
            </div>


                <div id="articles-pane">
                    <div class="card section-card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Recent Articles</h5>
                                <form method="get" class="d-flex gap-2">
                                    <input type="text" name="qa" class="form-control form-control-sm" placeholder="Search title" value="<?= e($articleSearch) ?>">
                                    <select name="cat" class="form-select form-select-sm">
                                        <option value="">All categories</option>
                                        <?php foreach ($categoryOptions as $cat): ?>
                                            <option value="<?= e($cat) ?>" <?= $articleCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-light">Filter</button>
                                </form>
                            </div>

                            <form method="post" class="row g-2 align-items-end mb-3" id="articlesBulkForm">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <div class="col-md-5">
                                    <label class="form-label">Bulk Action</label>
                                    <select class="form-select form-select-sm" name="bulk_article_action">
                                        <option value="set_category">Set Category</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Category (for Set Category)</label>
                                    <input type="text" class="form-control form-control-sm" name="bulk_category" placeholder="e.g. Reviews">
                                </div>
                                <div class="col-md-2">
                                    <button name="bulk_update_articles" value="1" class="btn btn-outline-warning w-100 btn-sm">Apply</button>
                                </div>
                            </form>

                            <div class="table-responsive rounded shadow-sm">
                                <table class="table table-dark table-striped align-middle mb-0">
                                    <thead>
                                    <tr><th style="width:42px;"><input type="checkbox" id="select-all-articles" class="form-check-input"></th><th>Title</th><th>Category</th><th>Date</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!$articles): ?>
                                        <tr><td colspan="5" class="text-center text-secondary">No articles found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($articles as $row): ?>
                                            <tr>
                                                <td><input type="checkbox" class="form-check-input article-check" name="article_ids[]" value="<?= (int)$row['id'] ?>" form="articlesBulkForm"></td>
                                                <td>
                                                    <div class="fw-semibold"><?= e($row['title']) ?>
                                                        <!-- زر نسخ عنوان المقال -->
                                                        <button type="button" class="copy-btn" onclick="copyToClipboard('article-title-<?= (int)$row['id'] ?>')" title="نسخ"><i class="bi bi-clipboard"></i></button>
                                                        <span id="copy-success-article-title-<?= (int)$row['id'] ?>" class="copy-success" style="display:none;">تم النسخ!</span>
                                                    </div>
                                                    <small class="text-secondary" id="article-title-<?= (int)$row['id'] ?>">/<?= e($row['slug']) ?></small>
                                                </td>
                                                <td><span class="badge text-bg-secondary"><?= e($row['category'] ?: 'General') ?></span></td>
                                                <td><?= e($row['published_at']) ?></td>
                                                <td class="d-flex flex-wrap gap-2">
                                                    <a href="index.php?slug=<?= e($row['slug']) ?>" target="_blank" class="btn btn-sm btn-info">View</a>
                                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#edit-article-<?= (int)$row['id'] ?>" aria-expanded="false">Edit</button>
                                                    <form method="post" onsubmit="return confirm('Delete this article?')">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                        <button name="delete_article" value="<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <tr class="collapse" id="edit-article-<?= (int)$row['id'] ?>">
                                                <td colspan="5">
                                                    <form method="post" class="row g-2">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                        <input type="hidden" name="article_id" value="<?= (int)$row['id'] ?>">
                                                        <div class="col-12">
                                                            <label class="form-label">Title</label>
                                                            <input type="text" name="article_title" class="form-control" required value="<?= e($row['title']) ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Slug</label>
                                                            <input type="text" name="article_slug" class="form-control" required value="<?= e($row['slug']) ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Category</label>
                                                            <input type="text" name="article_category" class="form-control" value="<?= e($row['category']) ?>">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Primary Image URL</label>
                                                            <input type="url" name="article_image" class="form-control" value="<?= e($row['image']) ?>">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Secondary Image URL</label>
                                                            <input type="url" name="article_image2" class="form-control" value="<?= e($row['image2'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Excerpt</label>
                                                            <textarea name="article_excerpt" class="form-control" rows="2"><?= e($row['excerpt']) ?></textarea>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Content (HTML)</label>
                                                            <textarea name="article_content" class="form-control" rows="8" required><?= e($row['content']) ?></textarea>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Translated Title (optional)</label>
                                                            <input type="text" name="article_translated_title" class="form-control" value="<?= e($row['translated_title'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Translated Content (optional)</label>
                                                            <textarea name="article_translated_content" class="form-control" rows="4"><?= e($row['translated_content'] ?? '') ?></textarea>
                                                        </div>
                                                        <div class="col-12">
                                                            <button name="save_article" value="1" class="btn btn-success w-100">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

        </div>
            </div>
        </div>
    </div>
</div>
<script>
    // دالة نسخ للقيم المهمة
    function showTopAlert(message, type) {
        var topAlert = document.getElementById('top-alert');
        if (!topAlert) return;
        topAlert.textContent = message;
        topAlert.className = 'alert alert-' + (type || 'info') + ' text-center';
        topAlert.style.display = 'block';
        setTimeout(function(){ topAlert.style.display = 'none'; }, 1800);
    }

    function revealCopySuccess(elementId) {
        var normalizedSuccessId = 'copy-success-' + elementId.replace(/[^a-zA-Z0-9]/g, '');
        var exactSuccessId = 'copy-success-' + elementId;
        var successEl = document.getElementById(exactSuccessId) || document.getElementById(normalizedSuccessId);
        if (successEl) {
            successEl.style.display = 'inline';
            setTimeout(function(){ successEl.style.display = 'none'; }, 1200);
        }
    }

    function legacyCopyText(text) {
        var temp = document.createElement('textarea');
        temp.value = text;
        temp.setAttribute('readonly', '');
        temp.style.position = 'absolute';
        temp.style.left = '-9999px';
        document.body.appendChild(temp);
        temp.select();
        temp.setSelectionRange(0, 99999);
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }
        document.body.removeChild(temp);
        return ok;
    }

    function copyToClipboard(elementId) {
        var el = document.getElementById(elementId);
        if (!el) return;

        var val = '';
        if ('value' in el && typeof el.value === 'string') {
            val = el.value;
        }
        if (!val) {
            val = el.innerText || el.textContent || '';
        }
        val = val.trim();
        if (!val) return;
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(val).then(function() {
                revealCopySuccess(elementId);
                showTopAlert('تم نسخ القيمة بنجاح!', 'success');
            }).catch(function() {
                var fallbackOk = legacyCopyText(val);
                if (fallbackOk) {
                    revealCopySuccess(elementId);
                    showTopAlert('تم النسخ عبر الطريقة البديلة.', 'success');
                } else {
                    showTopAlert('تعذر النسخ تلقائيًا. انسخ يدويًا.', 'warning');
                }
            });
            return;
        }

        var ok = legacyCopyText(val);
        if (ok) {
            revealCopySuccess(elementId);
            showTopAlert('تم النسخ عبر الطريقة البديلة.', 'success');
        } else {
            showTopAlert('تعذر النسخ تلقائيًا. انسخ يدويًا.', 'warning');
        }
    }
    function initAdminDashboardPanels() {
        document.querySelectorAll('.niche-details-toggle').forEach(function (btn) {
            const targetSel = btn.getAttribute('data-bs-target');
            if (!targetSel) return;
            const target = document.querySelector(targetSel);
            if (!target) return;
            const refreshLabel = function () {
                const isOpen = target.classList.contains('show');
                btn.textContent = isOpen ? 'Hide Fields' : 'Show Fields';
                btn.classList.toggle('btn-outline-secondary', !isOpen);
                btn.classList.toggle('btn-outline-warning', isOpen);
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };
            target.addEventListener('shown.bs.collapse', refreshLabel);
            target.addEventListener('hidden.bs.collapse', refreshLabel);
            refreshLabel();
        });

        const sourceContainer = document.getElementById('control-cards-source');
        const sourceCards = (function () {
            const requiredSectionIds = [
                'publishing-settings',
                'seo-settings',
                'ads-settings',
                'scripts-settings',
                'auto-scheduler-section',
                'pipeline-config-section',
                'admin-password-settings',
                'sources-management'
            ];

            const orderedCards = requiredSectionIds
                .map(function (id) { return document.getElementById(id); })
                .filter(function (card) {
                    return card instanceof HTMLElement && !card.closest('#active-control-panel') && card.querySelector('h5');
                });

            if (orderedCards.length) {
                return orderedCards;
            }

            if (!sourceContainer) {
                return [];
            }

            return Array.from(sourceContainer.querySelectorAll('.panel-section')).filter(function (card) {
                return card instanceof HTMLElement && !card.closest('#active-control-panel') && card.querySelector('h5');
            });
        })();
        const panelNav = document.getElementById('control-panel-nav');
        const panelSearch = document.getElementById('control-panel-search');
        const activeSectionLabel = document.getElementById('active-section-label');
        const activePanel = document.getElementById('active-control-panel');
        const prevBtn = document.getElementById('panel-prev-btn');
        const nextBtn = document.getElementById('panel-next-btn');
        const emptyState = document.getElementById('control-panel-empty');
        const sectionCounter = document.getElementById('section-counter');
        let currentIndex = 0;

        if (sectionCounter) {
            sectionCounter.textContent = sourceCards.length ? '1/' + sourceCards.length + ' sections' : '0/0 sections';
        }

        function getVisibleButtons() {
            if (!panelNav) return [];
            return Array.from(panelNav.querySelectorAll('button')).filter(function (btn) {
                return !btn.classList.contains('d-none');
            });
        }

        function updateCounter() {
            if (!sectionCounter || !panelNav) return;
            const visibleButtons = getVisibleButtons();

            if (!visibleButtons.length) {
                sectionCounter.textContent = '0/0 sections';
                return;
            }

            const activeVisiblePosition = visibleButtons.findIndex(function (btn) {
                return Number(btn.dataset.index) === currentIndex;
            });
            const position = activeVisiblePosition >= 0 ? activeVisiblePosition + 1 : 1;
            sectionCounter.textContent = position + '/' + visibleButtons.length + ' sections';
        }

        function updateNavState() {
            if (!panelNav) return;
            const visibleButtons = getVisibleButtons();
            const hasAny = visibleButtons.length > 0;

            if (prevBtn) prevBtn.disabled = !hasAny;
            if (nextBtn) nextBtn.disabled = !hasAny;
            if (emptyState) emptyState.classList.toggle('d-none', hasAny);
            updateCounter();
        }

        function renderPanel(index) {
            if (!sourceCards[index]) return;
            currentIndex = index;

            sourceCards.forEach(function (card) {
                card.classList.remove('panel-card-highlight');
            });

            const selectedCard = sourceCards[index];
            selectedCard.classList.add('panel-card-highlight');

            panelNav.querySelectorAll('button').forEach(function (btn) {
                const isActive = Number(btn.dataset.index) === index;
                btn.classList.toggle('active', isActive);
            });

            if (activeSectionLabel) {
                const titleEl = selectedCard.querySelector('h5');
                const label = titleEl ? titleEl.innerText.trim() : 'Section ' + (index + 1);
                activeSectionLabel.textContent = 'Active: ' + label;
            }

            selectedCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            updateCounter();
        }

        function navigateVisible(direction) {
            const visibleButtons = getVisibleButtons();
            if (!visibleButtons.length) return;

            let currentVisibleIndex = visibleButtons.findIndex(function (btn) {
                return Number(btn.dataset.index) === currentIndex;
            });

            if (currentVisibleIndex < 0) {
                currentVisibleIndex = 0;
            }

            const nextVisibleIndex = (currentVisibleIndex + direction + visibleButtons.length) % visibleButtons.length;
            renderPanel(Number(visibleButtons[nextVisibleIndex].dataset.index));
        }


        if (!sourceCards.length) {
            if (sourceContainer) {
                sourceContainer.classList.remove('d-none');
                sourceCards.forEach(function (card) {
                    card.style.display = 'block';
                });
            }
            if (emptyState) {
                emptyState.classList.remove('d-none');
                emptyState.textContent = 'No sections available right now. Please refresh the page.';
            }
        }

        if (panelNav && sourceCards.length) {
            sourceCards.forEach(function (card, index) {
                const titleEl = card.querySelector('h5');
                const label = titleEl ? titleEl.innerText.trim() : 'Section ' + (index + 1);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn panel-nav-btn';
                btn.textContent = label;
                btn.dataset.index = String(index);
                btn.dataset.label = label.toLowerCase();
                btn.addEventListener('click', function () {
                    renderPanel(index);
                });
                panelNav.appendChild(btn);
            });

            if (panelSearch) {
                panelSearch.addEventListener('input', function () {
                    const query = panelSearch.value.trim().toLowerCase();
                    const buttons = Array.from(panelNav.querySelectorAll('button'));
                    let firstVisibleIndex = null;

                    buttons.forEach(function (btn) {
                        const matches = query === '' || btn.dataset.label.includes(query);
                        btn.classList.toggle('d-none', !matches);
                        if (matches && firstVisibleIndex === null) {
                            firstVisibleIndex = Number(btn.dataset.index);
                        }
                    });

                    const activeBtn = panelNav.querySelector('button.active');
                    const activeHidden = activeBtn && activeBtn.classList.contains('d-none');
                    if (firstVisibleIndex !== null && activeHidden) {
                        renderPanel(firstVisibleIndex);
                    }

                    updateNavState();
                });
            }

            const resetBtn = document.getElementById('panel-reset-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    if (!panelSearch) return;
                    panelSearch.value = '';
                    Array.from(panelNav.querySelectorAll('button')).forEach(function (btn) {
                        btn.classList.remove('d-none');
                    });
                    if (sourceCards.length) {
                        renderPanel(0);
                    }
                    updateNavState();
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    navigateVisible(-1);
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    navigateVisible(1);
                });
            }

            document.addEventListener('keydown', function (event) {
                if (!panelNav || !sourceCards.length) return;
                if (event.key === '/' && document.activeElement !== panelSearch) {
                    event.preventDefault();
                    panelSearch && panelSearch.focus();
                    return;
                }

                if (['INPUT', 'TEXTAREA', 'SELECT'].includes((document.activeElement && document.activeElement.tagName) || '')) {
                    return;
                }

                if (event.key === ']') {
                    event.preventDefault();
                    navigateVisible(1);
                } else if (event.key === '[') {
                    event.preventDefault();
                    navigateVisible(-1);
                }
            });

            renderPanel(0);
            updateNavState();
        }

        const selectAll = document.getElementById('select-all-articles');
        if (!selectAll) return;
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.article-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminDashboardPanels);
    } else {
        initAdminDashboardPanels();
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
