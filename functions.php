<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

use App\Utils;

function slugify($text) {
    // wrapper for App\Utils::slugify; kept for backward compatibility
    return Utils::slugify((string)$text);
}

function e($text) {
    return Utils::e((string)$text);
}

function detectPreferredLanguage() {
    // maintain compatibility while delegating to the namespaced utility
    return Utils::detectPreferredLanguage();
}

/**
 * Niche helpers: get active niche slug (from query or default) and list available niches
 */
function getActiveNicheSlug() {
    $q = trim((string)($_GET['niche'] ?? ''));
    if ($q !== '') return $q;
    return trim((string)getSetting('active_niche', 'general'));
}

function setActiveNicheSlug($slug) {
    setSetting('active_niche', trim((string)$slug));
}

function listNiches() {
    if (!class_exists('App\\NicheManager')) return [];
    return \App\NicheManager::listNiches();
}

function seedDefaultNiches() {
    if (!class_exists('App\\NicheManager')) return;
    \App\NicheManager::seedDefaults();
}

/**
 * Get niche ID for the active niche slug. Returns 1 (general) if not found.
 */
function getActiveNicheId() {
    $slug = getActiveNicheSlug();
    if (!class_exists('App\\NicheManager')) {
        return 1;
    }

    $niche = \App\NicheManager::getNicheBySlug($slug);
    if ($niche) {
        return (int)$niche['id'];
    }

    $stmt = db_connect()->prepare("SELECT id FROM niches ORDER BY id LIMIT 1");
    $stmt->execute();
    $firstId = $stmt->fetchColumn();
    return $firstId ? (int)$firstId : 1;
}

function getActiveNicheInfo($slug = '') {
    if ($slug === '') {
        $slug = getActiveNicheSlug();
    }
    if (!class_exists('App\\NicheManager')) {
        return [
            'slug' => $slug,
            'name' => 'General',
            'description' => 'General articles and news.',
        ];
    }

    $niche = \App\NicheManager::getNicheBySlug($slug);
    if (!$niche) {
        return [
            'slug' => $slug,
            'name' => 'General',
            'description' => 'General articles and news.',
        ];
    }

    return [
        'slug' => (string)($niche['slug'] ?? ''),
        'name' => (string)($niche['name'] ?? 'General'),
        'description' => (string)($niche['description'] ?? ''),
    ];
}

function getNicheArticleMeta($slug = '') {
    $info = getActiveNicheInfo($slug);
    $slugLower = mb_strtolower((string)$info['slug'], 'UTF-8');
    $name = $info['name'] !== '' ? $info['name'] : 'General';
    $category = $name;
    $label = $name;
    $introContext = 'audience expectations, topical relevance, and niche-specific value';

    if ($slugLower === 'ev' || str_contains($name, 'Electric')) {
        $label = 'Electric Vehicles';
        $introContext = 'charging behavior, efficiency trends, and EV ownership confidence';
    } elseif ($slugLower === 'motorcycles' || str_contains($name, 'Motorcycle')) {
        $label = 'Motorcycle Reviews';
        $introContext = 'riding dynamics, ergonomics, and road confidence';
    } elseif (str_contains($slugLower, 'cuisine') || str_contains($name, 'Food') || str_contains($name, 'Cooking')) {
        $label = 'Food & Recipe Insights';
        $category = 'Cuisine';
        $introContext = 'recipe quality, flavor balance, and practical cooking tips';
    } elseif (str_contains($slugLower, 'health') || str_contains($name, 'Health')) {
        $label = 'Health & Wellness';
        $category = 'Health';
        $introContext = 'wellness benefits, practical routines, and evidence-based guidance';
    } elseif (str_contains($slugLower, 'business') || str_contains($name, 'Business')) {
        $label = 'Business Intelligence';
        $category = 'Business';
        $introContext = 'market trends, opportunity analysis, and strategic recommendations';
    }

    $customCategory = trim((string)getSetting('niche.' . $slugLower . '.category', ''));
    if ($customCategory !== '') {
        $category = $customCategory;
    }

    $customLabel = trim((string)getSetting('niche.' . $slugLower . '.label', ''));
    if ($customLabel !== '') {
        $label = $customLabel;
    }

    $customIntro = trim((string)getSetting('niche.' . $slugLower . '.intro_context', ''));
    if ($customIntro !== '') {
        $introContext = $customIntro;
    }

    return [
        'slug' => $info['slug'],
        'name' => $name,
        'description' => $info['description'],
        'label' => $label,
        'category' => $category,
        'intro_context' => $introContext,
    ];
}

function getNicheContentType(array $nicheMeta) {
    $slug = mb_strtolower(trim((string)$nicheMeta['slug']), 'UTF-8');
    $label = mb_strtolower(trim((string)$nicheMeta['label']), 'UTF-8');

    if ($slug === 'cuisine' || str_contains($label, 'food') || str_contains($label, 'recipe')) {
        return 'food';
    }
    if (str_contains($slug, 'money') || str_contains($slug, 'finance') || str_contains($label, 'finance') || str_contains($label, 'business')) {
        return 'finance';
    }
    if ($slug === 'ev' || $slug === 'motorcycles' || str_contains($label, 'vehicle') || str_contains($label, 'auto') || str_contains($label, 'car') || str_contains($label, 'motorcycle')) {
        return 'auto';
    }

    return 'general';
}

function getNicheSections($nicheType) {
    switch ($nicheType) {
        case 'food':
            return [
                'Recipe Overview and Purpose' => [
                    'dish intent, target eater, and recipe style',
                    'what makes this recipe different from common alternatives',
                    'how the finished dish should feel in terms of texture and flavor balance'
                ],
                'Ingredients and Preparation Notes' => [
                    'the quality of core ingredients and why they matter',
                    'critical technique points that determine success',
                    'time, tools, and skill level required for the recipe'
                ],
                'Flavor Profile and Serving Suggestions' => [
                    'the main taste identities and how they combine',
                    'pairing ideas that complement the dish rather than overpower it',
                    'presentation and garnish advice for better enjoyment'
                ],
                'Nutrition, Value, and Practical Use' => [
                    'how the recipe fits into daily meal planning or special occasions',
                    'value for money considering pantry ingredients and preparation effort',
                    'what readers should expect in terms of leftovers, reheating, or storage'
                ],
                'Final Verdict and Reader Recommendation' => [
                    'who benefits most from making this dish',
                    'what to watch for when choosing variations or substitutions',
                    'why this recipe earns a place in the reader’s regular cooking rotation'
                ],
            ];
        case 'finance':
            return [
                'What This Topic Delivers' => [
                    'the practical business or financial outcome it targets',
                    'how it differs from common alternatives or simpler approaches',
                    'the type of reader who benefits most from it'
                ],
                'Cost, Risk, and Reward' => [
                    'the main risks to watch for before committing',
                    'the reward profile in terms of savings, returns, or efficiency',
                    'how to compare it with other viable options'
                ],
                'Implementation and Usage Guidance' => [
                    'the steps needed to put this concept into practice',
                    'common mistakes and how to avoid them',
                    'where this approach fits into broader financial planning'
                ],
                'Audience Scenarios' => [
                    'which reader segments should prioritize it',
                    'how the recommendation changes depending on goals',
                    'the key questions to ask before choosing it'
                ],
                'Final Verdict and Strategic Recommendation' => [
                    'whether this topic is worth action now',
                    'the main conditions under which it makes the most sense',
                    'what a smart reader should do next'
                ],
            ];
        default:
            return [
                'Executive Summary and Market Position' => [
                    'segment fit and target audience clarity',
                    'how the model differentiates against direct rivals',
                    'the real value story behind headline marketing claims'
                ],
                'Exterior Design, Proportion, and Visual Character' => [
                    'surface treatment, stance, and brand identity execution',
                    'aerodynamic decisions that influence both style and efficiency',
                    'why design coherence affects owner satisfaction over time'
                ],
                'Cabin Quality, Space, and Human-Centered Ergonomics' => [
                    'seat comfort, posture support, and long-distance usability',
                    'dashboard hierarchy, physical controls, and interaction clarity',
                    'perceived quality through materials, fit, and acoustic control'
                ],
                'Powertrain Intelligence, Performance Delivery, and Efficiency' => [
                    'response quality under partial and full throttle situations',
                    'efficiency behavior in urban, mixed, and highway duty cycles',
                    'engineering trade-offs between excitement and sustainability'
                ],
                'Ride Comfort, Handling Balance, and Braking Confidence' => [
                    'suspension tuning over varied road surfaces',
                    'steering communication and directional stability at speed',
                    'predictable braking behavior in repeated real-world use'
                ],
                'Technology Stack, Infotainment, and Connectivity Experience' => [
                    'interface speed, readability, and cognitive simplicity',
                    'smartphone integration and navigation reliability in practice',
                    'software maturity, update path, and feature longevity'
                ],
                'Safety Systems, Driver Assistance, and Durability Outlook' => [
                    'calibration quality of active safety interventions',
                    'passive safety confidence and structural reassurance',
                    'maintenance predictability and long-term reliability perception'
                ],
                'Ownership Economics, Trim Strategy, and Buyer Recommendations' => [
                    'cost of ownership across fuel or charging, service, and insurance',
                    'which configuration levels provide the strongest value density',
                    'how to shortlist based on real priorities rather than hype'
                ],
            ];
    }
}

/**
 * Get RSS sources for a specific niche (or active niche if not specified)
 */
function getNicheSources($nicheSlug = '', $type = '') {
    if ($nicheSlug === '') {
        $nicheSlug = getActiveNicheSlug();
    }
    if (!class_exists('App\\NicheManager')) {
        return [];
    }

    $niche = \App\NicheManager::getNicheBySlug($nicheSlug);
    if (!$niche) {
        return [];
    }

    $sources = \App\NicheManager::getSourcesForNiche((int)$niche['id'], $type);
    return array_column($sources, 'url');
}

function getNicheRssSources($nicheSlug = '') {
    return getNicheSources($nicheSlug, 'rss');
}

/**
 * Get web sources for a specific niche (or active niche if not specified)
 */
function getNicheWebSources($nicheSlug = '') {
    return getNicheSources($nicheSlug, 'web');
}

/**
 * Tags/Keywords system helpers
 */
function getTags() {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT id, name, slug, description, post_count FROM tags ORDER BY post_count DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function addTagToArticle($articleId, $tagName) {
    $pdo = db_connect();
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($tagName));
    
    // Ensure tag exists
    $pdo->prepare("INSERT OR IGNORE INTO tags (name, slug, description) VALUES (?, ?, ?)")
        ->execute([$tagName, $slug, '']);
    
    $tagStmt = $pdo->prepare("SELECT id FROM tags WHERE slug = ? LIMIT 1");
    $tagStmt->execute([$slug]);
    $tagId = (int)$tagStmt->fetchColumn();
    
    if ($tagId > 0) {
        $pdo->prepare("INSERT OR IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)")
            ->execute([(int)$articleId, $tagId]);
            
        $pdo->prepare("UPDATE tags SET post_count = post_count + 1 WHERE id = ? AND post_count = (SELECT COUNT(*) - 1 FROM article_tags WHERE tag_id = ?)")
            ->execute([$tagId, $tagId]);
    }
}

