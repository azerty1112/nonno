<?php
/**
 * Smart Sources Handler (combines RSS and Web sources with smart detection)
 */

if (isset($_POST['add_source_smart'])) {
    $smartUrl = trim((string)($_POST['smart_source_url'] ?? ''));
    $smartUrlsBulk = trim((string)($_POST['smart_source_urls'] ?? ''));
    $smartType = trim((string)($_POST['smart_source_type'] ?? 'auto'));
    $smartNicheSlug = trim((string)getSetting('active_niche', 'general'));

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
