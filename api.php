<?php
require_once 'functions.php';
publishAutoArticleBySchedule();

header('Content-Type: application/json; charset=utf-8');

$pdo = db_connect();
$apiLockEnabled = getSettingInt('api_lock_enabled', 0, 0, 1) === 1;
if ($apiLockEnabled) {
    $configuredApiKey = trim((string)getSetting('api_access_key', ''));
    $requestApiKey = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));

    if ($configuredApiKey === '' || !hash_equals($configuredApiKey, $requestApiKey)) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'invalid_api_key',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$endpoint = $_GET['endpoint'] ?? 'articles';

if ($endpoint === 'rate_article') {
    $articleId = (int)($_POST['article_id'] ?? $_GET['article_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? $_GET['rating'] ?? 0);
    
    if ($articleId > 0 && $rating >= 1 && $rating <= 5) {
        $success = rateArticle($articleId, $rating, getVisitorFingerprint());
        echo json_encode(['ok' => $success], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($endpoint === 'stats') {
    $totalArticlesStmt = $pdo->prepare("SELECT COUNT(*) FROM articles");
    $totalArticlesStmt->execute();
    $totalArticles = (int)$totalArticlesStmt->fetchColumn();
    $totalSourcesStmt = $pdo->prepare("SELECT COUNT(*) FROM rss_sources");
    $totalSourcesStmt->execute();
    $totalSources = (int)$totalSourcesStmt->fetchColumn();
    $totalWebSourcesStmt = $pdo->prepare("SELECT COUNT(*) FROM web_sources");
    $totalWebSourcesStmt->execute();
    $totalWebSources = (int)$totalWebSourcesStmt->fetchColumn();
    $latestPublishStmt = $pdo->prepare("SELECT MAX(published_at) FROM articles");
    $latestPublishStmt->execute();
    $latestPublish = $latestPublishStmt->fetchColumn();
    $activeNiche = getActiveNicheSlug();
    $nicheRssCount = count(getNicheRssSources($activeNiche));
    $nicheWebCount = count(getNicheWebSources($activeNiche));
    $workflowSummary = getContentWorkflowSummary();

    echo json_encode([
        'site' => getSiteTitle(),
        'active_niche' => $activeNiche,
        'active_niche_rss_sources' => $nicheRssCount,
        'active_niche_web_sources' => $nicheWebCount,
        'global_rss_sources' => $totalSources,
        'global_web_sources' => $totalWebSources,
        'total_articles' => $totalArticles,
        'selected_content_workflow' => getSelectedContentWorkflow(),
        'workflow_summary' => $workflowSummary,
        'latest_publish' => $latestPublish,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($endpoint === 'article') {
    $slug = trim($_GET['slug'] ?? '');
    if ($slug === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing slug parameter.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, content, image, image2, translated_title, translated_content, orig_language, category, published_at FROM articles WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        http_response_code(404);
        echo json_encode(['error' => 'Article not found.']);
        exit;
    }

    $article['reading_time_min'] = estimateReadingTime($article['content']);
    echo json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$publishedFrom = normalizeDateInput($_GET['published_from'] ?? '');
$publishedTo = normalizeDateInput($_GET['published_to'] ?? '');
if ($publishedFrom !== '' && $publishedTo !== '' && $publishedFrom > $publishedTo) {
    [$publishedFrom, $publishedTo] = [$publishedTo, $publishedFrom];
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
$sort = $_GET['sort'] ?? 'newest';

$sortMap = [
    'newest' => 'id DESC',
    'oldest' => 'id ASC',
    'title_asc' => 'title ASC',
    'title_desc' => 'title DESC',
    'relevance' => 'id DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];

$clauses = [];
if ($q !== '') {
    $clauses[] = '(title LIKE :q OR excerpt LIKE :q OR content LIKE :q)';
}
if ($category !== '') {
    $clauses[] = 'category = :category';
}
if ($publishedFrom !== '') {
    $clauses[] = 'DATE(published_at) >= :published_from';
}
if ($publishedTo !== '') {
    $clauses[] = 'DATE(published_at) <= :published_to';
}
$where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM articles $where");
if ($q !== '') {
    $countStmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
}
if ($category !== '') {
    $countStmt->bindValue(':category', $category, PDO::PARAM_STR);
}
if ($publishedFrom !== '') {
    $countStmt->bindValue(':published_from', $publishedFrom, PDO::PARAM_STR);
}
if ($publishedTo !== '') {
    $countStmt->bindValue(':published_to', $publishedTo, PDO::PARAM_STR);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

if ($sort === 'relevance' && $q !== '') {
    $orderBy = '(CASE WHEN title LIKE :q_exact THEN 100 ELSE 0 END + CASE WHEN excerpt LIKE :q_exact THEN 45 ELSE 0 END + CASE WHEN content LIKE :q_exact THEN 25 ELSE 0 END + CASE WHEN title LIKE :q THEN 20 ELSE 0 END + CASE WHEN excerpt LIKE :q THEN 10 ELSE 0 END) DESC, id DESC';
}

$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, image, image2, translated_title, translated_content, category, published_at, content FROM articles $where ORDER BY $orderBy LIMIT :limit OFFSET :offset");
if ($q !== '') {
    $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
    if ($sort === 'relevance') {
        $stmt->bindValue(':q_exact', $q . '%', PDO::PARAM_STR);
    }
}
if ($category !== '') {
    $stmt->bindValue(':category', $category, PDO::PARAM_STR);
}
if ($publishedFrom !== '') {
    $stmt->bindValue(':published_from', $publishedFrom, PDO::PARAM_STR);
}
if ($publishedTo !== '') {
    $stmt->bindValue(':published_to', $publishedTo, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$articles = [];
foreach ($rows as $row) {
    $row['reading_time_min'] = estimateReadingTime($row['content']);
    unset($row['content']);
    $articles[] = $row;
}

echo json_encode([
    'endpoint' => 'articles',
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => $totalPages,
    'filters' => [
        'q' => $q,
        'category' => $category,
        'published_from' => $publishedFrom,
        'published_to' => $publishedTo,
        'sort' => $sort,
    ],
    'items' => $articles,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