function getArticleTags($articleId) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT t.id, t.name, t.slug FROM tags t JOIN article_tags at ON t.id = at.tag_id WHERE at.article_id = ? ORDER BY t.name");
    $stmt->execute([(int)$articleId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getArticlesForTag($tagSlug, $limit = 12) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.slug, a.excerpt, a.image, a.image2, a.translated_title, a.published_at 
        FROM articles a
        JOIN article_tags at ON a.id = at.article_id
        JOIN tags t ON at.tag_id = t.id
        WHERE t.slug = ?
        ORDER BY a.published_at DESC
        LIMIT ?
    ");
    $stmt->execute([$tagSlug, (int)$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Article stats and ratings
 */
function initArticleStats($articleId) {
    $pdo = db_connect();
    $pdo->prepare("INSERT OR IGNORE INTO article_stats (article_id, updated_at) VALUES (?, ?)")
        ->execute([(int)$articleId, time()]);
}

function generateAutoTags($title, $content = '') {
    $text = mb_strtolower($title . ' ' . strip_tags($content), 'UTF-8');
    // simple stop words list
    $stop = ['the','and','for','with','this','that','from','your','have','will','2026','best','new','review'];
    preg_match_all('/\b[a-z0-9]{4,}\b/', $text, $matches);
    $counts = array_count_values($matches[0]);
    arsort($counts);
    $tags = [];
    foreach ($counts as $word => $cnt) {
        if (in_array($word, $stop, true)) continue;
        $tags[] = ucfirst($word);
        if (count($tags) >= 5) break;
    }
    return $tags;
}

function translateText($text, $targetLang = 'ar') {
    $text = trim((string)$text);
    $targetLang = trim((string)$targetLang);
    if ($text === '' || $targetLang === '') {
        return '';
    }
    $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($text) . '&langpair=en|' . rawurlencode($targetLang);
    $resp = @file_get_contents($url);
    if (!$resp) {
        return '';
    }
    $data = json_decode($resp, true);
    return $data['responseData']['translatedText'] ?? '';
}

function recordArticleView($articleId) {
    $pdo = db_connect();
    $pdo->prepare("UPDATE article_stats SET views = views + 1, updated_at = ? WHERE article_id = ?")
        ->execute([time(), (int)$articleId]);
}

function rateArticle($articleId, $rating, $visitorHash = '') {
    if ($rating < 1 || $rating > 5) return false;
    
    $pdo = db_connect();
    $visitorHash = $visitorHash ?: getVisitorFingerprint();
    
    $pdo->prepare("INSERT OR REPLACE INTO article_ratings (article_id, rating, visitor_hash, created_at) VALUES (?, ?, ?, ?)")
        ->execute([(int)$articleId, (int)$rating, $visitorHash, time()]);
    
    // Update average rating
    $avgStmt = $pdo->prepare("SELECT AVG(rating) as avg, COUNT(*) as count FROM article_ratings WHERE article_id = ?");
    $avgStmt->execute([(int)$articleId]);
    $row = $avgStmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->prepare("UPDATE article_stats SET avg_rating = ?, rating_count = ?, updated_at = ? WHERE article_id = ?")
        ->execute([round($row['avg'], 1), (int)$row['count'], time(), (int)$articleId]);
    
    return true;
}

function getArticleStats($articleId) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT views, clicks, avg_rating, rating_count, shares FROM article_stats WHERE article_id = ? LIMIT 1");
    $stmt->execute([(int)$articleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: [
        'views' => 0,
        'clicks' => 0,
        'avg_rating' => 0,
        'rating_count' => 0,
        'shares' => 0,
    ];
}

function getTrendingArticles($limit = 10) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.slug, a.excerpt, a.image, a.published_at,
               a.image2, a.translated_title,
               s.views, s.avg_rating, s.rating_count
        FROM articles a
        LEFT JOIN article_stats s ON a.id = s.article_id
        ORDER BY COALESCE(s.views, 0) DESC
        LIMIT ?
    ");
    $stmt->execute([(int)$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getTopRatedArticles($limit = 10) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.slug, a.excerpt, a.image, a.published_at,
               s.views, s.avg_rating, s.rating_count
        FROM articles a
        LEFT JOIN article_stats s ON a.id = s.article_id
        WHERE s.avg_rating > 0 AND s.rating_count > 0
        ORDER BY s.avg_rating DESC, s.rating_count DESC
        LIMIT ?
    ");
    $stmt->execute([(int)$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}



function getSiteTitle() {
    $configuredTitle = trim((string)getSetting('site_title', SITE_TITLE));
    return $configuredTitle !== '' ? $configuredTitle : SITE_TITLE;
}

function getSiteBaseUrl() {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $scheme = ($https === 'on' || $https === '1' || $forwardedProto === 'https') ? 'https' : 'http';

    return $scheme . '://' . $host;
}

function getVisitorFingerprint() {
    $ip = getVisitorIpAddress();
    if ($ip === '') {
        $ip = 'unknown-ip';
    }

    return hash('sha256', $ip);
}

function getVisitorIpAddress() {
    $forwardedFor = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwardedFor !== '') {
        $parts = array_map('trim', explode(',', $forwardedFor));
        foreach ($parts as $candidate) {
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }

    $remoteAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddress !== '' && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
        return $remoteAddress;
    }

    return $remoteAddress;
}

function ipMatchesRule($ipAddress, $rule) {
    $ipAddress = trim((string)$ipAddress);
    $rule = trim((string)$rule);
    if ($ipAddress === '' || $rule === '') {
        return false;
    }

    if (strpos($rule, '/') !== false) {
        [$subnet, $maskBitsRaw] = array_pad(explode('/', $rule, 2), 2, '');
        $subnet = trim($subnet);
        $maskBits = (int)$maskBitsRaw;

        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($maskBits < 0 || $maskBits > 32) {
                return false;
            }
            $subnetLong = ip2long($subnet);
            $ipLong = ip2long($ipAddress);
            if ($subnetLong === false || $ipLong === false) {
                return false;
            }
            $mask = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));
            return (($ipLong & $mask) === ($subnetLong & $mask));
        }

        return false;
    }

    return $ipAddress === $rule;
}

function normalizeExcludedIpRules($rawValue) {
    $rawValue = trim((string)$rawValue);
    if ($rawValue === '') {
        return '';
    }

    $tokens = preg_split('/[\s,]+/', $rawValue, -1, PREG_SPLIT_NO_EMPTY);
    $rules = [];
    foreach ($tokens as $token) {
        $rule = trim((string)$token);
        if ($rule === '') {
            continue;
        }

        if (strpos($rule, '/') !== false) {
            [$subnet, $maskBitsRaw] = array_pad(explode('/', $rule, 2), 2, '');
            $subnet = trim($subnet);
            $maskBits = (int)$maskBitsRaw;
            if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $maskBits >= 0 && $maskBits <= 32) {
                $rules[] = $subnet . '/' . $maskBits;
            }
            continue;
        }

        if (filter_var($rule, FILTER_VALIDATE_IP)) {
            $rules[] = $rule;
        }
    }

    $rules = array_values(array_unique($rules));
    return implode("\n", $rules);
}

function shouldIgnoreVisitForIp($ipAddress) {
    $ipAddress = trim((string)$ipAddress);
    if ($ipAddress === '') {
        return false;
    }

    $excludedRaw = normalizeExcludedIpRules((string)getSetting('visit_excluded_ips', ''));
    if ($excludedRaw === '') {
        return false;
    }

    $rules = preg_split('/[\s,]+/', $excludedRaw, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($rules as $rule) {
        if (ipMatchesRule($ipAddress, $rule)) {
            return true;
        }
    }

    return false;
}

function recordPageVisit($pageKey, $pageLabel) {
    $pageKey = trim((string)$pageKey);
    $pageLabel = trim((string)$pageLabel);
    if ($pageKey === '' || $pageLabel === '') {
        return;
    }

    $pdo = db_connect();
    $ipAddress = getVisitorIpAddress();
    if (shouldIgnoreVisitForIp($ipAddress)) {
        return;
    }

    $visitorHash = getVisitorFingerprint();
    $now = time();

    $stmt = $pdo->prepare("INSERT INTO page_visits (page_key, page_label, visitor_hash, views, created_at, updated_at)
        VALUES (:page_key, :page_label, :visitor_hash, 1, :created_at, :updated_at)
        ON CONFLICT(page_key, visitor_hash) DO UPDATE SET
            views = views + CASE
                WHEN strftime('%Y-%m-%d', page_visits.updated_at, 'unixepoch') = strftime('%Y-%m-%d', excluded.updated_at, 'unixepoch') THEN 0
                ELSE 1
            END,
            page_label = excluded.page_label,
            updated_at = excluded.updated_at");

    $stmt->execute([
        'page_key' => $pageKey,
        'page_label' => $pageLabel,
        'visitor_hash' => $visitorHash,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function getPageVisitStats($limit = 8, $search = '') {
    $limit = max(1, min(30, (int)$limit));
    $pdo = db_connect();
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT
            page_key,
            MIN(page_label) AS page_label,
            SUM(views) AS total_views,
            COUNT(*) AS unique_visitors,
            SUM(CASE WHEN updated_at >= :last_24h THEN 1 ELSE 0 END) AS visitors_24h,
            MAX(updated_at) AS last_visit_at
        FROM page_visits
        WHERE page_label LIKE :search OR page_key LIKE :search
        GROUP BY page_key
        ORDER BY total_views DESC
        LIMIT :limit");
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare("SELECT
            page_key,
            MIN(page_label) AS page_label,
            SUM(views) AS total_views,
            COUNT(*) AS unique_visitors,
            SUM(CASE WHEN updated_at >= :last_24h THEN 1 ELSE 0 END) AS visitors_24h,
            MAX(updated_at) AS last_visit_at
        FROM page_visits
        GROUP BY page_key
        ORDER BY total_views DESC
        LIMIT :limit");
    }
    $stmt->bindValue(':last_24h', time() - 86400, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchUrlBody($url, $timeoutSeconds = null) {
    $url = trim((string)$url);
    if ($url === '') {
        return null;
    }

    $cacheHit = getCachedUrlBody($url);
    if ($cacheHit !== null) {
        return $cacheHit;
    }

    $timeoutSeconds = $timeoutSeconds === null
        ? getSettingInt('fetch_timeout_seconds', 12, 3, 45)
        : max(1, (int)$timeoutSeconds);

    $retryAttempts = getSettingInt('fetch_retry_attempts', 3, 1, 5);
    $backoffMs = getSettingInt('fetch_retry_backoff_ms', 350, 100, 3000);

    for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'user_agent' => getFetcherUserAgent(),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $statusCode = extractHttpStatusCode($http_response_header ?? []);
        $ok = is_string($body) && $body !== '' && $statusCode >= 200 && $statusCode < 400;

        if ($ok) {
            cacheUrlBody($url, $body, $statusCode, true);
            return $body;
        }

        if ($attempt < $retryAttempts) {
            $jitterMs = random_int(0, 120);
            usleep(($backoffMs * $attempt + $jitterMs) * 1000);
        }
    }

    cacheUrlBody($url, is_string($body) ? $body : '', $statusCode, false);
    return null;
}

function getFetcherUserAgent() {
    $ua = trim((string)getSetting('fetch_user_agent', 'Mozilla/5.0 (compatible; VitoBot/1.0; +https://example.com/bot)'));
    return $ua !== '' ? $ua : 'Mozilla/5.0 (compatible; VitoBot/1.0; +https://example.com/bot)';
}

function extractHttpStatusCode(array $headers) {
    foreach ($headers as $headerLine) {
        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', (string)$headerLine, $matches)) {
            return (int)$matches[1];
        }
    }
    return 0;
}

function getUrlCacheTtlSeconds() {
    return getSettingInt('url_cache_ttl_seconds', 900, 60, 86400);
}

function getCachedUrlBody($url) {
    $pdo = db_connect();
    $stmt = $pdo->prepare('SELECT body, fetched_at, ttl_seconds, blocked_until FROM url_cache WHERE url = ? LIMIT 1');
    $stmt->execute([$url]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $now = time();
    if ((int)$row['blocked_until'] > $now) {
        return null;
    }

    $ttl = max(60, (int)$row['ttl_seconds']);
    if (((int)$row['fetched_at'] + $ttl) < $now) {
        return null;
    }

    $body = (string)($row['body'] ?? '');
    return $body !== '' ? $body : null;
}

function cacheUrlBody($url, $body, $statusCode, $success) {
    $pdo = db_connect();
    $ttl = getUrlCacheTtlSeconds();
    $now = time();
    $statusCode = (int)$statusCode;

    $existingStmt = $pdo->prepare('SELECT fail_count FROM url_cache WHERE url = ? LIMIT 1');
    $existingStmt->execute([$url]);
    $existingFail = (int)$existingStmt->fetchColumn();

    $failCount = $success ? 0 : ($existingFail + 1);
    $blockedUntil = 0;
    if (!$success && ($statusCode === 429 || $statusCode === 403 || $failCount >= 3)) {
        $blockedUntil = $now + min(1800, 60 * $failCount);
    }

    $stmt = $pdo->prepare("INSERT INTO url_cache (url, body, status_code, fetched_at, ttl_seconds, fail_count, blocked_until)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(url) DO UPDATE SET
            body = excluded.body,
            status_code = excluded.status_code,
            fetched_at = excluded.fetched_at,
            ttl_seconds = excluded.ttl_seconds,
            fail_count = excluded.fail_count,
            blocked_until = excluded.blocked_until");

    $stmt->execute([$url, (string)$body, $statusCode, $now, $ttl, $failCount, $blockedUntil]);
}

function extractFeedTitlesWithSymfonyCrawler($xmlString, $limit = 50) {
    if (!class_exists('Symfony\\Component\\DomCrawler\\Crawler')) {
        return [];
    }

    $limit = max(1, (int)$limit);
    $crawler = new Symfony\Component\DomCrawler\Crawler();

    try {
        $crawler->addXmlContent($xmlString, 'UTF-8');
    } catch (Throwable $e) {
        return [];
    }

    $selectors = [
        'channel > item > title',
        'feed > entry > title',
        'rdf\:RDF > item > title',
        'item > title',
        'entry > title',
    ];

    $titles = [];
    foreach ($selectors as $selector) {
        $nodes = $crawler->filter($selector);
        if ($nodes->count() === 0) {
            continue;
        }

        foreach ($nodes as $node) {
            $title = trim((string)$node->textContent);
            if ($title !== '') {
                $titles[] = $title;
            }
            if (count($titles) >= $limit) {
                break 2;
            }
        }
    }

    return mergeAndDeduplicateTitles($titles);
}

function extractFeedTitlesWithSimpleXml($xmlString, $limit = 50) {
    $limit = max(1, (int)$limit);
    $xml = @simplexml_load_string($xmlString);
    if (!$xml) {
        return [];
    }

    $titles = [];

    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $title = trim((string)$item->title);
            if ($title !== '') {
                $titles[] = $title;
            }
            if (count($titles) >= $limit) {
                break;
            }
        }
    }

    if (count($titles) < $limit && isset($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $title = trim((string)$entry->title);
            if ($title !== '') {
                $titles[] = $title;
            }
            if (count($titles) >= $limit) {
                break;
            }
        }
    }

    return mergeAndDeduplicateTitles($titles);
}

function extractFeedTitles($url, $limit = 50) {
    $xmlString = fetchUrlBody($url);
    if (!is_string($xmlString) || trim($xmlString) === '') {
        return [];
    }

    $titles = extractFeedTitlesWithSymfonyCrawler($xmlString, $limit);
    if ($titles) {
        return $titles;
    }

    return extractFeedTitlesWithSimpleXml($xmlString, $limit);
}


function extractTitlesFromNormalPageWithSymfonyCrawler($htmlString, $limit = 50) {
    if (!class_exists('Symfony\Component\DomCrawler\Crawler')) {
        return [];
    }

    $limit = max(1, (int)$limit);
    $crawler = new Symfony\Component\DomCrawler\Crawler();

    try {
        $crawler->addHtmlContent($htmlString, 'UTF-8');
    } catch (Throwable $e) {
        return [];
    }

    $selectors = [
        'article h1 a, article h2 a, article h3 a',
        'main h1 a, main h2 a, main h3 a',
        '.post-title a, .entry-title a',
        'h1 a, h2 a, h3 a',
    ];

    $titles = [];
    foreach ($selectors as $selector) {
        $nodes = $crawler->filter($selector);
        if ($nodes->count() === 0) {
            continue;
        }

        foreach ($nodes as $node) {
            $title = trim((string)$node->textContent);
            if ($title !== '' && mb_strlen($title) >= 5) {
                $titles[] = $title;
            }
            if (count($titles) >= $limit) {
                break 2;
            }
        }
    }

    if (!$titles) {
        foreach ($crawler->filter('title') as $titleNode) {
            $title = trim((string)$titleNode->textContent);
            if ($title !== '' && mb_strlen($title) >= 5) {
                $titles[] = $title;
            }
            if (count($titles) >= $limit) {
                break;
            }
        }
    }

    return mergeAndDeduplicateTitles($titles);
}

function extractTitlesFromNormalPage($url, $limit = 50) {
    $htmlString = fetchUrlBody($url);
    if (!is_string($htmlString) || trim($htmlString) === '') {
        return [];
    }

    return extractTitlesFromNormalPageWithSymfonyCrawler($htmlString, $limit);
}

function getSelectedContentWorkflow() {
    $allowed = ['rss', 'web'];
    $workflow = trim((string)getSetting('content_workflow', 'rss'));
    return in_array($workflow, $allowed, true) ? $workflow : 'rss';
}

function enqueueWorkflowSources($workflow, array $sources) {
    $workflow = $workflow === 'web' ? 'web' : 'rss';
    $pdo = db_connect();
    $now = time();

    $existsStmt = $pdo->prepare('SELECT id FROM scrape_queue WHERE workflow = ? AND source_url = ? AND status IN ("pending","processing") LIMIT 1');
    $insertStmt = $pdo->prepare('INSERT INTO scrape_queue (workflow, source_url, status, attempts, locked_until, available_at, created_at, updated_at) VALUES (?, ?, "pending", 0, 0, ?, ?, ?)');

    foreach ($sources as $sourceUrl) {
        $sourceUrl = trim((string)$sourceUrl);
        if ($sourceUrl === '') {
            continue;
        }

        $existsStmt->execute([$workflow, $sourceUrl]);
        if ($existsStmt->fetchColumn() !== false) {
            continue;
        }

        $insertStmt->execute([$workflow, $sourceUrl, $now, $now, $now]);
    }
}

function pullWorkflowQueueItems($workflow, $limit) {
    $workflow = $workflow === 'web' ? 'web' : 'rss';
    $limit = max(1, (int)$limit);
    $pdo = db_connect();
    $now = time();
    $sourceCooldown = getSettingInt('queue_source_cooldown_seconds', 180, 30, 7200);

    resetStaleQueueLocks($workflow);

    $sql = 'SELECT id, source_url
        FROM scrape_queue
        WHERE workflow = ?
          AND status = "pending"
          AND available_at <= ?
          AND locked_until <= ?
          AND source_url NOT IN (
              SELECT source_url
              FROM scrape_queue
              WHERE workflow = ?
                AND status IN ("done", "processing")
                AND updated_at >= ?
          )
        ORDER BY id ASC
        LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$workflow, $now, $now, $workflow, $now - $sourceCooldown]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lockUntil = $now + 120;
    $lockStmt = $pdo->prepare('UPDATE scrape_queue SET status = "processing", locked_until = ?, updated_at = ? WHERE id = ?');
    foreach ($items as $item) {
        $lockStmt->execute([$lockUntil, $now, (int)$item['id']]);
    }

    return $items;
}

function resetStaleQueueLocks($workflow) {
    $workflow = $workflow === 'web' ? 'web' : 'rss';
    $pdo = db_connect();
    $now = time();

    $stmt = $pdo->prepare('UPDATE scrape_queue
        SET status = "pending", locked_until = 0, updated_at = ?
        WHERE workflow = ? AND status = "processing" AND locked_until > 0 AND locked_until <= ?');
    $stmt->execute([$now, $workflow, $now]);
}

function markQueueItemDone($id) {
    $pdo = db_connect();
    $stmt = $pdo->prepare('UPDATE scrape_queue SET status = "done", locked_until = 0, updated_at = ? WHERE id = ?');
    $stmt->execute([time(), (int)$id]);
}

function markQueueItemForRetry($id) {
    $pdo = db_connect();
    $retryDelay = getSettingInt('queue_retry_delay_seconds', 60, 10, 3600);
    $maxAttempts = getSettingInt('queue_max_attempts', 3, 1, 10);
    $now = time();

    $stmt = $pdo->prepare('SELECT attempts FROM scrape_queue WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $attempts = (int)$stmt->fetchColumn() + 1;

    if ($attempts >= $maxAttempts) {
        $failStmt = $pdo->prepare('UPDATE scrape_queue SET status = "failed", attempts = ?, locked_until = 0, updated_at = ? WHERE id = ?');
        $failStmt->execute([$attempts, $now, (int)$id]);
        return;
    }

    $retryAt = $now + ($retryDelay * $attempts);
    $retryStmt = $pdo->prepare('UPDATE scrape_queue SET status = "pending", attempts = ?, available_at = ?, locked_until = 0, updated_at = ? WHERE id = ?');
    $retryStmt->execute([$attempts, $retryAt, $now, (int)$id]);
}

function cleanAndNormalizeTitle($title) {
    $title = html_entity_decode((string)$title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = preg_replace('/\s+/u', ' ', $title);
    $title = preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$title);
    $title = trim((string)$title);
    if (mb_strlen($title) < 5) {
        return '';
    }
    return $title;
}

function mergeAndDeduplicateTitles(array ...$collections) {
    $merged = [];
    foreach ($collections as $titles) {
        foreach ($titles as $title) {
            $clean = cleanAndNormalizeTitle($title);
            if ($clean === '') {
                continue;
            }
            $key = normalizeTextForComparison($clean);
            if (!isset($merged[$key])) {
                $merged[$key] = $clean;
            }
        }
    }
    return array_values($merged);
}

function runRssWorkflow($limit = null) {
    $pdo = db_connect();
    $nicheId = getActiveNicheId();
    $activeNiche = getActiveNicheSlug();

    $stmt = $pdo->prepare("SELECT url FROM niche_sources WHERE niche_id = ? AND type = 'rss' ORDER BY id DESC");
    $stmt->execute([$nicheId]);
    $sources = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (empty($sources)) {
        $sources = getNicheRssSources($activeNiche) ?: [];
    }

    $limit = $limit === null ? max(1, (int)getSetting('daily_limit', 5)) : max(1, (int)$limit);
    $batchSize = getSettingInt('workflow_batch_size', 8, 1, 30);

    enqueueWorkflowSources('rss', $sources);
    $queueItems = pullWorkflowQueueItems('rss', $batchSize);

    $count = 0;
    $sourceStats = [];

    foreach ($queueItems as $queueItem) {
        $url = (string)$queueItem['source_url'];
        if ($count >= $limit) {
            break;
        }

        $titles = extractFeedTitles($url, max(10, $limit * 3));
        if (!$titles) {
            markQueueItemForRetry((int)$queueItem['id']);
            continue;
        }

        $publishedFromSource = 0;

        foreach ($titles as $title) {
            if ($count >= $limit) {
                break;
            }

            if ($title !== '' && !articleExists($title)) {
                $data = generateArticle($title);
                if (saveArticle($title, $data)) {
                    $count++;
                    $publishedFromSource++;
                }
            }
        }

        $sourceStats[] = [
            'url' => $url,
            'fetched_titles' => count($titles),
            'published' => $publishedFromSource,
        ];

        markQueueItemDone((int)$queueItem['id']);
    }

    return [
        'workflow' => 'rss',
        'sources_count' => count($sources),
        'queue_batch' => count($queueItems),
        'published' => $count,
        'stats' => $sourceStats,
    ];
}

function runWebWorkflow($limit = null) {
    $activeNiche = getActiveNicheSlug();
    $sources = getNicheWebSources($activeNiche) ?: [];
    $limit = $limit === null ? max(1, (int)getSetting('daily_limit', 5)) : max(1, (int)$limit);
    $batchSize = getSettingInt('workflow_batch_size', 8, 1, 30);

    enqueueWorkflowSources('web', $sources);
    $queueItems = pullWorkflowQueueItems('web', $batchSize);

    $count = 0;
    $sourceStats = [];

    foreach ($queueItems as $queueItem) {
        $url = (string)$queueItem['source_url'];
        if ($count >= $limit) {
            break;
        }

        $titles = extractTitlesFromNormalPage($url, max(10, $limit * 3));
        if (!$titles) {
            markQueueItemForRetry((int)$queueItem['id']);
            continue;
        }

        $publishedFromSource = 0;

        foreach ($titles as $title) {
            if ($count >= $limit) {
                break;
            }

            if ($title !== '' && !articleExists($title)) {
                $data = generateArticle($title);
                if (saveArticle($title, $data)) {
                    $count++;
                    $publishedFromSource++;
                }
            }
        }

        $sourceStats[] = [
            'url' => $url,
            'fetched_titles' => count($titles),
            'published' => $publishedFromSource,
        ];

        markQueueItemDone((int)$queueItem['id']);
    }

    return [
        'workflow' => 'web',
        'sources_count' => count($sources),
        'queue_batch' => count($queueItems),
        'published' => $count,
        'stats' => $sourceStats,
    ];
}

function runSelectedContentWorkflow($limit = null) {
    $selected = getSelectedContentWorkflow();
    if ($selected === 'web') {
        return runWebWorkflow($limit);
    }

    return runRssWorkflow($limit);
}

function getContentWorkflowSummary() {
    $pdo = db_connect();
    $selected = getSelectedContentWorkflow();
    $rssSourcesStmt = $pdo->prepare("SELECT COUNT(*) FROM rss_sources");
    $rssSourcesStmt->execute();
    $rssSources = (int)$rssSourcesStmt->fetchColumn();
    $webSourcesStmt = $pdo->prepare("SELECT COUNT(*) FROM web_sources");
    $webSourcesStmt->execute();
    $webSources = (int)$webSourcesStmt->fetchColumn();
    $dailyLimit = getSettingInt('daily_limit', 5, 1, 200);

    $selectedSources = $selected === 'web' ? $webSources : $rssSources;
    $scheduler = getAutoPublishSchedulerMeta();

    $health = 'ready';
    if ($selectedSources <= 0) {
        $health = 'missing_sources';
    } elseif (getSettingInt('auto_ai_enabled', 1, 0, 1) === 0) {
        $health = 'scheduler_disabled';
    }

    return [
        'selected_workflow' => $selected,
        'selected_workflow_label' => $selected === 'web' ? 'Normal Sites Workflow' : 'RSS Workflow',
        'rss_sources' => $rssSources,
        'web_sources' => $webSources,
        'selected_sources' => $selectedSources,
        'daily_limit' => $dailyLimit,
        'auto_ai_enabled' => getSettingInt('auto_ai_enabled', 1, 0, 1) === 1,
        'scheduler' => $scheduler,
        'health' => $health,
    ];
}


function normalizeDateInput($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return '';
    }

    return $value;
}


function getSettingInt($key, $default = 0, $min = null, $max = null) {
    $value = (int)getSetting($key, (string)$default);
    if ($min !== null) {
        $value = max((int)$min, $value);
    }
    if ($max !== null) {
        $value = min((int)$max, $value);
    }
    return $value;
}

function getAdSettings() {
    $mode = trim((string)getSetting('ads_injection_mode', 'smart'));
    if (!in_array($mode, ['smart', 'interval'], true)) {
        $mode = 'smart';
    }

    $label = trim((string)getSetting('ads_label_text', 'Sponsored'));
    if ($label === '') {
        $label = 'Sponsored';
    }

    return [
        'enabled' => getSettingInt('ads_enabled', 0, 0, 1) === 1,
        'mode' => $mode,
        'paragraph_interval' => getSettingInt('ads_paragraph_interval', 4, 2, 10),
        'max_units' => getSettingInt('ads_max_units_per_article', 2, 1, 6),
        'min_words_before_first' => getSettingInt('ads_min_words_before_first_injection', 260, 120, 900),
        'min_article_words' => getSettingInt('ads_min_article_words', 420, 120, 3000),
        'blocked_title_keywords' => trim((string)getSetting('ads_blocked_title_keywords', '')),
        'blocked_categories' => trim((string)getSetting('ads_blocked_categories', '')),
        'label' => mb_substr($label, 0, 40),
        'html_code' => trim((string)getSetting('ads_html_code', '<div class="ad-unit-inner">Place your ad code here</div>')),
    ];
}

function parseAdListTokens($value) {
    $raw = preg_split('/[,\n]+/u', (string)$value);
    $keywords = [];
    foreach ($raw as $item) {
        $token = mb_strtolower(trim((string)$item));
        if ($token === '' || mb_strlen($token) < 2) {
            continue;
        }
        $keywords[$token] = true;
    }

    return array_keys($keywords);
}

function parseAdBlockedTitleKeywords($value) {
    return parseAdListTokens($value);
}

function countVisibleWords($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return 0;
    }

    if (preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches)) {
        return count($matches[0]);
    }

    return str_word_count($text);
}

function buildInlineAdUnitHtml(array $adSettings, $position = 1) {
    $safePosition = max(1, (int)$position);
    $label = e($adSettings['label'] ?? 'Sponsored');
    $adCode = (string)($adSettings['html_code'] ?? '');
    if ($adCode === '') {
        $adCode = '<div class="ad-unit-inner">Place your ad code here</div>';
    }

    return '<aside class="inline-ad-unit" data-ad-slot="' . $safePosition . '">'
        . '<div class="inline-ad-label">' . $label . '</div>'
        . $adCode
        . '</aside>';
}


function parseSeoAutoLinkRules($rawRules) {
    $rules = [];
    $lines = preg_split('/\r\n|\r|\n/', (string)$rawRules);
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 1) {
            continue;
        }

        $keyword = trim((string)($parts[0] ?? ''));
        $destination = trim((string)($parts[1] ?? 'internal'));
        if ($keyword === '' || $destination === '') {
            continue;
        }

        $rules[] = [
            'keyword' => $keyword,
            'destination' => $destination,
            'new_tab' => isset($parts[2]) ? in_array(strtolower($parts[2]), ['1', 'yes', 'true', 'newtab', 'blank'], true) : false,
            'nofollow' => isset($parts[3]) ? in_array(strtolower($parts[3]), ['1', 'yes', 'true', 'nofollow'], true) : false,
        ];
    }

    return $rules;
}

function resolveSeoAutoLinkDestination(array $rule, $currentArticleId = 0) {
    $destination = trim((string)($rule['destination'] ?? ''));
    if ($destination === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $destination)) {
        return [
            'url' => $destination,
            'external' => true,
        ];
    }

    if (str_starts_with($destination, 'slug:')) {
        $slug = slugify(substr($destination, 5));
        if ($slug === '') {
            return null;
        }

        return [
            'url' => 'index.php?slug=' . rawurlencode($slug),
            'external' => false,
        ];
    }

    if (strtolower($destination) === 'internal') {
        $pdo = db_connect();
        $stmt = $pdo->prepare("SELECT slug FROM articles WHERE id != ? AND title LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int)$currentArticleId, '%' . $rule['keyword'] . '%']);
        $slug = (string)$stmt->fetchColumn();
        if ($slug === '') {
            return null;
        }

        return [
            'url' => 'index.php?slug=' . rawurlencode($slug),
            'external' => false,
        ];
    }

    if (str_starts_with($destination, '/')) {
        return [
            'url' => $destination,
            'external' => false,
        ];
    }

    return null;
}


function buildAutomaticInternalLinkRules($currentArticleId = 0, $limit = 3) {
    $limit = max(1, min(10, (int)$limit));
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT title, slug FROM articles WHERE id != ? ORDER BY id DESC LIMIT 30");
    $stmt->execute([(int)$currentArticleId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rules = [];
    foreach ($rows as $row) {
        $title = trim((string)($row['title'] ?? ''));
        $slug = trim((string)($row['slug'] ?? ''));
        if ($title === '' || $slug === '') {
            continue;
        }

        $keyword = $title;
        if (mb_strlen($keyword) > 80) {
            $keyword = mb_substr($keyword, 0, 80);
        }

        $rules[] = [
            'keyword' => $keyword,
            'destination' => 'slug:' . $slug,
            'new_tab' => false,
            'nofollow' => false,
        ];

        if (count($rules) >= $limit) {
            break;
        }
    }

    return $rules;
}

function injectSeoAutoLinks($html, $currentArticleId = 0) {
    $content = trim((string)$html);
    if ($content === '') {
        return $html;
    }

    $rules = parseSeoAutoLinkRules(getSetting('seo_auto_link_rules', ''));

    $autoInternalEnabled = getSettingInt('seo_auto_link_auto_internal', 1, 0, 1) === 1;
    $autoInternalLimit = getSettingInt('seo_auto_link_max_per_article', 3, 1, 10);
    if ($autoInternalEnabled) {
        $rules = array_merge($rules, buildAutomaticInternalLinkRules($currentArticleId, $autoInternalLimit));
    }

    if (!$rules) {
        return $html;
    }

    $resolved = [];
    foreach ($rules as $rule) {
        $target = resolveSeoAutoLinkDestination($rule, $currentArticleId);
        if (!$target || trim((string)($target['url'] ?? '')) === '') {
            continue;
        }
        $rule['url'] = $target['url'];
        $rule['external'] = (bool)($target['external'] ?? false);
        $keywordHash = mb_strtolower((string)($rule['keyword'] ?? ''));
        if ($keywordHash === '' || isset($resolved[$keywordHash])) {
            continue;
        }
        $resolved[$keywordHash] = $rule;
    }

    if (!$resolved) {
        return $html;
    }

    $wrappedHtml = '<div id="article-content-root">' . $content . '</div>';
    $previousUseErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previousUseErrors);

    if (!$loaded) {
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $textNodes = $xpath->query('//div[@id="article-content-root"]//text()[normalize-space(.)!="" and not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3)]');
    if (!$textNodes || $textNodes->length === 0) {
        return $html;
    }

    $resolved = array_values($resolved);

    $usedKeywords = [];
    foreach ($textNodes as $textNode) {
        $text = (string)$textNode->nodeValue;
        if ($text === '') {
            continue;
        }

        foreach ($resolved as $rule) {
            $keyword = (string)$rule['keyword'];
            $keyHash = mb_strtolower($keyword);
            if ($keyword === '' || isset($usedKeywords[$keyHash])) {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}])(' . preg_quote($keyword, '/') . ')(?![\p{L}\p{N}])/ui';
            if (!preg_match($pattern, $text)) {
                continue;
            }

            $replacement = '<a href="' . e($rule['url']) . '" class="seo-auto-link"';
            $openInNewTab = !empty($rule['new_tab']) || !empty($rule['external']);
            if ($openInNewTab) {
                $replacement .= ' target="_blank"';
            }

            $relParts = [];
            if ($openInNewTab) {
                $relParts[] = 'noopener';
            }
            if (!empty($rule['nofollow'])) {
                $relParts[] = 'nofollow';
            }
            if ($relParts) {
                $replacement .= ' rel="' . implode(' ', $relParts) . '"';
            }
            $replacement .= '>$1</a>';

            $updated = preg_replace($pattern, $replacement, $text, 1);
            if ($updated === null || $updated === $text) {
                continue;
            }

            $fragment = $dom->createDocumentFragment();
            if (!$fragment->appendXML($updated)) {
                continue;
            }

            $textNode->parentNode->replaceChild($fragment, $textNode);
            $usedKeywords[$keyHash] = true;
            break;
        }
    }

    $root = $dom->getElementById('article-content-root');
    if (!$root) {
        return $html;
    }

    $result = "";
    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }

    return $result !== "" ? $result : $html;
}

function injectAdsIntoArticleContent($html, $articleTitle = '', $articleCategory = '') {
    $content = trim((string)$html);
    if ($content === '') {
        return $html;
    }

    if (stripos($content, 'inline-ad-unit') !== false) {
        return $html;
    }

    $adSettings = getAdSettings();
    if (!$adSettings['enabled']) {
        return $html;
    }

    $maxUnits = (int)$adSettings['max_units'];
    $interval = (int)$adSettings['paragraph_interval'];
    $minWords = (int)$adSettings['min_words_before_first'];
    $minArticleWords = (int)$adSettings['min_article_words'];

    $articleWordCount = countVisibleWords(strip_tags($content));
    if ($articleWordCount < $minArticleWords) {
        return $html;
    }

    $titleKeywords = parseAdBlockedTitleKeywords($adSettings['blocked_title_keywords'] ?? '');
    if ($titleKeywords) {
        $title = mb_strtolower(trim((string)$articleTitle));
        foreach ($titleKeywords as $keyword) {
            if ($title !== '' && mb_stripos($title, $keyword) !== false) {
                return $html;
            }
        }
    }

    $blockedCategories = parseAdListTokens($adSettings['blocked_categories'] ?? '');
    if ($blockedCategories) {
        $category = mb_strtolower(trim((string)$articleCategory));
        if ($category !== '' && in_array($category, $blockedCategories, true)) {
            return $html;
        }
    }

    $wrappedHtml = '<div id="article-content-root">' . $content . '</div>';
    $previousUseErrors = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previousUseErrors);

    if (!$loaded) {
        return $html;
    }

    $root = $dom->getElementById('article-content-root');
    if (!$root) {
        return $html;
    }

    $candidates = [];
    foreach ($root->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        $tag = strtolower($child->tagName);
        if (!in_array($tag, ['p', 'ul', 'ol', 'blockquote', 'h2', 'h3'], true)) {
            continue;
        }

        $text = trim((string)$child->textContent);
        if ($text === '') {
            continue;
        }

        $candidates[] = [
            'node' => $child,
            'tag' => $tag,
            'words' => countVisibleWords($text),
            'text_len' => mb_strlen($text),
        ];
    }

    if (count($candidates) < 3) {
        return $html;
    }

    $indexes = [];
    $runningWords = 0;
    $lastIndex = -999;

    foreach ($candidates as $idx => $candidate) {
        $runningWords += (int)$candidate['words'];
        if ($idx < 1 || $idx >= count($candidates) - 1) {
            continue;
        }

        $shouldInject = false;
        if ($adSettings['mode'] === 'interval') {
            $shouldInject = $idx > 0 && $idx % $interval === 0;
        } else {
            $substantial = (int)$candidate['text_len'] >= 180 || (int)$candidate['words'] >= 28;
            $firstThreshold = $runningWords >= $minWords;
            $spacingOkay = ($idx - $lastIndex) >= max(2, $interval - 1);
            $shouldInject = $substantial && $firstThreshold && $spacingOkay;
        }

        if ($shouldInject) {
            $indexes[] = $idx;
            $lastIndex = $idx;
            if (count($indexes) >= $maxUnits) {
                break;
            }
        }
    }

    if (!$indexes) {
        return $html;
    }

    $adCount = 0;
    foreach ($indexes as $idx) {
        $targetNode = $candidates[$idx]['node'];
        $adCount++;

        $fragment = $dom->createDocumentFragment();
        $fragment->appendXML(buildInlineAdUnitHtml($adSettings, $adCount));

        if ($targetNode->nextSibling) {
            $targetNode->parentNode->insertBefore($fragment, $targetNode->nextSibling);
        } else {
            $targetNode->parentNode->appendChild($fragment);
        }
    }

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }

    return $result !== '' ? $result : $html;
}


function getAutoPublishIntervalSeconds() {
    $secondsRaw = getSetting('auto_publish_interval_seconds', null);
    if ($secondsRaw !== null && $secondsRaw !== '') {
        return getSettingInt('auto_publish_interval_seconds', 10800, 1, PHP_INT_MAX);
    }

    $minutesRaw = getSetting('auto_publish_interval_minutes', null);
    if ($minutesRaw !== null && $minutesRaw !== '') {
        $minutes = getSettingInt('auto_publish_interval_minutes', 180, 1, PHP_INT_MAX);
        if ($minutes > intdiv(PHP_INT_MAX, 60)) {
            return PHP_INT_MAX;
        }

        return max(1, $minutes * 60);
    }

    return 10800;
}


function getCurrentBaseUrl() {
    $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = $forwardedProto !== ''
        ? strtolower($forwardedProto) === 'https'
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443));

    $scheme = $isHttps ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));

    $scriptName = trim((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = str_replace('\\', '/', dirname($scriptName));
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '';
    }
    $basePath = rtrim($basePath, '/');

    return $scheme . '://' . $host . $basePath;
}

