<?php
/**
 * Automation & Pipeline Settings Handler
 */

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