function getCronEndpointUrl() {
    return getCurrentBaseUrl() . '/cron.php';
}

function getAutoPublishSchedulerMeta() {
    $lastRun = strtotime((string)getSetting('auto_publish_last_run_at', '1970-01-01 00:00:00')) ?: 0;
    $interval = getAutoPublishIntervalSeconds();
    $nextRun = $lastRun + $interval;
    $remaining = max(0, $nextRun - time());

    return [
        'interval_seconds' => $interval,
        'last_run_at' => date('Y-m-d H:i:s', $lastRun),
        'next_run_at' => date('Y-m-d H:i:s', $nextRun),
        'remaining_seconds' => $remaining,
    ];
}

function classifyVehicleProfile($title) {
    $titleLower = mb_strtolower($title, 'UTF-8');
    $map = [
        'SUV' => ['suv', 'crossover'],
        'Sedan' => ['sedan', 'saloon'],
        'Coupe' => ['coupe'],
        'Hatchback' => ['hatch', 'hatchback'],
        'Truck' => ['pickup', 'truck'],
    ];

    foreach ($map as $label => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($titleLower, $keyword) !== false) {
                return $label;
            }
        }
    }

    return 'Vehicle';
}

function buildBuyerPersonaSection($title, $isEV, $bodyType) {
    $useCase = $isEV ? 'daily charging rhythm, home charging access, and route planning confidence' : 'annual mileage, fuel cost sensitivity, and service-network convenience';
    $personaA = "<li><strong>Urban Professional:</strong> Best if your priority is refinement, technology usability, and stress-free commuting in mixed traffic.</li>";
    $personaB = "<li><strong>Family-Oriented Driver:</strong> Strong candidate when cabin practicality, comfort, and predictable ownership costs matter most.</li>";
    $personaC = "<li><strong>Enthusiast Pragmatist:</strong> Suitable for buyers who want engaging performance without sacrificing real-world comfort and reliability.</li>";

    return "<h2>Who Should Buy This {$bodyType}?</h2>
"
        . "<p>Before committing to the {$title}, align your decision with real usage patterns: {$useCase}. The strongest purchase decisions come from fit, not hype.</p>
"
        . "<ul>{$personaA}{$personaB}{$personaC}</ul>
";
}

function buildFaqSection($title, $isEV) {
    $runningCost = $isEV
        ? 'In many markets, charging remains cheaper per kilometer than fuel, especially with home charging and off-peak tariffs.'
        : 'Running costs depend on driving style and service intervals, but predictable maintenance plans can stabilize yearly expenses.';

    $faq = [
        ['Is the ' . $title . ' good for daily use?', 'Yes. Its strongest argument is consistency in comfort, usability, and technology behavior across routine driving scenarios.'],
        ['How does it compare with rivals?', 'It competes best when buyers value balanced engineering and ownership confidence rather than headline numbers alone.'],
        ['What about long-term cost?', $runningCost],
    ];

    $html = "<h2>Frequently Asked Questions</h2>
";
    foreach ($faq as [$q, $a]) {
        $html .= "<h3>{$q}</h3>
<p>{$a}</p>
";
    }

    return $html;
}


function getAutoTitleDefaultSettings() {
    return [
        'auto_title_mode' => 'template',
        'auto_title_min_year_offset' => '0',
        'auto_title_max_year_offset' => '1',
        'auto_title_brands' => "Toyota
BMW
Mercedes
Audi
Porsche
Tesla
Hyundai
Kia
Ford
Nissan
Volvo
Lexus",
        'auto_title_models' => "SUV
Sedan
Coupe
EV Crossover
Hybrid SUV
Performance Hatchback
Electric Sedan
Luxury Wagon
Premium Crossover",
        'auto_title_modifiers' => "Review
Specs
Price
Comparison
Buying Guide
Ownership Cost",
        'auto_title_audiences' => "Smart Buyers
First-Time Premium Buyers
Tech-Focused Drivers
Family Buyers",
        'auto_title_angles' => "Full Review and Buyer Guide
Long-Term Ownership Analysis
Real-World Efficiency Test
Daily Driving Impression
Smart Technology Deep Dive
Comparison and Value Breakdown
Reliability, Resale, and Total Cost Breakdown",
        'auto_title_templates' => "{year} {brand} {model} {modifier}: {angle} for {audience}
{year} {brand} {model} {modifier} — {angle} ({audience})
{year} {brand} {model}: {modifier} + {angle}",
        'auto_title_fixed_titles' => '',
    ];
}

function getAutoTitleSetting($key) {
    $defaults = getAutoTitleDefaultSettings();
    $fallback = $defaults[$key] ?? '';

    $activeNiche = trim((string)getActiveNicheSlug());
    if ($activeNiche !== '') {
        $nicheValue = getSetting('niche.' . $activeNiche . '.' . $key, null);
        if ($nicheValue !== null && trim((string)$nicheValue) !== '') {
            return (string)$nicheValue;
        }
    }

    return (string)getSetting($key, $fallback);
}

function parseSettingList($key, $fallback) {
    $raw = (string)getAutoTitleSetting($key);
    if (trim($raw) === '') {
        $raw = (string)$fallback;
    }
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $clean = [];

    foreach ($lines as $line) {
        $value = trim((string)$line);
        if ($value !== '') {
            $clean[] = $value;
        }
    }

    return $clean;
}

function pickRandomFromList(array $items, $fallback = '') {
    if (!$items) {
        return (string)$fallback;
    }

    return (string)$items[array_rand($items)];
}

function generateAutoTitle() {
    $mode = trim(getAutoTitleSetting('auto_title_mode'));

    if ($mode === 'list') {
        $fixedTitles = parseSettingList('auto_title_fixed_titles', '');
        if ($fixedTitles) {
            return pickRandomFromList($fixedTitles);
        }
        $mode = 'template';
    }

    $minOffset = (int)getAutoTitleSetting('auto_title_min_year_offset');
    $maxOffset = (int)getAutoTitleSetting('auto_title_max_year_offset');
    $minOffset = max(-1, min(2, $minOffset));
    $maxOffset = max(-1, min(3, $maxOffset));
    if ($maxOffset < $minOffset) {
        [$minOffset, $maxOffset] = [$maxOffset, $minOffset];
    }

    $year = (int)date('Y') + rand($minOffset, $maxOffset);
    $brand = pickRandomFromList(parseSettingList('auto_title_brands', getAutoTitleSetting('auto_title_brands')), 'Toyota');
    $model = pickRandomFromList(parseSettingList('auto_title_models', getAutoTitleSetting('auto_title_models')), 'SUV');
    $modifier = pickRandomFromList(parseSettingList('auto_title_modifiers', getAutoTitleSetting('auto_title_modifiers')), 'Review');
    $audience = pickRandomFromList(parseSettingList('auto_title_audiences', getAutoTitleSetting('auto_title_audiences')), 'Smart Buyers');
    $angle = pickRandomFromList(parseSettingList('auto_title_angles', getAutoTitleSetting('auto_title_angles')), 'Full Review and Buyer Guide');

    $templates = parseSettingList('auto_title_templates', getAutoTitleSetting('auto_title_templates'));
    if (!$templates) {
        $templates = ['{year} {brand} {model} {modifier}: {angle} for {audience}'];
    }

    $template = pickRandomFromList($templates, '{year} {brand} {model} {modifier}: {angle} for {audience}');
    $replacements = [
        '{year}' => (string)$year,
        '{brand}' => $brand,
        '{model}' => $model,
        '{modifier}' => $modifier,
        '{audience}' => $audience,
        '{angle}' => $angle,
    ];

    return trim(strtr($template, $replacements));
}

function generateUniqueAutoTitle($maxAttempts = 12) {
    $attempts = max(1, (int)$maxAttempts);
    for ($i = 0; $i < $attempts; $i++) {
        $title = generateAutoTitle();
        if (!articleExists($title)) {
            return $title;
        }
    }

    return null;
}

function publishAutoArticleBySchedule($force = false) {
    $enabled = getSettingInt('auto_ai_enabled', 1, 0, 1);
    if (!$force && $enabled !== 1) {
        return ['published' => 0, 'reason' => 'disabled'];
    }

    $intervalSeconds = getAutoPublishIntervalSeconds();
    $lastRun = strtotime((string)getSetting('auto_publish_last_run_at', '1970-01-01 00:00:00')) ?: 0;
    $now = time();

    if (!$force && ($now - $lastRun) < $intervalSeconds) {
        return ['published' => 0, 'reason' => 'not_due'];
    }

    $title = generateUniqueAutoTitle();
    if ($title === null) {
        return ['published' => 0, 'reason' => 'title_generation_failed'];
    }

    $data = generateArticle($title);
    if (!saveArticle($title, $data)) {
        return ['published' => 0, 'reason' => 'duplicate_title_or_content'];
    }
    setSetting('auto_publish_last_run_at', date('Y-m-d H:i:s', $now));

    return ['published' => 1, 'reason' => 'ok', 'title' => $title];
}

function getSetting($key, $default = null) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

function setSetting($key, $value) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    executeStatementWithRetry($stmt, [$key, (string)$value]);
}

function articleExists($title) {
    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT id FROM articles WHERE title = ?");
    $stmt->execute([$title]);
    return $stmt->fetch() !== false;
}

function normalizeTextForComparison($text) {
    $text = mb_strtolower((string)$text, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function normalizeTitleFingerprint($title) {
    $title = cleanAndNormalizeTitle($title);
    if ($title === '') {
        return '';
    }

    $title = mb_strtolower($title, 'UTF-8');
    $title = preg_replace('/\b(19|20)\d{2}\b/u', ' ', (string)$title);
    $title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string)$title);

    $tokens = preg_split('/\s+/u', trim((string)$title));
    if (!is_array($tokens)) {
        return '';
    }

    $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'for', 'with', 'from', 'to', 'of',
        'review', 'guide', 'buying', 'best', 'top', 'new', 'vs'
    ];

    $filtered = [];
    foreach ($tokens as $token) {
        if ($token === '' || in_array($token, $stopWords, true)) {
            continue;
        }
        $filtered[] = $token;
    }

    return implode(' ', $filtered);
}

function articleTitleVariantExists($title) {
    $pdo = db_connect();
    $baseSlug = slugify($title);
    if ($baseSlug === '') {
        return false;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM articles WHERE slug = ? OR slug LIKE ? LIMIT 1");
    $stmt->execute([$baseSlug, $baseSlug . '-%']);
    return $stmt->fetchColumn() !== false;
}

function articleTitleFingerprintExists($title) {
    $fingerprint = normalizeTitleFingerprint($title);
    if ($fingerprint === '') {
        return false;
    }

    $pdo = db_connect();
    $rowsStmt = $pdo->prepare("SELECT title FROM articles ORDER BY id DESC LIMIT 500");
    $rowsStmt->execute();
    $rows = $rowsStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rows as $storedTitle) {
        $storedFingerprint = normalizeTitleFingerprint((string)$storedTitle);
        if ($storedFingerprint !== '' && hash_equals($storedFingerprint, $fingerprint)) {
            return true;
        }
    }

    return false;
}

function articleContentExists($content) {
    $normalizedContent = normalizeTextForComparison(strip_tags((string)$content));
    if ($normalizedContent === '') {
        return false;
    }

    $pdo = db_connect();
    $stmt = $pdo->prepare("SELECT content FROM articles ORDER BY id DESC LIMIT 200");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($rows as $storedContent) {
        $storedNormalized = normalizeTextForComparison(strip_tags((string)$storedContent));
        if ($storedNormalized !== '' && hash_equals($storedNormalized, $normalizedContent)) {
            return true;
        }
    }

    return false;
}

function isDuplicateArticlePayload($title, $content) {
    return articleExists($title)
        || articleTitleVariantExists($title)
        || articleTitleFingerprintExists($title)
        || articleContentExists($content);
}

function generateUniqueSlug($title) {
    $pdo = db_connect();
    $base = slugify($title);
    $base = $base !== '' ? $base : 'article';
    $slug = $base;
    $i = 2;

    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE slug = ?");
        $stmt->execute([$slug]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function buildFreeArticleImageUrl($seed, $nicheSlug = '') {
    $seedText = trim((string)$seed);
    if ($seedText === '') {
        $seedText = 'car-article';
    }

    if ($nicheSlug === '') {
        $nicheSlug = getActiveNicheSlug();
    }
    $nicheSlug = trim((string)$nicheSlug);
    if ($nicheSlug === '') {
        $nicheSlug = 'general';
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($seedText . '-' . $nicheSlug));
    $slug = trim((string)$slug, '-');
    if ($slug === '') {
        $slug = 'car-article-general';
    }

    return "https://picsum.photos/seed/{$slug}/1200/675";
}

function buildUniqueArticleImageUrl($title, $model = '') {
    $pdo = db_connect();
    $baseSeed = trim($title . '-' . $model);
    $activeNiche = getActiveNicheSlug();

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $seed = $attempt === 1 ? $baseSeed : $baseSeed . '-' . $attempt;
        $candidate = buildFreeArticleImageUrl($seed, $activeNiche);
        $stmt = $pdo->prepare("SELECT 1 FROM articles WHERE image = ? LIMIT 1");
        $stmt->execute([$candidate]);
        if ($stmt->fetchColumn() === false) {
            return $candidate;
        }
    }

    return buildFreeArticleImageUrl($baseSeed . '-' . uniqid('', true), $activeNiche);
}

function verifyAdminPassword($password) {
    if (!is_string($password) || $password === '') {
        return false;
    }

    $adminPassword = getenv('ADMIN_PASSWORD');
    if (is_string($adminPassword) && $adminPassword !== '' && hash_equals($adminPassword, $password)) {
        return true;
    }

    $storedHash = getSetting('admin_password_hash', PASSWORD_HASH);
    if (!is_string($storedHash) || trim($storedHash) === '') {
        $storedHash = PASSWORD_HASH;
    }

    return password_verify($password, $storedHash);
}

function getRandomIntro($title, array $nicheMeta = []) {
    $slug = mb_strtolower((string)($nicheMeta['slug'] ?? ''), 'UTF-8');
    $nicheLabel = trim((string)($nicheMeta['label'] ?? ''));
    $introContext = trim((string)($nicheMeta['intro_context'] ?? ''));
    $isCuisine = str_contains($slug, 'cuisine') || str_contains(mb_strtolower($nicheLabel, 'UTF-8'), 'food');
    $isHealth = str_contains($slug, 'health');
    $isBusiness = str_contains($slug, 'business') || str_contains($nicheLabel, 'Business');
    $isEV = $slug === 'ev' || str_contains($nicheLabel, 'Electric');

    $intros = [];
    if ($isCuisine) {
        $intros = [
            "The $title is designed to help home cooks and food lovers make smarter, tastier choices in the kitchen.",
            "For the $title, flavor balance, preparation clarity, and ingredient quality are the strongest selling points.",
            "This $title combines approachable cooking guidance with practical recipe insights that matter to everyday chefs.",
        ];
    } elseif ($isHealth) {
        $intros = [
            "The $title is framed around real wellness benefits and practical steps ordinary readers can use.",
            "With the $title, the focus is on evidence-based advice, easy-to-follow habits, and sustainable lifestyle improvements.",
            "This $title is tailored for people who want clear health guidance rather than vague motivational messaging.",
        ];
    } elseif ($isBusiness) {
        $intros = [
            "The $title is structured to separate signal from noise in a fast-moving business context.",
            "This article looks beyond headlines to uncover the real strategy, risk, and financial implications behind the story.",
            "For readers focused on opportunity and outcome, the $title outlines the practical decisions that matter most.",
        ];
    } elseif ($isEV) {
        $intros = [
            "The $title is evaluated through the lens of charging readiness, range confidence, and real-world efficiency.",
            "This electric model is judged not just on specs, but on how well it supports daily routines and long-term ownership.",
            "The $title is built for drivers who care about consistent range performance, software stability, and charging convenience.",
        ];
    } else {
        $intros = [
            "The $title arrives at a time when buyers expect more than raw performance—they expect intelligence, consistency, and real ownership value.",
            "The $title reflects a modern automotive philosophy where design, software, efficiency, and durability must all work together.",
            "With the $title, the brand is clearly targeting drivers who care about emotional appeal and practical decision-making in equal measure.",
            "The $title enters a competitive segment, and its real strength is how it balances premium character with day-to-day usability.",
        ];
    }

    if ($introContext !== '') {
        $intros[] = "This article centers on {$introContext} so that the $title review is more useful for informed buyers.";
    }

    return $intros[array_rand($intros)];
}

function getNicheWritingAngle(array $nicheMeta) {
    $slug = mb_strtolower((string)$nicheMeta['slug'], 'UTF-8');
    if ($slug === 'ev' || str_contains((string)$nicheMeta['label'], 'Electric')) {
        return 'charging behavior, energy efficiency, and software maturity';
    }
    if ($slug === 'motorcycles') {
        return 'riding dynamics, ergonomic fit, and control confidence';
    }
    if (str_contains($slug, 'cuisine') || str_contains((string)$nicheMeta['label'], 'Food')) {
        return 'ingredient quality, recipe usefulness, and flavor consistency';
    }
    if (str_contains($slug, 'business') || str_contains((string)$nicheMeta['label'], 'Business')) {
        return 'market relevance, financial clarity, and strategic decision-making';
    }
    return 'real ownership value, practical usability, and competitive positioning';
}

function buildNicheSeoKeywords($title, array $nicheMeta) {
    $keywords = [
        $title,
        $title . ' review',
        $title . ' best features',
        $title . ' buying advice',
        $title . ' reliability',
        $title . ' pros and cons',
        $title . ' vs competitors',
    ];

    $nicheLabel = trim((string)$nicheMeta['label']);
    if ($nicheLabel !== '' && mb_strtolower($nicheLabel, 'UTF-8') !== 'general') {
        $keywords[] = $nicheLabel . ' review';
        $keywords[] = $nicheLabel . ' buying guide';
    }

    $topicContext = trim((string)$nicheMeta['intro_context']);
    if ($topicContext !== '') {
        $keywords[] = $title . ' ' . $topicContext;
    }

    return array_values(array_unique(array_filter(array_map('trim', $keywords))));
}

function getArticleExpansionLibrary($title, array $nicheMeta) {
    $nicheLabel = trim((string)$nicheMeta['label']);

    return [
        "<p>Beyond specifications, the {$title} should be evaluated through lifecycle quality: software stability, service access, parts availability, and dealer competence. These ownership signals are especially important for {$nicheLabel} buyers who want lasting value rather than a good first impression.</p>",
        "<p>A final strategic angle concerns resale narrative. Products that preserve a strong identity, avoid unnecessary complexity, and maintain predictable reliability tend to keep value better. The {$title} appears aligned with that principle by emphasizing balance instead of gimmicks.</p>",
        "<p>From a product planning perspective, the {$title} signals where the brand may be heading next: stronger system integration, higher efficiency discipline, and a clearer user-first direction. Those are the kinds of improvements that matter for thoughtful {$nicheLabel} readers.</p>",
    ];
}

function pickRandomFrom(array $items) {
    return $items[array_rand($items)];
}

function buildAnalyticalParagraph($title, $sectionTitle, $focus, $nicheType, $perspective) {
    $energyContext = $nicheType === 'auto'
        ? ($sectionTitle === 'Powertrain Intelligence, Performance Delivery, and Efficiency'
            ? 'its electric architecture, battery management logic, and charging ecosystem'
            : 'its engine calibration, transmission strategy, and thermal durability')
        : 'its practical characteristics, execution quality, and real-world suitability';

    if ($nicheType === 'food') {
        $openingBank = [
            "For {$sectionTitle}, the {$title} should be judged by {$focus} in a kitchen-friendly way.",
            "When considering {$sectionTitle}, the most useful point is {$focus} for a satisfying final dish.",
            "A strong {$title} recipe stands out when {$focus} is handled with clarity and practical technique."
        ];
        $analysisBank = [
            "The key to a good result is consistency in flavor, texture, and timing rather than chasing complex tricks.",
            "A home cook benefits from predictable steps, understandable ingredients, and a final result that tastes balanced.",
            "This recipe succeeds when the elements work together without overwhelming the main character of the dish."
        ];
        $perspectiveBank = [
            "For everyday cooking, that means fewer surprises and more confidence in the outcome.",
            "When you prepare this for guests, the most memorable part should be the harmony of flavors and the ease of execution.",
            "A practical recipe is one you are happy to make again, not one that only looks impressive on the first try."
        ];
        $closingBank = [
            "That kind of coherence is what makes {$title} feel like a dependable kitchen choice rather than a one-off experiment.",
            "In the end, a successful dish is judged by how well it delivers enjoyment, repeatability, and sensible preparation.",
            "Ultimately, this is a recipe you want to return to because it balances taste, effort, and reliability."
        ];
    } elseif ($nicheType === 'finance') {
        $openingBank = [
            "In the context of {$sectionTitle}, the {$title} should be measured by {$focus}.",
            "This topic becomes meaningful when {$focus} is evaluated against real financial outcomes.",
            "A smart decision is built around {$focus}, and that matters more than flashy short-term claims."
        ];
        $analysisBank = [
            "The stronger signal here is long-term clarity rather than a single headline benefit.",
            "Consistent results come from predictable assumptions and a realistic view of risk, not from overly aggressive projections.",
            "What sets good advice apart is the practical alignment with your goals, time frame, and tolerance for complexity."
        ];
        $perspectiveBank = [
            "For a business owner or investor, the right choice usually means a lower chance of regret later on.",
            "When resources are constrained, the best outcomes come from decisions that balance growth potential with discipline.",
            "A conservative approach can still be attractive if it preserves flexibility and reduces unnecessary exposure."
        ];
        $closingBank = [
            "That kind of discipline is what helps the {$title} translate theory into reliable decision-making.",
            "In practice, the best strategy is one that remains sensible under different market conditions.",
            "Ultimately, the strongest case is built on real-world execution, not just appealing jargon."
        ];
    } else {
        $openingBank = [
            "In the context of {$sectionTitle}, the {$title} deserves attention for {$focus}.",
            "Looking at {$sectionTitle} through a practical lens, {$focus} becomes one of the most relevant points for {$title}.",
            "When analysts evaluate {$sectionTitle}, they usually start with {$focus}, and the {$title} performs in a convincing way."
        ];
        $analysisBank = [
            "The most credible part of this story is not a single headline figure, but the consistency of behavior across daily scenarios like traffic, highway cruising, and weekend travel.",
            "What separates mature products from average ones is repeatability, and here the vehicle keeps a stable character even when road quality, weather, and load conditions change.",
            "Instead of over-optimizing for lab-style results, the package appears tuned for real-world confidence where comfort, control, and predictability matter every day."
        ];
        $perspectiveBank = [
            "From an owner perspective, this means fewer compromises between comfort and capability, and a lower chance of buyer regret after the first months of excitement.",
            "For mixed-use drivers, this creates a meaningful advantage: the car feels refined in city conditions yet remains composed when pushed on open roads.",
            "From a long-term standpoint, this balance supports stronger perceived quality because the driving experience remains coherent rather than fragmented."
        ];
        $closingBank = [
            "That broader coherence is reinforced by {$energyContext}, which helps the {$title} translate engineering choices into tangible daily benefits.",
            "The result is a clearer value proposition: the {$title} is not merely impressive on paper, it is understandable and rewarding in normal ownership use.",
            "Ultimately, this is where product intelligence appears—different systems collaborate naturally instead of competing for attention."
        ];
    }

    $paragraph = pickRandomFrom($openingBank) . ' '
        . pickRandomFrom($analysisBank) . ' '
        . pickRandomFrom($perspectiveBank) . ' '
        . pickRandomFrom($closingBank);

    if ($perspective !== '') {
        $paragraph .= ' ' . $perspective;
    }

    return "<p>{$paragraph}</p>";
}

function buildSectionContent($title, $sectionTitle, array $focusPoints, $nicheType, array $nicheMeta) {
    $perspectives = [
        'This is especially important in segments where buyers compare six or seven alternatives before committing.',
        'In competitive markets, small gains in usability often influence purchase decisions more than aggressive marketing claims.',
        'For families and frequent commuters, these details can be more valuable than short-term novelty features.'
    ];

    $paragraphs = [];
    foreach ($focusPoints as $index => $focus) {
        $paragraphs[] = buildAnalyticalParagraph(
            $title,
            $sectionTitle,
            $focus,
            $nicheType,
            $perspectives[$index % count($perspectives)]
        );
    }

    return $paragraphs;
}

function buildComparisonTable($title, $bodyType, $isEV) {
    $rivalMap = [
        'SUV' => ['Toyota RAV4 Hybrid', 'Honda CR-V Hybrid'],
        'Sedan' => ['Toyota Camry', 'Honda Accord'],
        'Truck' => ['Ford Ranger', 'Toyota Hilux'],
        'Coupe' => ['BMW 4 Series', 'Audi A5'],
        'Hatchback' => ['Volkswagen Golf', 'Mazda 3'],
    ];

    $rivals = $rivalMap[$bodyType] ?? ['Segment Benchmark Model', 'Value-Focused Alternative'];
    $efficiencyMetric = $isEV ? 'Range (km est.)' : 'Fuel Economy (L/100km)';
    $efficiencyValue = $isEV ? (string)rand(460, 690) : (string)rand(6, 10);

    return "<h2>Competitor Comparison at a Glance</h2>
"
        . "<p>Buyers searching for {$title} alternatives usually compare real ownership outcomes, not only launch marketing numbers. This table highlights how {$title} stacks up against common choices in the same {$bodyType} category.</p>
"
        . "<div class='table-responsive'><table class='table table-striped table-bordered'><thead><tr><th>Model</th><th>Positioning</th><th>{$efficiencyMetric}</th><th>Best For</th></tr></thead><tbody>"
        . "<tr><td><strong>{$title}</strong></td><td>Balanced performance + daily practicality</td><td>{$efficiencyValue}</td><td>Buyers wanting long-term ownership confidence</td></tr>"
        . "<tr><td>{$rivals[0]}</td><td>Mainstream benchmark setup</td><td>Competitive</td><td>Drivers prioritizing proven familiarity</td></tr>"
        . "<tr><td>{$rivals[1]}</td><td>Value-first package</td><td>Strong on paper</td><td>Cost-sensitive buyers with simpler needs</td></tr>"
        . "</tbody></table></div>
";
}

function buildBuyingChecklistSection($title, $isEV) {
    $specificPoint = $isEV
        ? 'Verify home charging readiness, local fast-charging coverage, and expected charging curve behavior in your climate.'
        : 'Check expected fuel economy in your driving pattern and compare maintenance package coverage by dealership.';

    return "<h2>Pre-Purchase Checklist</h2>
"
        . "<p>Before finalizing {$title}, use this shortlist to reduce buyer regret and improve long-term value:</p>
"
        . "<ol>"
        . "<li>Compare trims by safety and comfort features, not badge labels alone.</li>"
        . "<li>{$specificPoint}</li>"
        . "<li>Request a real-world test route that includes city traffic, rough roads, and highway speeds.</li>"
        . "<li>Confirm warranty details, service intervals, and total ownership cost projections for 3–5 years.</li>"
        . "</ol>
";
}

function buildPeopleAlsoAskSection($title, $isEV) {
    $chargingQuestion = $isEV
        ? "<li><strong>How fast can {$title} charge in daily use?</strong> Charging speed depends on charger type and battery state, but owners should prioritize charging curve stability and local infrastructure quality over peak brochure numbers.</li>"
        : "<li><strong>Is {$title} fuel-efficient enough for daily commuting?</strong> Real-world efficiency is strongest when traffic, maintenance, and driving style are considered together instead of relying on ideal test cycles.</li>";

    return "<h2>People Also Ask</h2>
"
        . "<p>These are common search questions potential buyers ask before deciding:</p>
"
        . "<ul>"
        . "<li><strong>Is {$title} worth buying this year?</strong> It is a strong candidate for buyers who want a complete package with fewer compromises across comfort, tech, and ownership predictability.</li>"
        . "<li><strong>How does {$title} compare with main competitors?</strong> The model typically wins by offering more balanced day-to-day behavior, even if some rivals lead in one isolated metric.</li>"
        . $chargingQuestion
        . "<li><strong>What should I check before signing the purchase?</strong> Compare trim value, warranty clarity, service network quality, and total cost of ownership rather than focusing only on sticker price.</li>"
        . "</ul>
";
}

function buildFaqSchemaScript($title, $isEV) {
    $faqItems = [
        [
            'question' => "Is {$title} a good daily driver?",
            'answer' => "Yes. {$title} is tuned to balance comfort, performance, and practical ownership, making it a solid daily-use option for most buyers.",
        ],
        [
            'question' => "How does {$title} compare with competitors?",
            'answer' => "It usually stands out through better overall balance and ownership confidence rather than relying on a single headline metric.",
        ],
        [
            'question' => $isEV ? "Is {$title} practical for charging routines?" : "Is {$title} economical to run over time?",
            'answer' => $isEV
                ? "With suitable home or public charging access, it can be practical for daily routines while also reducing long-term running costs."
                : "When maintained well and matched with the right trim, it can deliver predictable ownership costs over the long term.",
        ],
    ];

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(function ($item) {
            return [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }, $faqItems),
    ];

    return "<script type='application/ld+json'>" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>";
}

function ensureMinimumWordCount($html, $title, $minimumWords = 2100, array $nicheMeta = []) {
    $expansionLibrary = getArticleExpansionLibrary($title, $nicheMeta);

    $currentWords = str_word_count(strip_tags($html));
    $i = 0;
    while ($currentWords < $minimumWords) {
        $html .= "\n" . $expansionLibrary[$i % count($expansionLibrary)];
        $currentWords = str_word_count(strip_tags($html));
        $i++;
    }

    return $html;
}

function buildSeoBlock($title, $excerpt, array $nicheMeta = []) {
    $keywords = buildNicheSeoKeywords($title, $nicheMeta);
    $metaDescription = mb_substr(trim((string)$excerpt), 0, 155);
    $keywordHtml = '<ul><li>' . implode('</li><li>', array_map('e', $keywords)) . '</li></ul>';
    $categoryLabel = trim((string)$nicheMeta['label']);
    $pageLabel = $categoryLabel !== '' ? $categoryLabel : 'Buyer Guide';

    return [
        'meta_title' => $title . ' | ' . $pageLabel,
        'meta_description' => $metaDescription,
        'keywords' => $keywords,
        'html_block' => "<section class='seo-optimization'><h2>SEO Focus Keywords</h2>{$keywordHtml}<p><strong>Meta Description:</strong> " . e($metaDescription) . "</p></section>",
    ];
}

/**
 * Generate a standalone HTML page for an article payload. Includes basic
 * head metadata useful for SEO and mirrors the public article view.
 */
function buildArticleExportHtml(array $payload) {
    $baseUrl = getSiteBaseUrl();
    $siteTitle = getSiteTitle();
    $title = trim((string)($payload['title'] ?? ''));
    $slug = trim((string)($payload['slug'] ?? ''));
    $excerpt = trim((string)($payload['excerpt'] ?? ''));
    $content = (string)($payload['content'] ?? '');
    $published = trim((string)($payload['published_at'] ?? ''));
    $category = trim((string)($payload['category'] ?? ''));

    $pageTitle = $title !== '' ? $title . ' | ' . $siteTitle : $siteTitle;
    $pageDescription = $excerpt !== '' ? $excerpt : mb_substr(strip_tags($content), 0, 160);
    $canonical = rtrim($baseUrl, '/') . '/index.php?slug=' . rawurlencode($slug);

    $ogImage = trim((string)($payload['image'] ?? '')) ?: trim((string)($payload['image2'] ?? ''));
    $ogImageTag = $ogImage !== '' ? "<meta property=\"og:image\" content=\"" . e($ogImage) . "\">" : '';

    $html = '<!DOCTYPE html>\n';
    $html .= '<html lang="en">\n';
    $html .= '<head>\n';
    $html .= '    <meta charset="UTF-8">\n';
    $html .= '    <title>' . e($pageTitle) . '</title>\n';
    $html .= '    <meta name="description" content="' . e($pageDescription) . '">\n';
    $html .= '    <link rel="canonical" href="' . e($canonical) . '">\n';
    $html .= '    <meta property="og:title" content="' . e($pageTitle) . '">\n';
    $html .= '    <meta property="og:description" content="' . e($pageDescription) . '">\n';
    if ($ogImageTag) {
        $html .= '    ' . $ogImageTag . "\n";
    }
    $html .= '</head>\n';
    $html .= '<body>\n';
    $html .= '<article>\n';
    $html .= '<h1>' . e($title) . '</h1>\n';
    if ($published !== '') {
        $html .= '<time datetime="' . e($published) . '">' . e($published) . '</time>\n';
    }
    if ($category !== '') {
        $html .= '<p><strong>Category:</strong> ' . e($category) . '</p>\n';
    }
    $html .= $content . '\n';
    $html .= '</article>\n';
    $html .= '</body>\n';
    $html .= '</html>\n';

    return $html;
}

function writeArticleExportFiles($articleId, $slug, array $payload) {
    $exportsDir = __DIR__ . '/data/exports';
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0777, true);
    }

    $htmlPath = $exportsDir . '/' . $slug . '.html';
    $jsonPath = $exportsDir . '/' . $slug . '.json';

    // build a full page from payload instead of raw content only
    $htmlContent = buildArticleExportHtml($payload);
    file_put_contents($htmlPath, $htmlContent);
    file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $pdo = db_connect();
    $stmt = $pdo->prepare("INSERT INTO article_exports (article_id, slug, html_path, json_path, created_at)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT(article_id) DO UPDATE SET
            slug = excluded.slug,
            html_path = excluded.html_path,
            json_path = excluded.json_path,
            created_at = excluded.created_at");
    $stmt->execute([(int)$articleId, $slug, 'data/exports/' . $slug . '.html', 'data/exports/' . $slug . '.json', date('Y-m-d H:i:s')]);
}

function getStaticPages($language = null) {
    if ($language === null) {
        $language = detectPreferredLanguage();
    }
    $isArabic = $language === 'ar';
    $nicheServicesContent = [];
    $niches = listNiches();
    foreach ($niches as $niche) {
        $nicheName = trim((string)($niche['name'] ?? ''));
        $nicheSlug = trim((string)($niche['slug'] ?? ''));
        $nicheDesc = trim((string)($niche['description'] ?? ''));
        if ($nicheName === '') {
            continue;
        }

        $rssSources = $nicheSlug !== '' ? getNicheRssSources($nicheSlug) : [];
        $webSources = $nicheSlug !== '' ? getNicheWebSources($nicheSlug) : [];
        $rssCount = count($rssSources);
        $webCount = count($webSources);
        $sampleRss = $rssCount > 0 ? (string)$rssSources[0] : '';
        $sampleWeb = $webCount > 0 ? (string)$webSources[0] : '';
        $nicheTitle = $nicheName . ($nicheSlug !== '' ? ' (' . $nicheSlug . ')' : '');

        if ($isArabic) {
            $nicheServicesContent[] = 'نيش "' . $nicheTitle . '": نوفّر له إطار عمل متكامل للنمو يشمل التخطيط التحريري، تحسين الظهور في البحث، وقياس النتائج.';
            $nicheServicesContent[] = 'الوصف: ' . ($nicheDesc !== '' ? $nicheDesc : 'لا يوجد وصف حاليًا، ويمكننا صياغة وصف احترافي موجّه بدقة للجمهور المستهدف.');
            $nicheServicesContent[] = 'الجاهزية الحالية للمصادر: RSS=' . $rssCount . ' | Web=' . $webCount . '.';
            $nicheServicesContent[] = $sampleRss !== '' ? 'أقوى مصدر RSS مقترح: ' . $sampleRss : 'لا يوجد مصدر RSS مضاف حاليًا.';
            $nicheServicesContent[] = $sampleWeb !== '' ? 'أقوى مصدر Web مقترح: ' . $sampleWeb : 'لا يوجد مصدر Web مضاف حاليًا.';
            $nicheServicesContent[] = 'خطة AF (Attract & Follow-up): جذب الزوار عبر مواضيع دقيقة، ثم متابعة الأداء أسبوعيًا لتطوير خطة المحتوى وتحسين التحويل.';
        } else {
            $nicheServicesContent[] = 'Niche "' . $nicheTitle . '": we provide a complete growth framework across editorial planning, search visibility optimization, and measurable performance tracking.';
            $nicheServicesContent[] = 'Description: ' . ($nicheDesc !== '' ? $nicheDesc : 'No description is configured yet; we can craft a focused, audience-specific positioning summary.');
            $nicheServicesContent[] = 'Current source readiness: RSS=' . $rssCount . ' | Web=' . $webCount . '.';
            $nicheServicesContent[] = $sampleRss !== '' ? 'Top suggested RSS source: ' . $sampleRss : 'No RSS source is currently configured.';
            $nicheServicesContent[] = $sampleWeb !== '' ? 'Top suggested Web source: ' . $sampleWeb : 'No Web source is currently configured.';
            $nicheServicesContent[] = 'AF plan (Attract & Follow-up): attract qualified traffic through precise topic clusters, then run weekly follow-up optimization to improve reach and conversion.';
        }
    }
    if (empty($nicheServicesContent)) {
        $nicheServicesContent = $isArabic
            ? ['نقدّم خدمات مرنة قابلة للتخصيص حسب النيش المستهدف مع متابعة وتحسين مستمر.']
            : ['We offer flexible, niche-specific services with continuous follow-up and optimization.'];
    }
    return [
        'about' => [
            'title' => $isArabic ? 'من نحن' : 'About Us',
            'description' => $isArabic
                ? 'تعرف على رسالتنا التحريرية في مجال السيارات ومعايير النشر التي نعتمدها.'
                : 'Learn more about our automotive editorial mission, publishing standards, and audience-first approach.',
            'content' => $isArabic
                ? [
                    'ننشر محتوى عمليًا عن السيارات يركز على أسئلة المالك الحقيقية مثل الصيانة والشراء والسلامة.',
                    'نجمع بين الأتمتة والمراجعة البشرية لضمان جودة المحتوى وسهولة القراءة.',
                    'نلتزم بالوضوح والشفافية لمساعدة القرّاء على اتخاذ قرارات أفضل.',
                ]
                : [
                    'We publish practical automotive content focused on real ownership questions: maintenance, buying, safety, and long-term value.',
                    'Our editorial workflow combines automated research with final quality checks for readability, originality, and user usefulness.',
                    'We prioritize clear language, transparent labeling, and content that helps readers make better car decisions.',
                ],
        ],
        'contact' => [
            'title' => $isArabic ? 'تواصل معنا' : 'Contact Us',
            'description' => $isArabic
                ? 'هل تحتاج دعمًا أو ترغب في التعاون؟ تواصل مع فريق التحرير والدعم.'
                : 'Need support, want to report an issue, or discuss collaboration? Reach our editorial and support team.',
            'content' => $isArabic
                ? [
                    'لدعم الموقع وطلبات التحرير: contact@example.com',
                    'للشراكات والإعلانات: partnerships@example.com',
                    'نراجع جميع الرسائل ونحاول الرد خلال يومي عمل.',
                ]
                : [
                    'For support and editorial requests, please email: contact@example.com',
                    'For ad and business inquiries, please email: partnerships@example.com',
                    'We review all messages and aim to reply within 2 business days.',
                ],
        ],
        'services' => [
            'title' => $isArabic ? 'خدماتنا' : 'Our Services',
            'description' => $isArabic
                ? 'نقدم حزمة خدمات سيارات متكاملة تشمل الاستشارات قبل الشراء، تحليل التكلفة، وخطط الصيانة الذكية.'
                : 'Explore our end-to-end automotive services, from pre-purchase consulting to maintenance planning and ownership optimization.',
            'content' => array_merge(
                $isArabic
                ? [
                    'نقدّم خدمة استشارات قبل الشراء لمساعدتك على اختيار السيارة المناسبة بناءً على نمط الاستخدام اليومي، ميزانية الوقود أو الشحن، وتكاليف الملكية المتوقعة خلال 3 إلى 5 سنوات.',
                    'فريقنا يجهّز مقارنة تفصيلية بين الفئات المختلفة لنفس الموديل، مع توضيح الفرق الحقيقي في التجهيزات، أنظمة الأمان، القيمة مقابل السعر، وإعادة البيع المتوقعة في سوق المستعمل.',
                    'نوفر خدمة مراجعة حالة السيارة المستعملة قبل الشراء عبر قائمة فحص عملية تشمل السجل الفني، حالة الهيكل، أداء المحرك أو البطارية، وتكاليف الإصلاح المحتملة بعد الاستلام.',
                    'نساعدك في بناء خطة صيانة دورية ذكية مرتبطة بعدد الكيلومترات وطبيعة القيادة داخل المدينة أو على الطرق السريعة، لتقليل الأعطال المفاجئة وتحسين عمر السيارة.',
                    'نقدم تحليلات تكلفة تشغيل شهرية تتضمن الوقود أو الكهرباء، التأمين، الإهلاك، رسوم الترخيص، وتوقعات الصيانة، بحيث تحصل على صورة مالية واضحة قبل أي قرار.',
                    'للشركات وأصحاب الأساطيل الصغيرة، نوفر خدمة تحسين إدارة الأسطول عبر تقارير أداء المركبات، توزيع الاستخدام، وتحديد فرص خفض التكلفة ورفع الكفاءة التشغيلية.',
                    'نوفر خدمة تخطيط الرحلات الطويلة للسيارات الكهربائية والبنزين مع توصيات عملية لنقاط التوقف، إدارة الاستهلاك، وتجهيزات السلامة الضرورية قبل السفر.',
                    'نشاركك أدلة مبسطة للعناية بالسيارة بعد الشراء، مثل حماية الطلاء، متابعة ضغط الإطارات، تحسين استهلاك الطاقة، والحفاظ على القيمة السوقية للسيارة مع الوقت.',
                ]
                : [
                    'Our pre-purchase advisory service helps you choose the right vehicle using daily driving patterns, fuel or charging realities, and projected 3–5 year ownership costs.',
                    'We provide detailed trim-level comparisons to highlight meaningful differences in safety features, technology, comfort, resale potential, and true value for money.',
                    'For used vehicles, we offer a practical inspection framework covering service history, body condition, engine or battery health, and likely near-term repair risks.',
                    'We design mileage-based maintenance roadmaps tailored to city and highway usage so you can reduce unexpected failures and extend long-term vehicle reliability.',
                    'Our ownership cost analysis includes fuel or electricity, insurance, depreciation, licensing fees, and forecast maintenance to give you a complete financial view.',
                    'For small fleets and business operators, we provide optimization guidance through usage analytics, vehicle allocation insights, and cost-efficiency opportunities.',
                    'We support long-distance trip planning for EV and ICE vehicles with practical stop strategy recommendations, range management tips, and safety preparation checklists.',
                    'You also get easy-to-follow post-purchase care guidance covering tire pressure habits, paint protection basics, efficiency practices, and resale value preservation.',
                ],
                $nicheServicesContent
            ),
        ],
        'privacy' => [
            'title' => $isArabic ? 'سياسة الخصوصية' : 'Privacy Policy',
            'description' => $isArabic
                ? 'اعرف كيف نجمع ونستخدم ونحمي بيانات الزوار وملفات تعريف الارتباط.'
                : 'Read how we collect, use, and protect visitor data, cookies, and analytics information.',
            'content' => $isArabic
                ? [
                    'قد نستخدم التحليلات وتقنيات الإعلانات (مثل الكوكيز) لفهم الزيارات وتحسين التجربة.',
                    'لا نجمع عمدًا بيانات شخصية حساسة عبر الصفحات العامة، ونستخدم بيانات التواصل للرد فقط.',
                    'قد تعالج خدمات الطرف الثالث البيانات وفق سياساتها الخاصة، ويمكنك تعطيل الكوكيز من إعدادات المتصفح.',
                ]
                : [
                    'We may use analytics and advertising technologies (such as cookies and measurement scripts) to understand traffic and improve user experience.',
                    'We do not intentionally collect sensitive personal information through public pages. If you contact us directly, we only use your information to respond.',
                    'Third-party services (including ad providers) may process data according to their own privacy policies. You can disable cookies from your browser settings.',
                ],
        ],
        'terms' => [
            'title' => $isArabic ? 'الشروط والأحكام' : 'Terms of Use',
            'description' => $isArabic
                ? 'شروط الاستخدام التي تغطي الاستخدام المقبول وحقوق الملكية والتنبيهات القانونية.'
                : 'Website terms covering acceptable use, intellectual property, disclaimers, and content usage.',
            'content' => $isArabic
                ? [
                    'باستخدامك للموقع فإنك توافق على استخدام المحتوى لأغراض قانونية ومعلوماتية شخصية فقط.',
                    'جميع المقالات لأغراض معرفية عامة ولا تُعد بديلاً عن الاستشارات المهنية.',
                    'قد نقوم بتحديث المحتوى والسياسات في أي وقت لضمان الجودة والامتثال.',
                ]
                : [
                    'By using this website, you agree to use the content for lawful and personal informational purposes only.',
                    'All articles are provided for general information and do not replace professional legal, financial, or mechanical advice.',
                    'We may update content and site policies at any time to maintain quality, compliance, and platform requirements.',
                ],
        ],
    ];
}

/**
 * Write out standalone HTML versions of each static page for SEO.
 * Saves files like about.html, contact.html, privacy.html, terms.html in the root.
 */
function exportStaticPages($language = null) {
    $pages = getStaticPages($language);
    $baseUrl = getSiteBaseUrl();
    if ($baseUrl === '') {
        $baseUrl = 'http://localhost';
    }
    foreach ($pages as $key => $info) {
        $html = '<!doctype html>\n<html lang="' . e($language ?: detectPreferredLanguage()) . '">\n<head>\n';
        $html .= '  <meta charset="UTF-8">\n';
        $html .= '  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n';
        $html .= '  <title>' . e($info['title']) . '</title>\n';
        if (!empty($info['description'])) {
            $html .= '  <meta name="description" content="' . e($info['description']) . '">\n';
        }
        $html .= '  <link rel="canonical" href="' . e(rtrim($baseUrl, '/') . '/index.php?doc=' . rawurlencode($key)) . '">\n';
        $html .= '</head>\n<body>\n';
        $html .= '<main>\n<h1>' . e($info['title']) . '</h1>\n';
        foreach ((array)$info['content'] as $para) {
            $html .= '<p>' . e($para) . '</p>\n';
        }
        $html .= '</main>\n</body>\n</html>\n';
        // primary filename
        file_put_contents(__DIR__ . '/' . $key . '.html', $html);
        // also write legacy variants
        if ($key === 'about') {
            file_put_contents(__DIR__ . '/about-us.html', $html);
        }
        if ($key === 'contact') {
            file_put_contents(__DIR__ . '/contact-us.html', $html);
        }
        if ($key === 'privacy') {
            // also correct common misspelling
            file_put_contents(__DIR__ . '/privercy.html', $html);
        }
    }
}

function buildHeadingAnchorId($text, $fallback = 'section') {
    $base = strtolower(trim((string)$text));
    $base = preg_replace('/[^a-z0-9\s-]/', '', $base);
    $base = preg_replace('/\s+/', '-', (string)$base);
    $base = trim((string)$base, '-');
    if ($base === '') {
        $base = trim((string)$fallback);
    }
    return $base;
}

function buildArticleTableOfContents(array $sections) {
    if (!$sections) {
        return '';
    }

    $items = [];
    foreach ($sections as $section) {
        $title = trim((string)($section['title'] ?? ''));
        $id = trim((string)($section['id'] ?? ''));
        if ($title === '' || $id === '') {
            continue;
        }
        $items[] = "<li><a href='#" . e($id) . "'>" . e($title) . "</a></li>";
    }

    if (!$items) {
        return '';
    }

    return "<section class='article-toc'><h2>Quick Navigation</h2><ol>" . implode('', $items) . "</ol></section>";
}

function generateArticle($title) {
    $title = cleanAndNormalizeTitle($title);
    if ($title === '') {
        $title = 'New Release Review';
    }

    $activeNicheSlug = getActiveNicheSlug();
    $nicheMeta = getNicheArticleMeta($activeNicheSlug);
    $nicheType = getNicheContentType($nicheMeta);
    $model = trim(preg_replace('/\b(202[0-9]|20[0-9]{2})\b/', '', $title));
    $isEV = stripos($title, 'EV') !== false || stripos($title, 'electric') !== false || str_contains(mb_strtolower($nicheMeta['slug'], 'UTF-8'), 'ev');
    $bodyType = classifyVehicleProfile($title);
    if ($nicheType !== 'auto') {
        $bodyType = ucfirst($nicheType);
    }
    $topicContext = trim((string)$nicheMeta['intro_context']);
    if ($topicContext === '') {
        $topicContext = 'audience expectations, topical relevance, and niche-specific value';
    }

    $content = "<h1>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h1>\n";
    $content .= "<p class='text-muted'>Published " . date('F j, Y') . " • " . htmlspecialchars($nicheMeta['label'], ENT_QUOTES, 'UTF-8') . "</p>\n";
    $coverImage = buildUniqueArticleImageUrl($title, $model);
    $imageAltSuffix = trim((string)getSetting('seo_image_alt_suffix', ' - car image'));
    $imageTitleSuffix = trim((string)getSetting('seo_image_title_suffix', ' - photo'));
    $imageAlt = trim($title . ' ' . ltrim($imageAltSuffix, '- '));
    $imageTitle = trim($title . ' ' . ltrim($imageTitleSuffix, '- '));
    $content .= "<img src='" . htmlspecialchars($coverImage, ENT_QUOTES, 'UTF-8') . "' class='img-fluid rounded mb-4' alt='" . htmlspecialchars($imageAlt, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($imageTitle, ENT_QUOTES, 'UTF-8') . "' loading='eager' decoding='async' fetchpriority='high'>\n";
    $content .= "<p>" . getRandomIntro($title, $nicheMeta) . " This review follows an editorial structure designed to deliver deep analysis, clear comparisons, and practical buying guidance.</p>\n";
    $content .= "<p>The article is written for " . htmlspecialchars($nicheMeta['label'], ENT_QUOTES, 'UTF-8') . " readers and focuses on " . htmlspecialchars($topicContext, ENT_QUOTES, 'UTF-8') . " to make the decision easier for people who want more than surface-level advice.</p>\n";
    $content .= "<p>This {$title} review is optimized to answer the top buyer questions around " . htmlspecialchars($topicContext, ENT_QUOTES, 'UTF-8') . ".</p>\n";
    $content .= "<p><strong>Quick Take:</strong> The {$title} is a {$bodyType}-class product focused on balanced performance, everyday usability, and ownership predictability rather than one-dimensional headline metrics.</p>\n";
    $content .= "<p>We evaluate it with a focus on " . htmlspecialchars(getNicheWritingAngle($nicheMeta), ENT_QUOTES, 'UTF-8') . ", so the coverage stays aligned with the most meaningful buying signals for this niche.</p>\n";
    $content .= "<h2>What You Will Learn in This Guide</h2>\n";
    $content .= "<ul><li>How {$title} performs in real ownership conditions, not only in launch marketing.</li><li>Which trim strategy makes the most financial sense for different buyer types.</li><li>Where {$title} stands versus competitors in comfort, tech, efficiency, and long-term value.</li></ul>\n";

    $sections = getNicheSections($nicheType);

        'Technology Stack, Infotainment, and Connectivity Experience' => [
            'interface speed, readability, and cognitive simplicity',
            'smartphone integration and navigation reliability in practice',
            'software maturity, update path, and feature longevity'
        ],
        'Safety Systems, Driver Assistance, and Durability Outlook' => [
            'calibration quality of active safety interventions',
            'passive safety confidence and structural reassurance',
            'maintenance predictability and long-term reliability perception'
        ],
        'Ownership Economics, Trim Strategy, and Buyer Recommendations' => [
            'cost of ownership across fuel or charging, service, and insurance',
            'which configuration levels provide the strongest value density',
            'how to shortlist based on real priorities rather than hype'
        ]
    ];

    $tocSections = [];
    foreach ($sections as $sectionTitle => $focusPoints) {
        $sectionId = buildHeadingAnchorId($sectionTitle, 'section-' . (count($tocSections) + 1));
        $tocSections[] = ['title' => $sectionTitle, 'id' => $sectionId];
        $content .= "<h2 id='" . e($sectionId) . "'>{$sectionTitle}</h2>\n";
        foreach (buildSectionContent($title, $sectionTitle, $focusPoints, $nicheType, $nicheMeta) as $paragraph) {
            $content .= $paragraph . "\n";
        }
    }

    $content = preg_replace('/(<h2>What You Will Learn in This Guide<\/h2>\n<ul>.*?<\/ul>\n)/s', "$1" . buildArticleTableOfContents($tocSections) . "\n", $content, 1);

    $horsepower = rand(260, 640);
    $zeroToSixty = number_format(rand(34, 67) / 10, 1);
    $efficiencyLine = $isEV
        ? rand(420, 680) . ' km estimated range (mixed use)'
        : rand(6, 11) . ' L/100km combined estimate';
    $drivetrain = $isEV ? 'Dual Electric Motors (AWD)' : '2.5L Turbo + Advanced Automatic Transmission';

    $content .= "<h2>Technical Snapshot</h2>\n";
    $content .= "<table class='table table-bordered'><tr><th>Powertrain</th><td>{$drivetrain}</td></tr><tr><th>Output</th><td>{$horsepower} hp</td></tr><tr><th>0-60 mph</th><td>{$zeroToSixty} seconds</td></tr><tr><th>Efficiency</th><td>{$efficiencyLine}</td></tr><tr><th>Editorial Category</th><td>" . htmlspecialchars($nicheMeta['category'], ENT_QUOTES, 'UTF-8') . "</td></tr></table>\n";

    $content .= buildComparisonTable($title, $bodyType, $isEV);
    $content .= buildBuyerPersonaSection($title, $isEV, $bodyType);

    $content .= "<h2>Strengths and Trade-Offs</h2>\n";
    $content .= "<ul><li><strong>Strengths:</strong> Cohesive engineering balance, strong day-to-day usability, mature technology integration, and a clear long-term value narrative.</li><li><strong>Trade-Offs:</strong> Higher entry price in premium trims, optional packages that may overlap in features, and availability pressure in high-demand regions.</li></ul>\n";

    $content .= buildFaqSection($title, $isEV);
    $content .= buildPeopleAlsoAskSection($title, $isEV);
    $content .= buildBuyingChecklistSection($title, $isEV);

    $content .= "<h2>Final Editorial Verdict</h2>\n";
    $content .= "<p class='mt-3'>The {$title} succeeds because it behaves like a complete product, not a collection of isolated features. It combines emotional appeal with practical intelligence, and that combination is exactly what modern buyers need in an uncertain, fast-evolving market. If your priority is a vehicle that remains convincing beyond launch-week excitement, this model is a serious and well-justified candidate.</p>";

    $minimumWords = getSettingInt('min_words', 3000, 1200, 300000);
    $content = ensureMinimumWordCount($content, $title, $minimumWords, $nicheMeta);

    $plainText = trim(strip_tags($content));
    $excerpt = mb_substr($plainText, 0, 340);
    if (mb_strlen($plainText) > 340) {
        $excerpt .= '...';
    }

    $seo = buildSeoBlock($title, $excerpt, $nicheMeta);
    $content .= "\n" . $seo['html_block'];
    $content .= "\n" . buildFaqSchemaScript($title, $isEV);

    return [
        'content' => $content,
        'excerpt' => $excerpt,
        'image' => $coverImage,
        'meta_title' => $seo['meta_title'],
        'meta_description' => $seo['meta_description'],
        'focus_keywords' => $seo['keywords'],
    ];
}

function isSqliteLockedException(PDOException $exception) {
    $message = mb_strtolower($exception->getMessage(), 'UTF-8');
    return strpos($message, 'database is locked') !== false || strpos($message, 'database table is locked') !== false;
}

function executeStatementWithRetry(PDOStatement $statement, array $params, $maxAttempts = 5, $initialDelayMs = 100) {
    $attempt = 0;
    $delayMs = max(1, (int)$initialDelayMs);
    $limit = max(1, (int)$maxAttempts);

    while (true) {
        try {
            $statement->execute($params);
            return;
        } catch (PDOException $exception) {
            $attempt++;
            if ($attempt >= $limit || !isSqliteLockedException($exception)) {
                throw $exception;
            }
            usleep($delayMs * 1000);
            $delayMs *= 2;
        }
    }
}

/**
 * Notify search engines about sitemap updates. This is a best-effort ping; failures are ignored.
 */
function pingSearchEngines($sitemapUrl) {
    $sitemapUrl = trim((string)$sitemapUrl);
    if ($sitemapUrl === '') {
        return false;
    }
    $targets = [
        'https://www.google.com/ping?sitemap=' . rawurlencode($sitemapUrl),
        'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemapUrl),
    ];
    foreach ($targets as $u) {
        @file_get_contents($u);
    }
    return true;
}

/**
 * Generate or update robots.txt file with proper sitemap reference.
 */
function updateRobotsTxt() {
    $base = getSiteBaseUrl();
    if ($base === '') return false;
    $content = "User-agent: *\nAllow: /\n\n";
    $content .= "Sitemap: " . rtrim($base, '/') . "/sitemap.php\n";
    @file_put_contents(__DIR__ . '/robots.txt', $content);
    return true;
}

function saveArticle($title, $data) {
    if (isDuplicateArticlePayload($title, $data['content'] ?? '')) {
        return false;
    }

    $pdo = db_connect();
    $slug = generateUniqueSlug($title);
    $nicheId = getActiveNicheId();

    // determine translation if enabled
    $autoTranslate = getSettingInt('auto_translate_enabled', 0, 0, 1) === 1;
    $targetLang = trim((string)getSetting('auto_translate_target_language', ''));
    $translatedTitle = null;
    $translatedContent = null;
    $origLanguage = '';

    if ($autoTranslate && $targetLang !== '') {
        $translatedTitle = translateText($title, $targetLang);
        $translatedContent = translateText($data['content'] ?? '', $targetLang);
        $origLanguage = $targetLang;
    }

    $nicheMeta = getNicheArticleMeta();
    $category = trim((string)$nicheMeta['category']);
    if ($category === '') {
        $category = 'News';
    }

    $stmt = $pdo->prepare("INSERT INTO articles (title, slug, content, image, image2, excerpt, published_at, category, niche_id, translated_title, translated_content, orig_language) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    executeStatementWithRetry($stmt, [
        $title,
        $slug,
        $data['content'],
        $data['image'] ?? null,
        $data['image2'] ?? null,
        $data['excerpt'] ?? null,
        date('Y-m-d H:i:s'),
        $category,
        $nicheId,
        $translatedTitle,
        $translatedContent,
        $origLanguage
    ]);
    $articleId = (int)$pdo->lastInsertId();
    
    // Initialize stats for new article
    initArticleStats($articleId);
    
    // add tags automatically based on title/content
    $tags = generateAutoTags($title, $data['content'] ?? '');
    foreach ($tags as $tag) {
        addTagToArticle($articleId, $tag);
    }

    writeArticleExportFiles($articleId, $slug, [
        'id' => $articleId,
        'title' => $title,
        'slug' => $slug,
        'content' => $data['content'],
        'excerpt' => $data['excerpt'],
        'image' => $data['image'] ?? null,
        'image2' => $data['image2'] ?? null,
        'translated_title' => $translatedTitle,
        'translated_content' => $translatedContent,
        'meta_title' => $data['meta_title'] ?? null,
        'meta_description' => $data['meta_description'] ?? null,
        'focus_keywords' => $data['focus_keywords'] ?? [],
        'published_at' => date('c'),
    ]);

    // kick the search engines to recrawl sitemap
    pingSearchEngines(getSiteBaseUrl() . '/sitemap.php');

    return true;
}

function estimateReadingTime($htmlContent) {
    $wordCount = str_word_count(strip_tags($htmlContent));
    return max(1, (int)ceil($wordCount / 220));
}

function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}
